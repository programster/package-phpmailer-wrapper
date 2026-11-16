<?php

/*
 * Sends one email with PhpMailerEmailer, for ChildProcessSendResult, which runs this in its own PHP process, so it can
 * trust the fake SMTP server's certificate (with openssl.cafile, which can't be changed once PHP has started).
 * Takes the settings as JSON, and prints the result as JSON: php send-in-child.php '<settings>'
 */

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\ExceptionFailedToSendEmail;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\SecurityProtocol;

require_once(__DIR__ . '/../../vendor/autoload.php');

$settings = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
$host = is_array($settings) ? ($settings['host'] ?? null) : null;
$port = is_array($settings) ? ($settings['port'] ?? null) : null;
$protocol = is_array($settings) ? ($settings['protocol'] ?? null) : null;

if (!is_string($host) || !is_int($port) || !is_string($protocol))
{
    fwrite(STDERR, "The settings aren't valid.\n");
    exit(1);
}

$mailer = new PhpMailerEmailer(
    smtpHost: $host,
    smtpUser: 'child-user',
    smtpPassword: 'child-password',
    securityProtocol: SecurityProtocol::from($protocol),
    from: new Contact('sender@example.com', 'Sender'),
    smtpPort: $port
);

try
{
    $mailer->send(
        subject: 'Sent over TLS',
        plaintextMessage: 'This was sent over TLS.',
        htmlMessage: null,
        to: new ContactCollection(new Contact('recipient@example.com', 'Recipient'))
    );

    $result = ['sent' => true, 'message' => '', 'replyCode' => null];
}
catch (ExceptionFailedToSendEmail $exception)
{
    $result = ['sent' => false, 'message' => $exception->getMessage(), 'replyCode' => $exception->getSmtpReplyCode()];
}

echo json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
