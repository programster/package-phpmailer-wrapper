<?php

/*
 * A fake SMTP server for the tests to send emails to, so they need no network access or SMTP account. It runs in its
 * own PHP process (FakeSmtpServerProcess), on 127.0.0.1, which works in a container with no network (docker run
 * --network none), and records everything it's sent, which fetchSessions() reads back, so a test can check exactly
 * what PhpMailerEmailer sent. It can be told to refuse a recipient or a command with a reply of the test's choosing,
 * and to offer STARTTLS, or use TLS from the start, with a self-signed certificate (see TestCertificate).
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;


final class FakeSmtpServer
{
    private readonly string $m_directory;
    private readonly int $m_port;
    private readonly TestCertificate $m_certificate;

    /** @var resource|null - the server's process, or null once it's been stopped */
    private $m_process;


    /**
     * @param string $directory - the server's temporary directory, for its config, certificate and records.
     * @param resource $process - the server's process.
     * @param int $port - the port it's listening on.
     * @param TestCertificate $certificate - the certificate it uses for TLS.
     */
    private function __construct(string $directory, $process, int $port, TestCertificate $certificate)
    {
        $this->m_directory = $directory;
        $this->m_process = $process;
        $this->m_port = $port;
        $this->m_certificate = $certificate;
    }


    /**
     * Create a fake SMTP server, and start it. It's stopped when the object is destroyed, or by stop().
     *
     * @param FakeSmtpServerMode $mode - how it handles encryption.
     *
     * @param FakeSmtpRefusal ...$refusals - the recipients and commands to refuse, and the replies to refuse them
     * with. Everything else is accepted.
     *
     * @return FakeSmtpServer
     *
     * @throws \RuntimeException - if it can't be started.
     */
    public static function create(FakeSmtpServerMode $mode, FakeSmtpRefusal ...$refusals) : FakeSmtpServer
    {
        $directory = sys_get_temp_dir() . '/phpmailer-fake-smtp-' . bin2hex(random_bytes(8));

        if (!mkdir($directory, 0700))
        {
            throw new \RuntimeException("Couldn't create a directory for the fake SMTP server: {$directory}");
        }

        $certificate = TestCertificate::createInDirectory($directory);
        $replies = [];

        foreach ($refusals as $refusal)
        {
            $replies[$refusal->getKey()] = $refusal->getReply();
        }

        $config = ['mode' => $mode->value, 'directory' => $directory, 'refusals' => $replies];
        file_put_contents("{$directory}/config.json", json_encode($config, JSON_THROW_ON_ERROR));
        $command = [PHP_BINARY, '-d', 'display_errors=stderr', __DIR__ . '/serve.php', "{$directory}/config.json"];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', "{$directory}/server-errors.log", 'a']];
        $process = proc_open($command, $descriptors, $pipes);

        if ($process === false)
        {
            throw new \RuntimeException("Couldn't start the fake SMTP server.");
        }

        stream_set_timeout($pipes[1], 10);
        $firstLine = (string) fgets($pipes[1]);

        if (preg_match('/^PORT ([0-9]+)$/', trim($firstLine), $matches) !== 1)
        {
            proc_terminate($process);
            proc_close($process);
            $errors = (string) @file_get_contents("{$directory}/server-errors.log");
            throw new \RuntimeException("The fake SMTP server didn't start: {$firstLine} {$errors}");
        }

        return new FakeSmtpServer($directory, $process, (int) $matches[1], $certificate);
    }


    /**
     * Read back what the server has recorded: one session for each connection made to it, in order.
     * @return FakeSmtpSessionCollection
     * @throws \UnexpectedValueException - if a record can't be read.
     */
    public function fetchSessions() : FakeSmtpSessionCollection
    {
        $sessions = new FakeSmtpSessionCollection();
        $paths = glob("{$this->m_directory}/session-*.json") ?: [];
        sort($paths);

        foreach ($paths as $path)
        {
            $record = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($record))
            {
                throw new \UnexpectedValueException("The fake SMTP server's record in {$path} isn't valid.");
            }

            $sessions->add(FakeSmtpSession::createFromArray($record));
        }

        return $sessions;
    }


    /**
     * Read back the only session the server has recorded, throwing if there wasn't exactly one.
     * @return FakeSmtpSession
     * @throws \LogicException - if there wasn't exactly one session.
     */
    public function fetchOnlySession() : FakeSmtpSession
    {
        $sessions = $this->fetchSessions();

        if (count($sessions) !== 1)
        {
            $numSessions = count($sessions);
            throw new \LogicException("Expected the fake SMTP server to have one session, but it had {$numSessions}");
        }

        return $sessions->first();
    }


    /**
     * Describe the server's state, for a test's failure message: whether it's running, and any errors it's written.
     * @return string
     */
    public function fetchDiagnostics() : string
    {
        $isRunning = ($this->m_process !== null && proc_get_status($this->m_process)['running']);
        $errors = trim((string) @file_get_contents("{$this->m_directory}/server-errors.log"));
        $state = $isRunning ? 'running' : 'not running';
        return "the fake SMTP server on port {$this->m_port} is {$state}, with errors: {$errors}";
    }


    /**
     * Stop the server, and delete its directory. Does nothing if it's already stopped.
     * @return void
     */
    public function stop() : void
    {
        if ($this->m_process !== null)
        {
            proc_terminate($this->m_process);
            proc_close($this->m_process);
            $this->m_process = null;

            foreach (glob("{$this->m_directory}/*") ?: [] as $path)
            {
                unlink($path);
            }

            rmdir($this->m_directory);
        }
    }


    public function __destruct()
    {
        $this->stop();
    }


    # Accessors
    public function getPort() : int { return $this->m_port; }
    public function getCertificatePath() : string { return $this->m_certificate->getCertificatePath(); }
}
