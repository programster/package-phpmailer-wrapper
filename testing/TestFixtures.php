<?php

/*
 * What the tests share: the files they attach, and emailers set up to send to a fake SMTP server.
 */

namespace Programster\Phpmailer\Testing;

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\SecurityProtocol;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;


final class TestFixtures
{
    public const SAMPLE_PDF = __DIR__ . '/files/sample.pdf';
    public const SAMPLE_PNG = __DIR__ . '/files/sample.png';
    public const NOTES_TXT = __DIR__ . '/files/notes.txt';

    public const SMTP_USER = 'test-user';
    public const SMTP_PASSWORD = 'test-password';
    public const FROM_EMAIL = 'sender@example.com';
    public const FROM_NAME = 'Sender Name';


    /**
     * Create an emailer that sends to a fake SMTP server, as 127.0.0.1, logging in as SMTP_USER.
     *
     * @param FakeSmtpServer $server - the server to send to.
     *
     * @param SecurityProtocol $protocol - the security protocol. Defaults to none, as the server's certificate can't be
     * trusted in this process (see ChildProcessSendResult).
     *
     * @param ?Contact $replyTo - the reply-to address, or null for it to be the from address.
     *
     * @param string $charset - the character set.
     *
     * @return PhpMailerEmailer
     */
    public static function createEmailer(
        FakeSmtpServer $server,
        SecurityProtocol $protocol = SecurityProtocol::NONE,
        ?Contact $replyTo = null,
        string $charset = 'utf-8'
    ) : PhpMailerEmailer
    {
        return new PhpMailerEmailer(
            smtpHost: '127.0.0.1',
            smtpUser: self::SMTP_USER,
            smtpPassword: self::SMTP_PASSWORD,
            securityProtocol: $protocol,
            from: new Contact(self::FROM_EMAIL, self::FROM_NAME),
            smtpPort: $server->getPort(),
            replyTo: $replyTo,
            charset: $charset
        );
    }


    /**
     * Create a collection of one contact, Ann, to send a test email to.
     * @return ContactCollection
     */
    public static function createAnn() : ContactCollection
    {
        return new ContactCollection(new Contact('ann@example.com', 'Ann'));
    }


    /**
     * Find a port on 127.0.0.1 that nothing is listening on, to send to a server that can't be reached.
     * @return int
     * @throws \RuntimeException - if it can't.
     */
    public static function findUnusedPort() : int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);

        if ($socket === false)
        {
            throw new \RuntimeException("Couldn't find an unused port: {$errorMessage}");
        }

        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($address, strrpos($address, ':') + 1);
    }


    /**
     * Read a file the tests attach, to compare with what was sent.
     * @param string $path - the file, e.g. SAMPLE_PDF.
     * @return string
     * @throws \RuntimeException - if it can't be read.
     */
    public static function readFile(string $path) : string
    {
        $contents = file_get_contents($path);

        if ($contents === false)
        {
            throw new \RuntimeException("Couldn't read {$path}");
        }

        return $contents;
    }
}
