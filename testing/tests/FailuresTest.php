<?php

/*
 * Smoke tests for what send() throws when an email can't be sent: real refusals from a fake SMTP server, with the
 * reply code (e.g. 550) and enhanced status code (e.g. "5.1.1") they're refused with, an unreachable server, and an
 * email with no recipients. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\ExceptionFailedToSendEmail;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\SecurityProtocol;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpCommand;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpRefusal;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerMode;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

# Send an email to $to with $emailer, and get the exception that it throws.
$sendAndCatch = function (PhpMailerEmailer $emailer, ContactCollection $to) : ExceptionFailedToSendEmail {
    $send = function () use ($emailer, $to) : void {
        $emailer->send('It fails', 'Hello', null, $to);
    };

    return SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send);
};

$testPermanentRecipientRefusal = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createRecipientRefusal('nobody@example.com', '550 5.1.1 No such user');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $to = new ContactCollection(new Contact('nobody@example.com', 'Nobody'));
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), $to);

    SmokeTest::assertSame(550, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame('5.1.1', $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame(true, $exception->isPermanentFailure(), "isPermanentFailure()");
    SmokeTest::assertSame(true, str_contains($exception->getMessage(), 'nobody@example.com'), "the message says who");
    SmokeTest::assertSame(0, $server->fetchOnlySession()->getNumMessages(), "nothing was sent");
};

$testTemporaryRecipientRefusal = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createRecipientRefusal('busy@example.com', '450 4.2.1 Mailbox busy, try later');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $to = new ContactCollection(new Contact('busy@example.com', 'Busy'));
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), $to);

    SmokeTest::assertSame(450, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame('4.2.1', $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame(false, $exception->isPermanentFailure(), "isPermanentFailure()");
};

$testRefusalWithoutExtendedCode = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createCommandRefusal(FakeSmtpCommand::MAIL_FROM, '421 Too busy, try later');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), TestFixtures::createAnn());

    SmokeTest::assertSame(421, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame(null, $exception->getSmtpExtendedCode(), "getSmtpExtendedCode(), which wasn't sent");
    SmokeTest::assertSame(false, $exception->isPermanentFailure(), "isPermanentFailure()");
};

$testSenderRefusal = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createCommandRefusal(FakeSmtpCommand::MAIL_FROM, '553 5.7.1 Sender not allowed');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), TestFixtures::createAnn());

    SmokeTest::assertSame(553, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame(true, $exception->isPermanentFailure(), "isPermanentFailure()");
    SmokeTest::assertSame([], $server->fetchOnlySession()->getRecipients(), "no recipients were sent");
};

$testMessageRefusal = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createCommandRefusal(FakeSmtpCommand::DATA, '554 5.7.1 Rejected as spam');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), TestFixtures::createAnn());

    SmokeTest::assertSame(554, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame('5.7.1', $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame(true, $exception->isPermanentFailure(), "isPermanentFailure()");
};

$testLoginRefusal = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createCommandRefusal(FakeSmtpCommand::AUTH, '535 5.7.8 Credentials invalid');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), TestFixtures::createAnn());

    SmokeTest::assertSame(535, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame('5.7.8', $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame(true, $exception->isPermanentFailure(), "isPermanentFailure()");
    $verbs = $server->fetchOnlySession()->getVerbsBeforeTls();
    SmokeTest::assertSame(false, in_array('MAIL', $verbs, true), "nothing was sent after the login was refused");
};

$testStartTlsRefusal = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createCommandRefusal(FakeSmtpCommand::STARTTLS, '454 4.7.0 TLS not available');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::STARTTLS, $refusal);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::TLS);
    $exception = $sendAndCatch($emailer, TestFixtures::createAnn());

    SmokeTest::assertSame(454, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame(false, $exception->isPermanentFailure(), "isPermanentFailure()");
};

$testSomeRecipientsRefused = function () use ($sendAndCatch) : void {
    $refusal = FakeSmtpRefusal::createRecipientRefusal('nobody@example.com', '550 5.1.1 No such user');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $to = new ContactCollection(new Contact('ann@example.com', 'Ann'), new Contact('nobody@example.com', 'Nobody'));
    $exception = $sendAndCatch(TestFixtures::createEmailer($server), $to);

    # PHPMailer still sends the email to the recipients the server accepted, but send() still throws, so retrying it
    # would send it to them again. See docs/failures.md.
    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(550, $exception->getSmtpReplyCode(), "the refused recipient's reply code");
    SmokeTest::assertSame(true, $exception->isPermanentFailure(), "isPermanentFailure()");
    SmokeTest::assertSame(1, $session->getNumMessages(), "the email was still sent, to the recipient who was accepted");
    $toHeader = $session->getOnlyMessage()->getHeader('To');
    SmokeTest::assertSame('Ann <ann@example.com>, Nobody <nobody@example.com>', $toHeader, "To");
};

$testUnreachableServer = function () : void {
    $emailer = new PhpMailerEmailer(
        smtpHost: '127.0.0.1',
        smtpUser: 'user',
        smtpPassword: 'password',
        securityProtocol: SecurityProtocol::NONE,
        from: new Contact('sender@example.com', null),
        smtpPort: TestFixtures::findUnusedPort()
    );

    $send = function () use ($emailer) : void {
        $emailer->send('Unreachable', 'Hello', null, TestFixtures::createAnn());
    };

    $exception = SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send, 'SMTP connect() failed');
    SmokeTest::assertSame(null, $exception->getSmtpReplyCode(), "getSmtpReplyCode(), as nothing replied");
    SmokeTest::assertSame(null, $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame(false, $exception->isPermanentFailure(), "isPermanentFailure(), as it may work later");
};

$testNoRecipients = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $empty = new ContactCollection();

    $sendToNull = function () use ($emailer) : void {
        $emailer->send('Nobody', 'Hello', null, null);
    };

    $sendToEmpty = function () use ($emailer, $empty) : void {
        $emailer->send('Nobody', 'Hello', null, $empty, $empty, $empty);
    };

    foreach ([$sendToNull, $sendToEmpty] as $send)
    {
        $exception = SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send, 'no configured recipients');
        SmokeTest::assertSame(null, $exception->getSmtpReplyCode(), "getSmtpReplyCode(), as nothing was sent");
        SmokeTest::assertSame(false, $exception->isPermanentFailure(), "isPermanentFailure()");
    }

    SmokeTest::assertSame(0, count($server->fetchSessions()), "it didn't connect to the server");
};

$smoke->run("failures: a permanently refused recipient (550) is a permanent failure", $testPermanentRecipientRefusal);
$smoke->run("failures: a temporarily refused recipient (450) isn't permanent", $testTemporaryRecipientRefusal);
$smoke->run("failures: a refusal without an enhanced status code", $testRefusalWithoutExtendedCode);
$smoke->run("failures: a refused sender (553) is a permanent failure", $testSenderRefusal);
$smoke->run("failures: a refused message (554 after DATA) is a permanent failure", $testMessageRefusal);
$smoke->run("failures: a refused login (535) is a permanent failure", $testLoginRefusal);
$smoke->run("failures: a temporarily refused STARTTLS (454) isn't permanent", $testStartTlsRefusal);
$smoke->run("failures: one refused recipient fails the send, though the others got it", $testSomeRecipientsRefused);
$smoke->run("failures: an unreachable server has no reply code, and isn't permanent", $testUnreachableServer);
$smoke->run("failures: an email with no recipients is refused, without connecting", $testNoRecipients);
