<?php

/*
 * Smoke tests for PhpMailerEmailer::send(), checking exactly what it sends to a fake SMTP server: the envelope (who
 * it's from, and every recipient, including BCC ones), the login, the headers, the plain and HTML bodies, the
 * attachments and embedded images, and the character set. These are run by testing/main.php, which provides $smoke.
 * The security protocols are tested in SecurityTest.php, and failures in FailuresTest.php.
 */

use PHPMailer\PHPMailer\PHPMailer;
use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentCollection;
use Programster\Phpmailer\AttachmentEncoding;
use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\EmbeddedImage;
use Programster\Phpmailer\EmbeddedImageCollection;
use Programster\Phpmailer\SecurityProtocol;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerMode;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testPlaintext = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $emailer->send('Plain subject', "Line one\nLine two", null, TestFixtures::createAnn());

    $session = $server->fetchOnlySession();
    $message = $session->getOnlyMessage();
    SmokeTest::assertSame(TestFixtures::SMTP_USER, $session->getAuthUsername(), "the username it logged in with");
    SmokeTest::assertSame(TestFixtures::SMTP_PASSWORD, $session->getAuthPassword(), "the password it logged in with");
    SmokeTest::assertSame(TestFixtures::FROM_EMAIL, $session->getMailFrom(), "MAIL FROM");
    SmokeTest::assertSame(['ann@example.com'], $session->getRecipients(), "RCPT TO");
    SmokeTest::assertSame('Plain subject', $message->getHeader('Subject'), "Subject");
    SmokeTest::assertSame('Sender Name <sender@example.com>', $message->getHeader('From'), "From");
    SmokeTest::assertSame('Sender Name <sender@example.com>', $message->getHeader('Reply-To'), "Reply-To is From");
    SmokeTest::assertSame('Ann <ann@example.com>', $message->getHeader('To'), "To");
    SmokeTest::assertSame(false, $message->hasHeader('Cc'), "there's no Cc");
    SmokeTest::assertSame('text/plain', $message->getContentType(), "a plaintext email isn't multipart");
    SmokeTest::assertSame('utf-8', $message->getContentTypeParameter('charset'), "the charset defaults to UTF-8");
    SmokeTest::assertSame("Line one\r\nLine two", $message->getDecodedText(), "the body");
};

$testHtmlWithAlternative = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $html = '<h1>Hello</h1><p>An <b>HTML</b> email.</p>';
    $emailer->send('HTML subject', 'An <b>HTML</b> email.', $html, TestFixtures::createAnn());

    $message = $server->fetchOnlySession()->getOnlyMessage();
    $plainPart = $message->findOnlyPartOfType('text/plain');
    $htmlPart = $message->findOnlyPartOfType('text/html');
    SmokeTest::assertSame('multipart/alternative', $message->getContentType(), "the message's type");
    SmokeTest::assertSame($html, $htmlPart->getDecodedText(), "the HTML body");
    SmokeTest::assertSame('An HTML email.', $plainPart->getDecodedText(), "the plain body, with any tags stripped");
    # PHPMailer labels a part that's only ASCII as us-ascii (which UTF-8 is a superset of). See $testUtf8 for UTF-8.
    SmokeTest::assertSame('us-ascii', $htmlPart->getContentTypeParameter('charset'), "the HTML part's charset");
    SmokeTest::assertSame('us-ascii', $plainPart->getContentTypeParameter('charset'), "the plain part's charset");
};

$testRecipients = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $to = new ContactCollection(new Contact('ann@example.com', 'Ann'), new Contact('nameless@example.com', null));
    $cc = new ContactCollection(new Contact('carl@example.com', 'Carl'));
    $bcc = new ContactCollection(new Contact('hidden@example.com', 'Hidden'));
    $emailer->send('Recipients', 'Hello', null, $to, $cc, $bcc);

    $session = $server->fetchOnlySession();
    $message = $session->getOnlyMessage();
    $allRecipients = ['ann@example.com', 'nameless@example.com', 'carl@example.com', 'hidden@example.com'];
    SmokeTest::assertSame($allRecipients, $session->getRecipients(), "RCPT TO, for To, CC and BCC");
    SmokeTest::assertSame('Ann <ann@example.com>, nameless@example.com', $message->getHeader('To'), "To");
    SmokeTest::assertSame('Carl <carl@example.com>', $message->getHeader('Cc'), "Cc");
    SmokeTest::assertSame(false, $message->hasHeader('Bcc'), "there's no Bcc header, which would reveal them");
    $rawMessage = $session->getOnlyRawMessage();
    SmokeTest::assertSame(false, str_contains($rawMessage, 'hidden@example.com'), "the BCC isn't in the message");
};

$testOnlyBcc = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $bcc = new ContactCollection(new Contact('hidden@example.com', 'Hidden'));
    $emailer->send('Only BCC', 'Hello', null, null, null, $bcc);

    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(['hidden@example.com'], $session->getRecipients(), "RCPT TO");
    SmokeTest::assertSame('undisclosed-recipients:;', $session->getOnlyMessage()->getHeader('To'), "To");
};

$testOnlyCc = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $emailer->send('Only CC', 'Hello', null, new ContactCollection(), TestFixtures::createAnn());

    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(['ann@example.com'], $session->getRecipients(), "RCPT TO");
    SmokeTest::assertSame('Ann <ann@example.com>', $session->getOnlyMessage()->getHeader('Cc'), "Cc");
};

$testReplyTo = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $replyTo = new Contact('replies@example.com', 'Replies');
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::NONE, $replyTo);
    $emailer->send('Reply-To', 'Hello', null, TestFixtures::createAnn());

    $message = $server->fetchOnlySession()->getOnlyMessage();
    SmokeTest::assertSame('Replies <replies@example.com>', $message->getHeader('Reply-To'), "Reply-To");
    SmokeTest::assertSame('Sender Name <sender@example.com>', $message->getHeader('From'), "From is unchanged");
};

$testAttachments = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);

    $attachments = new AttachmentCollection(
        new Attachment(TestFixtures::SAMPLE_PDF, 'invoice.pdf'),
        new Attachment(TestFixtures::SAMPLE_PNG, null, AttachmentEncoding::BASE_64, 'image/x-test'),
        new Attachment(TestFixtures::NOTES_TXT, 'notes.txt', AttachmentEncoding::QUOTED_PRINTABLE),
    );

    $to = TestFixtures::createAnn();
    $emailer->send('Attachments', 'See attached.', '<p>See attached.</p>', $to, null, null, $attachments);

    $message = $server->fetchOnlySession()->getOnlyMessage();
    $pdf = $message->findPartWithFilename('invoice.pdf');
    $png = $message->findPartWithFilename('sample.png');
    $notes = $message->findPartWithFilename('notes.txt');
    SmokeTest::assertSame('multipart/mixed', $message->getContentType(), "the message's type");
    $plainBody = $message->findOnlyPartOfType('multipart/alternative')->findOnlyPartOfType('text/plain');
    SmokeTest::assertSame('See attached.', $plainBody->getDecodedText(), "the plain body");
    SmokeTest::assertSame('application/pdf', $pdf->getContentType(), "the PDF's type, from its extension");
    SmokeTest::assertSame('base64', $pdf->getHeader('Content-Transfer-Encoding'), "the PDF's encoding");
    SmokeTest::assertSame(true, str_starts_with($pdf->getHeader('Content-Disposition'), 'attachment'), "it's attached");
    SmokeTest::assertSame(TestFixtures::readFile(TestFixtures::SAMPLE_PDF), $pdf->getDecodedBody(), "the PDF's bytes");
    SmokeTest::assertSame('image/x-test', $png->getContentType(), "the mimetype given, rather than the extension's");
    SmokeTest::assertSame(TestFixtures::readFile(TestFixtures::SAMPLE_PNG), $png->getDecodedBody(), "the PNG's bytes");
    SmokeTest::assertSame('quoted-printable', $notes->getHeader('Content-Transfer-Encoding'), "the encoding given");
    SmokeTest::assertSame('text/plain', $notes->getContentType(), "the text file's type");
    SmokeTest::assertSame(TestFixtures::readFile(TestFixtures::NOTES_TXT), $notes->getDecodedBody(), "the notes' text");
};

$testEmbeddedImages = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);

    $images = new EmbeddedImageCollection(
        new EmbeddedImage(TestFixtures::SAMPLE_PNG, 'logo'),
        new EmbeddedImage(TestFixtures::SAMPLE_PNG),
    );

    $html = '<p>Logos:</p><img src="cid:logo"><img src="cid:sample.png">';
    $emailer->send('Images', 'Logos', $html, TestFixtures::createAnn(), null, null, null, $images);

    $message = $server->fetchOnlySession()->getOnlyMessage();
    $logo = $message->findPartWithContentId('logo');
    $sample = $message->findPartWithContentId('sample.png');
    $pngBytes = TestFixtures::readFile(TestFixtures::SAMPLE_PNG);
    $numRelatedParts = count($message->findPartsOfType('multipart/related'));
    SmokeTest::assertSame(1, $numRelatedParts, "the images are related to the HTML");
    SmokeTest::assertSame($html, $message->findOnlyPartOfType('text/html')->getDecodedText(), "the HTML body");
    SmokeTest::assertSame('image/png', $logo->getContentType(), "the image's type");
    SmokeTest::assertSame(true, str_starts_with($logo->getHeader('Content-Disposition'), 'inline'), "it's inline");
    SmokeTest::assertSame($pngBytes, $logo->getDecodedBody(), "the image's bytes");
    SmokeTest::assertSame($pngBytes, $sample->getDecodedBody(), "the image with the default cid");
};

$testAttachmentsAndImagesTogether = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $attachments = new AttachmentCollection(new Attachment(TestFixtures::SAMPLE_PDF));
    $images = new EmbeddedImageCollection(new EmbeddedImage(TestFixtures::SAMPLE_PNG, 'logo'));
    $html = '<img src="cid:logo">';
    $emailer->send('Both', 'Both', $html, TestFixtures::createAnn(), null, null, $attachments, $images);

    $message = $server->fetchOnlySession()->getOnlyMessage();
    $pdfBytes = TestFixtures::readFile(TestFixtures::SAMPLE_PDF);
    $pngBytes = TestFixtures::readFile(TestFixtures::SAMPLE_PNG);
    SmokeTest::assertSame('multipart/mixed', $message->getContentType(), "the message's type");
    SmokeTest::assertSame($pdfBytes, $message->findPartWithFilename('sample.pdf')->getDecodedBody(), "the attachment");
    SmokeTest::assertSame($pngBytes, $message->findPartWithContentId('logo')->getDecodedBody(), "the image");
};

$testUtf8 = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $subject = 'Café — ünïcödé ✓';
    $to = new ContactCollection(new Contact('zoe@example.com', 'Zoë'));
    $emailer->send($subject, 'Naïve café ✓', '<p>Naïve café ✓</p>', $to);

    $message = $server->fetchOnlySession()->getOnlyMessage();
    SmokeTest::assertSame(true, str_starts_with($message->getHeader('Subject'), '=?utf-8?'), "the subject is encoded");
    SmokeTest::assertSame($subject, $message->getDecodedHeader('Subject'), "the subject, decoded");
    SmokeTest::assertSame('Zoë <zoe@example.com>', $message->getDecodedHeader('To'), "the recipient's name, decoded");
    $plainPart = $message->findOnlyPartOfType('text/plain');
    $htmlPart = $message->findOnlyPartOfType('text/html');
    SmokeTest::assertSame('Naïve café ✓', $plainPart->getDecodedText(), "the plain body");
    SmokeTest::assertSame('<p>Naïve café ✓</p>', $htmlPart->getDecodedText(), "the HTML body");
    SmokeTest::assertSame('utf-8', $plainPart->getContentTypeParameter('charset'), "the plain part's charset");
    SmokeTest::assertSame('utf-8', $htmlPart->getContentTypeParameter('charset'), "the HTML part's charset");
};

$testOtherCharset = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::NONE, null, PHPMailer::CHARSET_ISO88591);
    $latin1Body = (string) mb_convert_encoding('Café', 'ISO-8859-1', 'UTF-8');
    $latin1Subject = (string) mb_convert_encoding('Café', 'ISO-8859-1', 'UTF-8');
    $emailer->send($latin1Subject, $latin1Body, null, TestFixtures::createAnn());

    $message = $server->fetchOnlySession()->getOnlyMessage();
    SmokeTest::assertSame('iso-8859-1', $message->getContentTypeParameter('charset'), "the charset given");
    SmokeTest::assertSame($latin1Body, $message->getDecodedText(), "the body's bytes, unchanged");
    SmokeTest::assertSame('Café', $message->getDecodedHeader('Subject'), "the subject, decoded from ISO-8859-1");
};

$testReuse = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $attachments = new AttachmentCollection(new Attachment(TestFixtures::SAMPLE_PDF));
    $emailer->send('First', 'First', null, TestFixtures::createAnn(), null, null, $attachments);
    $emailer->send('Second', 'Second', null, new ContactCollection(new Contact('bob@example.com', 'Bob')));

    $sessions = $server->fetchSessions();
    $second = $sessions->last();
    SmokeTest::assertSame(2, count($sessions), "each email is sent in its own session");
    SmokeTest::assertSame(['bob@example.com'], $second->getRecipients(), "the first email's recipients aren't kept");
    SmokeTest::assertSame('Second', $second->getOnlyMessage()->getHeader('Subject'), "the second subject");
    SmokeTest::assertSame('text/plain', $second->getOnlyMessage()->getContentType(), "the attachment isn't kept");
};

$smoke->run("sending: a plaintext email", $testPlaintext);
$smoke->run("sending: an HTML email has the plain message as its alternative", $testHtmlWithAlternative);
$smoke->run("sending: to, CC and BCC recipients, with BCC ones hidden", $testRecipients);
$smoke->run("sending: to BCC recipients only", $testOnlyBcc);
$smoke->run("sending: to CC recipients only, with an empty to", $testOnlyCc);
$smoke->run("sending: a reply-to address other than the from address", $testReplyTo);
$smoke->run("sending: attachments, with their names, types, encodings and bytes", $testAttachments);
$smoke->run("sending: embedded images, referenced by their cid", $testEmbeddedImages);
$smoke->run("sending: attachments and embedded images together", $testAttachmentsAndImagesTogether);
$smoke->run("sending: UTF-8 in the subject, names and bodies", $testUtf8);
$smoke->run("sending: another character set", $testOtherCharset);
$smoke->run("sending: an emailer can send several emails, which don't share anything", $testReuse);
