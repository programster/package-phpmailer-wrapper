<?php

/*
 * The opt-in live test, which sends real emails through a real SMTP server, set in testing/settings.php (see
 * settings.php.tmpl), to check the package works with a real server, not just the fake one the other tests use. It
 * only runs locally, by hand: it isn't run by main.php, run-all-php-versions.sh or the GitHub workflow, and refuses to
 * run in CI, or until settings.php confirms the account is one for testing. Check your mailbox for the emails after.
 * Run with: php -d zend.assertions=1 testing/live-test.php
 */

use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentCollection;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\EmbeddedImage;
use Programster\Phpmailer\EmbeddedImageCollection;
use Programster\Phpmailer\ExceptionFailedToSendEmail;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\Testing\LiveTestSettings;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

require_once(__DIR__ . '/../vendor/autoload.php');

$settingsPath = __DIR__ . '/settings.php';

if (getenv('CI') !== false || getenv('GITHUB_ACTIONS') !== false)
{
    echo "The live test sends real emails, so it only runs locally, by hand, not in CI.\n";
    exit(1);
}

if (ini_get('zend.assertions') !== '1' && @ini_set('zend.assertions', '1') === false)
{
    echo "zend.assertions must be 1 for the tests to check anything. Run: php -d zend.assertions=1 " . __FILE__ . "\n";
    exit(1);
}

if (!is_file($settingsPath))
{
    echo "The live test needs its settings: copy testing/settings.php.tmpl to testing/settings.php, and fill it in.\n";
    exit(1);
}

ini_set('assert.exception', '1');

try
{
    $settings = require $settingsPath;
}
catch (\LogicException $exception)
{
    echo "Refusing to run the live test: {$exception->getMessage()}\n";
    exit(1);
}

if (!($settings instanceof LiveTestSettings))
{
    echo "testing/settings.php must return a LiveTestSettings. See testing/settings.php.tmpl.\n";
    exit(1);
}

$smoke = new SmokeTest();

$mailer = new PhpMailerEmailer(
    smtpHost: $settings->getSmtpHost(),
    smtpUser: $settings->getSmtpUser(),
    smtpPassword: $settings->getSmtpPassword(),
    securityProtocol: $settings->getSecurityProtocol(),
    from: $settings->getFrom(),
    smtpPort: $settings->getSmtpPort(),
    replyTo: $settings->getReplyTo(),
);

$sendPlaintext = function () use ($mailer, $settings) : void {
    $mailer->send(
        subject: 'Send Email Test - plaintext',
        plaintextMessage: "This is a plaintext email on its own",
        htmlMessage: null,
        to: $settings->getTo(),
        cc: $settings->getCc(),
        bcc: $settings->getBcc(),
    );
};

$sendHtmlWithAttachments = function () use ($mailer, $settings) : void {
    $attachments = new AttachmentCollection(
        new Attachment(TestFixtures::SAMPLE_PDF, "example-pdf.pdf"),
        new Attachment(TestFixtures::SAMPLE_PDF, "second-pdf.pdf"),
    );

    $mailer->send(
        subject: 'Send Email Test - html with altbody and attachment',
        plaintextMessage: "This is the alt body",
        htmlMessage: "<h1>HTML Email</h1><p>This is an html email with an attachment</p>",
        to: $settings->getTo(),
        cc: $settings->getCc(),
        bcc: $settings->getBcc(),
        attachments: $attachments
    );
};

$sendHtmlWithImage = function () use ($mailer, $settings) : void {
    $images = new EmbeddedImageCollection(new EmbeddedImage(TestFixtures::SAMPLE_PNG, "sample-image"));
    $html = '<h1>HTML Email</h1><p>There should be a gradient image below:</p><img src="cid:sample-image" alt="">';

    $mailer->send(
        subject: 'Send Email Test - html with embedded image',
        plaintextMessage: "This is the alt body. The HTML version of this email contains an embedded image.",
        htmlMessage: $html,
        to: $settings->getTo(),
        cc: $settings->getCc(),
        bcc: $settings->getBcc(),
        images: $images
    );
};

$sendToNobody = function () use ($mailer) : void {
    $sendWithNoRecipients = function () use ($mailer) : void {
        $mailer->send(
            subject: 'Send Email Test - no recipients',
            plaintextMessage: "This email should never be sent.",
            htmlMessage: null,
            to: new ContactCollection(),
            cc: new ContactCollection(),
            bcc: new ContactCollection(),
        );
    };

    SmokeTest::expectException(ExceptionFailedToSendEmail::class, $sendWithNoRecipients);
};

$smoke->run("live: send a plaintext email", $sendPlaintext);
$smoke->run("live: send an HTML email with attachments", $sendHtmlWithAttachments);
$smoke->run("live: send an HTML email with an embedded image", $sendHtmlWithImage);
$smoke->run("live: an email with no recipients is refused", $sendToNobody);

$failures = $smoke->getFailures();
echo "Live test: {$smoke->getNumPassed()} passed, " . count($failures) . " failed. Check your mailbox for 3 emails.\n";

foreach ($failures as $failure)
{
    echo "  FAIL {$failure}\n";
}

exit(count($failures) === 0 ? 0 : 1);
