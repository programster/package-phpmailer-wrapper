<?php

/*
 * Smoke tests for ReplyRecordingSmtp, used directly against a fake SMTP server: that it keeps the reply to the last
 * command the server refused, even after later commands work (which PHPMailer's own error doesn't). These are run by
 * testing/main.php, which provides $smoke. How send() uses it is tested in FailuresTest.php.
 */

use Programster\Phpmailer\ReplyRecordingSmtp;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpCommand;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpRefusal;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerMode;
use Programster\Phpmailer\Testing\SmokeTest;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testStartsWithNoRefusal = function () : void {
    $smtp = new ReplyRecordingSmtp();
    SmokeTest::assertSame(null, $smtp->getLastRefusalCode(), "getLastRefusalCode()");
    SmokeTest::assertSame(null, $smtp->getLastRefusalExtendedCode(), "getLastRefusalExtendedCode()");
};

$testKeepsRefusal = function () : void {
    $refusal = FakeSmtpRefusal::createCommandRefusal(FakeSmtpCommand::MAIL_FROM, '553 5.1.8 Bad sender domain');
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS, $refusal);
    $smtp = new ReplyRecordingSmtp();
    assert($smtp->connect('127.0.0.1', $server->getPort()), "it connected");
    assert($smtp->hello('test.example.com'), "EHLO worked");
    SmokeTest::assertSame(null, $smtp->getLastRefusalCode(), "nothing was refused yet");
    SmokeTest::assertSame(false, $smtp->mail('sender@bad.example'), "MAIL FROM was refused");
    SmokeTest::assertSame(553, $smtp->getLastRefusalCode(), "getLastRefusalCode()");
    SmokeTest::assertSame('5.1.8', $smtp->getLastRefusalExtendedCode(), "getLastRefusalExtendedCode()");
    assert($smtp->reset(), "RSET worked");
    $smtp->quit();
    $smtp->close();
    SmokeTest::assertSame('', $smtp->getError()['smtp_code'], "PHPMailer's own error has been cleared");
    SmokeTest::assertSame(553, $smtp->getLastRefusalCode(), "the refusal is kept after later commands work");
};

$testLaterRefusalReplacesEarlier = function () : void {
    $server = FakeSmtpServer::create(
        FakeSmtpServerMode::NO_TLS,
        FakeSmtpRefusal::createRecipientRefusal('nobody@example.com', '550 5.1.1 No such user'),
        FakeSmtpRefusal::createRecipientRefusal('busy@example.com', '450 Busy')
    );

    $smtp = new ReplyRecordingSmtp();
    assert($smtp->connect('127.0.0.1', $server->getPort()) && $smtp->hello('test.example.com'), "it connected");
    assert($smtp->mail('sender@example.com'), "MAIL FROM worked");
    SmokeTest::assertSame(false, $smtp->recipient('nobody@example.com'), "the first recipient was refused");
    SmokeTest::assertSame(550, $smtp->getLastRefusalCode(), "the first refusal");
    SmokeTest::assertSame(false, $smtp->recipient('busy@example.com'), "the second recipient was refused");
    SmokeTest::assertSame(450, $smtp->getLastRefusalCode(), "the second refusal replaces it");
    SmokeTest::assertSame(null, $smtp->getLastRefusalExtendedCode(), "the second had no enhanced status code");
    $smtp->quit();
    $smtp->close();
};

$testCommandWithoutConnection = function () : void {
    $smtp = new ReplyRecordingSmtp();
    SmokeTest::assertSame(false, $smtp->mail('sender@example.com'), "MAIL FROM fails, as it isn't connected");
    SmokeTest::assertSame(null, $smtp->getLastRefusalCode(), "it isn't a refusal, as no server replied");
};

$smoke->run("reply recording SMTP: starts with no refusal", $testStartsWithNoRefusal);
$smoke->run("reply recording SMTP: keeps the reply to a refused command", $testKeepsRefusal);
$smoke->run("reply recording SMTP: a later refusal replaces an earlier one", $testLaterRefusalReplacesEarlier);
$smoke->run("reply recording SMTP: a command that never reached a server isn't refused", $testCommandWithoutConnection);
