<?php

/*
 * What the fake SMTP server recorded of one session (one connection): every command the client sent, before and
 * after TLS started, the credentials it logged in with, and each message it sent.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;

use Programster\Phpmailer\Testing\Mime\MimePart;


final readonly class FakeSmtpSession
{
    /** @var list<string> */
    private array $m_commandsBeforeTls;

    /** @var list<string> */
    private array $m_commandsOverTls;

    /** @var list<string> - the raw messages, as sent after DATA */
    private array $m_messages;

    /** @var list<string> - the address of every RCPT TO, whether or not it was refused */
    private array $m_recipients;

    private string $m_mailFrom;
    private string $m_authUsername;
    private string $m_authPassword;
    private bool $m_usedTls;
    private bool $m_tlsHandshakeFailed;


    /**
     * @param list<string> $commandsBeforeTls - the commands sent in plain text (all of them, if TLS wasn't used).
     * @param list<string> $commandsOverTls - the commands sent over TLS.
     * @param list<string> $messages - the raw messages.
     * @param list<string> $recipients - the address of every RCPT TO.
     * @param string $mailFrom - the address of MAIL FROM.
     * @param string $authUsername - the username the client logged in with.
     * @param string $authPassword - the password the client logged in with.
     * @param bool $usedTls - whether the session was encrypted (by STARTTLS, or from the start).
     * @param bool $tlsHandshakeFailed - whether the client gave up on the TLS handshake, e.g. distrusting the cert.
     */
    private function __construct(
        array $commandsBeforeTls,
        array $commandsOverTls,
        array $messages,
        array $recipients,
        string $mailFrom,
        string $authUsername,
        string $authPassword,
        bool $usedTls,
        bool $tlsHandshakeFailed
    )
    {
        $this->m_commandsBeforeTls = $commandsBeforeTls;
        $this->m_commandsOverTls = $commandsOverTls;
        $this->m_messages = $messages;
        $this->m_recipients = $recipients;
        $this->m_mailFrom = $mailFrom;
        $this->m_authUsername = $authUsername;
        $this->m_authPassword = $authPassword;
        $this->m_usedTls = $usedTls;
        $this->m_tlsHandshakeFailed = $tlsHandshakeFailed;
    }


    /**
     * Create a session from the server's record of it.
     * @param array<mixed> $record - the decoded JSON the server wrote.
     * @return FakeSmtpSession
     * @throws \UnexpectedValueException - if the record isn't what the server writes.
     */
    public static function createFromArray(array $record) : FakeSmtpSession
    {
        $messages = [];

        foreach (self::getStringList($record, 'messages') as $encodedMessage)
        {
            $messages[] = (string) base64_decode($encodedMessage, true);
        }

        return new FakeSmtpSession(
            self::getStringList($record, 'commandsBeforeTls'),
            self::getStringList($record, 'commandsOverTls'),
            $messages,
            self::getStringList($record, 'recipients'),
            self::getString($record, 'mailFrom'),
            self::getString($record, 'authUsername'),
            self::getString($record, 'authPassword'),
            ($record['usedTls'] ?? null) === true,
            ($record['tlsHandshakeFailed'] ?? null) === true
        );
    }


    /**
     * Get the one message sent in this session, as it was sent, throwing if there wasn't exactly one.
     * @return string
     * @throws \LogicException - if there wasn't exactly one message.
     */
    public function getOnlyRawMessage() : string
    {
        if (count($this->m_messages) !== 1)
        {
            $numMessages = count($this->m_messages);
            throw new \LogicException("Expected the session to have one message, but it had {$numMessages}");
        }

        return $this->m_messages[0];
    }


    /**
     * Get the one message sent in this session, parsed, throwing if there wasn't exactly one.
     * @return MimePart
     * @throws \LogicException - if there wasn't exactly one message.
     */
    public function getOnlyMessage() : MimePart
    {
        return MimePart::createFromString($this->getOnlyRawMessage());
    }


    /**
     * Get every command the client sent, in order, whether in plain text or over TLS.
     * @return list<string>
     */
    public function getAllCommands() : array
    {
        return array_merge($this->m_commandsBeforeTls, $this->m_commandsOverTls);
    }


    /**
     * Get the verbs (e.g. "EHLO", "MAIL") of the commands the client sent in plain text, in order.
     * @return list<string>
     */
    public function getVerbsBeforeTls() : array
    {
        return self::getVerbs($this->m_commandsBeforeTls);
    }


    /**
     * Get the verbs (e.g. "EHLO", "MAIL") of the commands the client sent over TLS, in order.
     * @return list<string>
     */
    public function getVerbsOverTls() : array
    {
        return self::getVerbs($this->m_commandsOverTls);
    }


    /**
     * Get the verbs of some commands, e.g. "MAIL" for "MAIL FROM:<ann@example.com>".
     * @param list<string> $commands - the commands.
     * @return list<string>
     */
    private static function getVerbs(array $commands) : array
    {
        $verbs = [];

        foreach ($commands as $command)
        {
            $verbs[] = strtoupper(explode(' ', $command)[0]);
        }

        return $verbs;
    }


    /**
     * Get a string from the record.
     * @param array<mixed> $record - the record.
     * @param string $key - the key.
     * @return string
     * @throws \UnexpectedValueException - if it isn't a string.
     */
    private static function getString(array $record, string $key) : string
    {
        $value = $record[$key] ?? null;

        if (!is_string($value))
        {
            throw new \UnexpectedValueException("The fake SMTP server's record has no string \"{$key}\".");
        }

        return $value;
    }


    /**
     * Get a list of strings from the record.
     * @param array<mixed> $record - the record.
     * @param string $key - the key.
     * @return list<string>
     * @throws \UnexpectedValueException - if it isn't a list of strings.
     */
    private static function getStringList(array $record, string $key) : array
    {
        $value = $record[$key] ?? null;
        $strings = [];

        if (!is_array($value) || !array_is_list($value))
        {
            throw new \UnexpectedValueException("The fake SMTP server's record has no list \"{$key}\".");
        }

        foreach ($value as $item)
        {
            $strings[] = is_string($item) ? $item : throw new \UnexpectedValueException("\"{$key}\" isn't strings.");
        }

        return $strings;
    }


    # Accessors
    /** @return list<string> */
    public function getCommandsBeforeTls() : array { return $this->m_commandsBeforeTls; }

    /** @return list<string> */
    public function getCommandsOverTls() : array { return $this->m_commandsOverTls; }

    /** @return list<string> */
    public function getRecipients() : array { return $this->m_recipients; }

    public function getNumMessages() : int { return count($this->m_messages); }
    public function getMailFrom() : string { return $this->m_mailFrom; }
    public function getAuthUsername() : string { return $this->m_authUsername; }
    public function getAuthPassword() : string { return $this->m_authPassword; }
    public function usedTls() : bool { return $this->m_usedTls; }
    public function tlsHandshakeFailed() : bool { return $this->m_tlsHandshakeFailed; }
}
