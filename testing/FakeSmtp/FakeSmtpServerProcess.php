<?php

/*
 * The fake SMTP server itself, which runs in its own PHP process (started by FakeSmtpServer, through serve.php), as
 * PHPMailer's send() blocks until the server replies. It listens on 127.0.0.1, on a port the system picks, and handles
 * one connection at a time. It records everything the client sends in each session (each connection) in a JSON file,
 * which it rewrites before every reply, so by the time the client has its reply, the file has the command it replied
 * to. It speaks just enough SMTP for PHPMailer: EHLO/HELO, STARTTLS, AUTH LOGIN and PLAIN, MAIL, RCPT, DATA, RSET,
 * NOOP and QUIT.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;


final class FakeSmtpServerProcess
{
    private const HOSTNAME = 'fake-smtp.test';

    // How long to wait for a connection, or for a client to send the next line, before giving up, so a server that's
    // been left running (e.g. its test crashed) exits by itself.
    private const TIMEOUT_SECONDS = 60;

    private readonly FakeSmtpServerMode $m_mode;
    private readonly string $m_directory;

    /** @var array<string, string> - the refusals' replies, by FakeSmtpRefusal's key */
    private readonly array $m_refusals;

    // What's been recorded of the current session (see FakeSmtpSession).
    private int $m_sessionNumber = 0;

    /** @var list<string> */
    private array $m_commandsBeforeTls = [];

    /** @var list<string> */
    private array $m_commandsOverTls = [];

    /** @var list<string> - the messages, in base64, as their bytes needn't be valid UTF-8, which JSON needs */
    private array $m_messages = [];

    /** @var list<string> */
    private array $m_recipients = [];

    private string $m_mailFrom = '';
    private string $m_authUsername = '';
    private string $m_authPassword = '';
    private bool $m_usedTls = false;
    private bool $m_tlsHandshakeFailed = false;


    /**
     * @param array<mixed> $config - the server's settings, from FakeSmtpServer.
     * @throws \LogicException - if the config isn't valid.
     */
    public function __construct(array $config)
    {
        $mode = FakeSmtpServerMode::tryFrom(is_string($config['mode'] ?? null) ? $config['mode'] : '');
        $directory = $config['directory'] ?? null;
        $refusalsConfig = $config['refusals'] ?? null;

        if ($mode === null || !is_string($directory) || !is_dir($directory) || !is_array($refusalsConfig))
        {
            throw new \LogicException("The fake SMTP server's config isn't valid.");
        }

        $refusals = [];

        foreach ($refusalsConfig as $key => $reply)
        {
            if (!is_string($key) || !is_string($reply))
            {
                throw new \LogicException("A refusal in the fake SMTP server's config isn't valid.");
            }

            $refusals[$key] = $reply;
        }

        $this->m_mode = $mode;
        $this->m_directory = $directory;
        $this->m_refusals = $refusals;
    }


    /**
     * Listen, print the port to STDOUT (as "PORT 12345") for FakeSmtpServer to read, and handle connections until
     * the process is stopped, or no connection is made for TIMEOUT_SECONDS.
     *
     * @return void
     *
     * @throws \RuntimeException - if it can't listen.
     */
    public function serve() : void
    {
        $context = stream_context_create([
            'ssl' => [
                'local_cert' => $this->m_directory . '/certificate.pem',
                'local_pk' => $this->m_directory . '/private-key.pem',
                'verify_peer' => false,
            ],
        ]);

        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $server = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage, $flags, $context);

        if ($server === false)
        {
            throw new \RuntimeException("The fake SMTP server couldn't listen: {$errorMessage}");
        }

        $address = (string) stream_socket_get_name($server, false);
        $port = substr($address, strrpos($address, ':') + 1);
        fwrite(STDOUT, "PORT {$port}\n");
        fflush(STDOUT);
        $idleSince = time();

        // accept() can give up before its timeout (e.g. if it's interrupted by a signal), so the server only stops
        // once it's really had no connection for TIMEOUT_SECONDS.
        while (time() - $idleSince < self::TIMEOUT_SECONDS)
        {
            $connection = @stream_socket_accept($server, self::TIMEOUT_SECONDS);

            if ($connection === false)
            {
                $error = error_get_last();
                fwrite(STDERR, "accept() gave up: " . ($error['message'] ?? 'no error') . "\n");
            }
            else
            {
                stream_set_timeout($connection, self::TIMEOUT_SECONDS);
                $this->handleSession($connection);
                @fclose($connection);
                $idleSince = time();
            }
        }
    }


    /**
     * Speak SMTP with one client, until it quits or disconnects.
     * @param resource $connection - the client's connection.
     * @return void
     */
    private function handleSession($connection) : void
    {
        $this->m_sessionNumber++;

        $this->m_commandsBeforeTls = [];
        $this->m_commandsOverTls = [];
        $this->m_messages = [];
        $this->m_recipients = [];
        $this->m_mailFrom = '';
        $this->m_authUsername = '';
        $this->m_authPassword = '';
        $this->m_usedTls = false;
        $this->m_tlsHandshakeFailed = false;

        $isOpen = true;

        if ($this->m_mode === FakeSmtpServerMode::IMPLICIT_TLS)
        {
            $isOpen = $this->startTls($connection);
        }

        if ($isOpen)
        {
            $this->reply($connection, "220 " . self::HOSTNAME . " ESMTP fake server ready");
        }

        while ($isOpen)
        {
            $line = $this->readLine($connection);

            if ($line === null)
            {
                $isOpen = false;
            }
            else
            {
                $this->recordCommand($line);
                $isOpen = $this->handleCommand($connection, $line);
            }
        }

        $this->saveSession();
    }


    /**
     * Reply to one command.
     * @param resource $connection - the client's connection.
     * @param string $line - the command.
     * @return bool - whether the session carries on.
     */
    private function handleCommand($connection, string $line) : bool
    {
        $isOpen = true;
        $verb = strtoupper((string) strtok($line, ' '));
        $argument = trim(substr($line, strlen($verb)));

        switch ($verb)
        {
            case 'EHLO':
            {
                $this->replyToEhlo($connection);
            }
            break;

            case 'HELO':
            {
                $this->reply($connection, "250 " . self::HOSTNAME);
            }
            break;

            case 'STARTTLS':
            {
                $isOpen = $this->handleStartTls($connection);
            }
            break;

            case 'AUTH':
            {
                $isOpen = $this->handleAuth($connection, $argument);
            }
            break;

            case 'MAIL':
            {
                $this->m_mailFrom = $this->extractAddress($argument);
                $this->replyOrRefuse($connection, FakeSmtpCommand::MAIL_FROM->value, "250 2.1.0 OK");
            }
            break;

            case 'RCPT':
            {
                $recipient = $this->extractAddress($argument);
                $this->m_recipients[] = $recipient;
                $this->replyOrRefuse($connection, FakeSmtpRefusal::createRecipientKey($recipient), "250 2.1.5 OK");
            }
            break;

            case 'DATA':
            {
                $isOpen = $this->handleData($connection);
            }
            break;

            case 'RSET':
            case 'NOOP':
            {
                $this->reply($connection, "250 2.0.0 OK");
            }
            break;

            case 'QUIT':
            {
                $this->reply($connection, "221 2.0.0 Bye");
                $isOpen = false;
            }
            break;

            default:
            {
                $this->reply($connection, "502 5.5.2 Command not recognised");
            }
        }

        return $isOpen;
    }


    /**
     * Reply to EHLO with the server's capabilities: AUTH, and STARTTLS if it's offered and not already in use.
     * @param resource $connection - the client's connection.
     * @return void
     */
    private function replyToEhlo($connection) : void
    {
        $lines = ["250-" . self::HOSTNAME, "250-AUTH PLAIN LOGIN"];
        $offersStartTls = ($this->m_mode === FakeSmtpServerMode::STARTTLS && !$this->m_usedTls);

        if ($offersStartTls)
        {
            $lines[] = "250-STARTTLS";
        }

        $lines[] = "250 8BITMIME";
        $this->reply($connection, implode("\r\n", $lines));
    }


    /**
     * Handle STARTTLS: refuse it if told to, or if it isn't offered, or upgrade the connection to TLS.
     * @param resource $connection - the client's connection.
     * @return bool - whether the session carries on, which it doesn't if the TLS handshake failed.
     */
    private function handleStartTls($connection) : bool
    {
        $isOpen = true;

        if (isset($this->m_refusals[FakeSmtpCommand::STARTTLS->value]))
        {
            $this->reply($connection, $this->m_refusals[FakeSmtpCommand::STARTTLS->value]);
        }
        elseif ($this->m_mode !== FakeSmtpServerMode::STARTTLS || $this->m_usedTls)
        {
            $this->reply($connection, "502 5.5.1 STARTTLS isn't offered");
        }
        else
        {
            $this->reply($connection, "220 2.0.0 Ready to start TLS");
            $isOpen = $this->startTls($connection);
        }

        return $isOpen;
    }


    /**
     * Handle AUTH LOGIN (the username and password, each in base64, in reply to 334 prompts) or AUTH PLAIN (both in
     * one base64 string, after the command), recording the username and password.
     *
     * @param resource $connection - the client's connection.
     *
     * @param string $argument - what follows AUTH, e.g. "LOGIN", or "PLAIN <credentials>".
     *
     * @return bool - whether the session carries on.
     */
    private function handleAuth($connection, string $argument) : bool
    {
        $isOpen = true;
        $parts = explode(' ', $argument, 2);
        $mechanism = strtoupper($parts[0]);

        if ($mechanism === 'LOGIN')
        {
            $this->reply($connection, "334 VXNlcm5hbWU6");
            $username = $this->readLine($connection);
            $this->reply($connection, "334 UGFzc3dvcmQ6");
            $password = ($username === null) ? null : $this->readLine($connection);

            if ($username === null || $password === null)
            {
                $isOpen = false;
            }
            else
            {
                $this->m_authUsername = (string) base64_decode($username);
                $this->m_authPassword = (string) base64_decode($password);
                $this->replyOrRefuse($connection, FakeSmtpCommand::AUTH->value, "235 2.7.0 Authentication succeeded");
            }
        }
        elseif ($mechanism === 'PLAIN' && isset($parts[1]))
        {
            $credentials = explode("\0", (string) base64_decode($parts[1]));
            $this->m_authUsername = $credentials[1] ?? '';
            $this->m_authPassword = $credentials[2] ?? '';
            $this->replyOrRefuse($connection, FakeSmtpCommand::AUTH->value, "235 2.7.0 Authentication succeeded");
        }
        else
        {
            $this->reply($connection, "504 5.5.4 Unrecognised authentication type");
        }

        return $isOpen;
    }


    /**
     * Handle DATA: read the message, up to the line with just a dot on it, and accept or refuse it.
     * @param resource $connection - the client's connection.
     * @return bool - whether the session carries on.
     */
    private function handleData($connection) : bool
    {
        $isOpen = true;
        $this->reply($connection, "354 Start mail input, end with <CRLF>.<CRLF>");
        $lines = [];
        $line = $this->readLine($connection);

        while ($line !== null && $line !== '.')
        {
            // Undo the client's dot-stuffing: a line that starts with a dot has another one added in front.
            $lines[] = str_starts_with($line, '..') ? substr($line, 1) : $line;
            $line = $this->readLine($connection);
        }

        if ($line === null)
        {
            $isOpen = false;
        }
        else
        {
            $this->m_messages[] = base64_encode(implode("\r\n", $lines) . "\r\n");
            $this->replyOrRefuse($connection, FakeSmtpCommand::DATA->value, "250 2.0.0 OK queued");
        }

        return $isOpen;
    }


    /**
     * Upgrade the connection to TLS, as the server, recording whether it worked.
     * @param resource $connection - the client's connection.
     * @return bool - whether the handshake worked.
     */
    private function startTls($connection) : bool
    {
        // The client refusing the certificate (as it should, when it can't verify it) is a failed handshake, which
        // is recorded, rather than warned about.
        $worked = (@stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_SERVER) === true);
        $this->m_usedTls = $worked;
        $this->m_tlsHandshakeFailed = !$worked;
        $this->saveSession();
        return $worked;
    }


    /**
     * Reply with the refusal for this key, if there is one, or the reply for success.
     * @param resource $connection - the client's connection.
     * @param string $refusalKey - the command (e.g. "DATA"), or "RCPT <address>" for a recipient.
     * @param string $successReply - the reply if it isn't refused.
     * @return void
     */
    private function replyOrRefuse($connection, string $refusalKey, string $successReply) : void
    {
        $this->reply($connection, $this->m_refusals[$refusalKey] ?? $successReply);
    }


    /**
     * Save what's been recorded so far, then send a reply.
     * @param resource $connection - the client's connection.
     * @param string $reply - the reply, whose lines are separated by "\r\n".
     * @return void
     */
    private function reply($connection, string $reply) : void
    {
        $this->saveSession();
        @fwrite($connection, $reply . "\r\n");
    }


    /**
     * Read one line from the client.
     * @param resource $connection - the client's connection.
     * @return ?string - the line, without its line ending, or null if the client disconnected or went quiet.
     */
    private function readLine($connection) : ?string
    {
        $line = @fgets($connection);
        return ($line === false) ? null : rtrim($line, "\r\n");
    }


    /**
     * Record a command the client sent, in the list for before or after TLS started.
     * @param string $line - the command.
     * @return void
     */
    private function recordCommand(string $line) : void
    {
        if ($this->m_usedTls)
        {
            $this->m_commandsOverTls[] = $line;
        }
        else
        {
            $this->m_commandsBeforeTls[] = $line;
        }
    }


    /**
     * Get the address from a MAIL FROM or RCPT TO command's argument, e.g. "TO:<ann@example.com>".
     * @param string $argument - what follows MAIL or RCPT.
     * @return string
     */
    private function extractAddress(string $argument) : string
    {
        $matched = preg_match('/<([^>]*)>/', $argument, $matches);
        return ($matched === 1) ? $matches[1] : $argument;
    }


    /**
     * Write the current session's record to its file, replacing it in one go, so it's never read half-written.
     * @return void
     */
    private function saveSession() : void
    {
        $path = sprintf('%s/session-%04d.json', $this->m_directory, $this->m_sessionNumber);

        $session = [
            'commandsBeforeTls' => $this->m_commandsBeforeTls,
            'commandsOverTls' => $this->m_commandsOverTls,
            'messages' => $this->m_messages,
            'recipients' => $this->m_recipients,
            'mailFrom' => $this->m_mailFrom,
            'authUsername' => $this->m_authUsername,
            'authPassword' => $this->m_authPassword,
            'usedTls' => $this->m_usedTls,
            'tlsHandshakeFailed' => $this->m_tlsHandshakeFailed,
        ];

        file_put_contents("{$path}.tmp", json_encode($session, JSON_THROW_ON_ERROR));
        rename("{$path}.tmp", $path);
    }
}
