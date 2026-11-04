<?php

use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentCollection;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\EmbeddedImage;
use Programster\Phpmailer\EmbeddedImageCollection;
use Programster\Phpmailer\ExceptionFailedToSendEmail;
use Programster\Phpmailer\PhpMailerEmailer;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/Settings.php');

# Test that phpmailer works
# (you will need to log into your email account and check that you actually get the emails)
$mailer = new PhpMailerEmailer(
    smtpHost: SMTP_HOST,
    smtpUser: SMTP_USER,
    smtpPassword: SMTP_PASSWORD,
    securityProtocol: SMTP_SECURITY_PROTOCOL,
    from: SMTP_FROM,
    smtpPort: SMTP_PORT,
    replyTo: SMTP_REPLY_TO,
);


// send a plaintext email on its own.
$mailer->send(
    subject: 'Send Email Test - plaintext',
    plaintextMessage: "This is a plaintext email on its own",
    htmlMessage: null,
    to: EMAIL_TO,
    cc: EMAIL_CC,
    bcc: EMAIL_BCC,
);

echo "Sent plaintext email." . PHP_EOL;


// now send a more complicated HTML email with attachments.
$attachments = new AttachmentCollection(
    new Attachment(__DIR__ . '/sample.pdf', "example-pdf.pdf"),
    new Attachment(__DIR__ . '/sample.pdf', "second-pdf.pdf"),
);

$mailer->send(
    subject: 'Send Email Test - html with altbody and attachment',
    plaintextMessage: "This is the alt body",
    htmlMessage: "<h1>HTML Email</h1><p>This is an html email with an attachment</p>",
    to: EMAIL_TO,
    cc: EMAIL_CC,
    bcc: EMAIL_BCC,
    attachments: $attachments
);

echo "Sent HTML email with attachments." . PHP_EOL;


// send an HTML email with an embedded image, referenced by its cid.
$images = new EmbeddedImageCollection(
    new EmbeddedImage(__DIR__ . '/sample.png', "sample-image"),
);

$mailer->send(
    subject: 'Send Email Test - html with embedded image',
    plaintextMessage: "This is the alt body. The HTML version of this email contains an embedded image.",
    htmlMessage: "<h1>HTML Email</h1><p>There should be a gradient image below:</p><img src=\"cid:sample-image\" alt=\"sample image\">",
    to: EMAIL_TO,
    cc: EMAIL_CC,
    bcc: EMAIL_BCC,
    images: $images
);

echo "Sent HTML email with embedded image." . PHP_EOL;


// check that trying to send an email with no recipients throws an exception.
$threwException = false;

try
{
    $mailer->send(
        subject: 'Send Email Test - no recipients',
        plaintextMessage: "This email should never be sent.",
        htmlMessage: null,
        to: new ContactCollection(),
        cc: new ContactCollection(),
        bcc: new ContactCollection(),
    );
}
catch (ExceptionFailedToSendEmail $e)
{
    $threwException = true;
}

if ($threwException)
{
    echo "Sending with no recipients correctly threw an exception." . PHP_EOL;
}
else
{
    echo "FAILED: Sending with no recipients did not throw an exception." . PHP_EOL;
    exit(1);
}
