<?php

/*
 * Smoke tests for what each SecurityProtocol does, against a fake SMTP server that offers STARTTLS, uses TLS from the
 * start, or doesn't offer TLS at all: that TLS and SSL encrypt everything before logging in, and never fall back to
 * plain text, that they refuse a certificate they can't verify, and that NONE never upgrades to TLS. These are run by
 * testing/main.php, which provides $smoke.
 *
 * The emails sent with a certificate the client trusts are sent in a child process (see ChildProcessSendResult), as
 * the only way to trust it is PHP's openssl.cafile setting, which can't be changed once PHP has started.
 */

use Programster\Phpmailer\ExceptionFailedToSendEmail;
use Programster\Phpmailer\SecurityProtocol;
use Programster\Phpmailer\Testing\FakeSmtp\ChildProcessSendResult;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerMode;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpSession;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

# Check nothing that should be encrypted (the login, the envelope or the message) was sent in plain text.
$assertNothingSentInPlainText = function (FakeSmtpSession $session) : void {
    $verbs = $session->getVerbsBeforeTls();
    SmokeTest::assertSame(false, in_array('AUTH', $verbs, true), "it didn't log in over plain text");
    SmokeTest::assertSame(false, in_array('MAIL', $verbs, true), "it didn't send the envelope over plain text");
    SmokeTest::assertSame('', $session->getAuthPassword(), "the password wasn't sent");
    SmokeTest::assertSame(0, $session->getNumMessages(), "the message wasn't sent");
};

$testNoneNeverUpgrades = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::STARTTLS);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::NONE);
    $emailer->send('None', 'Hello', null, TestFixtures::createAnn());

    $session = $server->fetchOnlySession();
    $expectedVerbs = ['EHLO', 'AUTH', 'MAIL', 'RCPT', 'DATA', 'QUIT'];
    SmokeTest::assertSame($expectedVerbs, $session->getVerbsBeforeTls(), "no STARTTLS, though it's offered");
    SmokeTest::assertSame(false, $session->usedTls(), "TLS wasn't used");
    SmokeTest::assertSame(TestFixtures::SMTP_PASSWORD, $session->getAuthPassword(), "it still logged in");
    SmokeTest::assertSame(1, $session->getNumMessages(), "the message was sent");
};

$testTlsWithTrustedCertificate = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::STARTTLS);
    $result = ChildProcessSendResult::createBySendingTrustingCertificate($server, SecurityProtocol::TLS);

    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(true, $result->wasSent(), "it was sent: {$result->getMessage()}");
    $expectedVerbsOverTls = ['EHLO', 'AUTH', 'MAIL', 'RCPT', 'DATA', 'QUIT'];
    SmokeTest::assertSame(['EHLO', 'STARTTLS'], $session->getVerbsBeforeTls(), "only EHLO and STARTTLS in plain text");
    SmokeTest::assertSame($expectedVerbsOverTls, $session->getVerbsOverTls(), "the rest is over TLS");
    SmokeTest::assertSame('child-password', $session->getAuthPassword(), "it logged in, over TLS");
    SmokeTest::assertSame(1, $session->getNumMessages(), "the message was sent, over TLS");
};

$testTlsWithUntrustedCertificate = function () use ($assertNothingSentInPlainText) : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::STARTTLS);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::TLS);

    $send = function () use ($emailer) : void {
        $emailer->send('TLS', 'Hello', null, TestFixtures::createAnn());
    };

    $exception = SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send, 'SMTP connect() failed');
    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(true, $session->tlsHandshakeFailed(), "the client refused the certificate");
    SmokeTest::assertSame(null, $exception->getSmtpReplyCode(), "there's no reply code, as the server refused nothing");
    $assertNothingSentInPlainText($session);
};

$testTlsWhenNotOffered = function () use ($assertNothingSentInPlainText) : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::TLS);

    $send = function () use ($emailer) : void {
        $emailer->send('TLS', 'Hello', null, TestFixtures::createAnn());
    };

    $exception = SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send);
    SmokeTest::assertSame(502, $exception->getSmtpReplyCode(), "the server's refusal of STARTTLS");
    SmokeTest::assertSame(true, $exception->isPermanentFailure(), "it's permanent");
    $assertNothingSentInPlainText($server->fetchOnlySession());
};

$testSslWithTrustedCertificate = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::IMPLICIT_TLS);
    $result = ChildProcessSendResult::createBySendingTrustingCertificate($server, SecurityProtocol::SSL);

    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(true, $result->wasSent(), "it was sent: {$result->getMessage()}");
    SmokeTest::assertSame([], $session->getCommandsBeforeTls(), "nothing is sent in plain text");
    SmokeTest::assertSame(true, $session->usedTls(), "TLS was used from the start");
    SmokeTest::assertSame('child-password', $session->getAuthPassword(), "it logged in, over TLS");
    SmokeTest::assertSame(1, $session->getNumMessages(), "the message was sent, over TLS");
};

$testSslWithUntrustedCertificate = function () use ($assertNothingSentInPlainText) : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::IMPLICIT_TLS);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::SSL);

    $send = function () use ($emailer) : void {
        $emailer->send('SSL', 'Hello', null, TestFixtures::createAnn());
    };

    $exception = SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send, 'SMTP connect() failed');
    $session = $server->fetchOnlySession();
    SmokeTest::assertSame(true, $session->tlsHandshakeFailed(), "the client refused the certificate");
    SmokeTest::assertSame(null, $exception->getSmtpReplyCode(), "there's no reply code");
    $assertNothingSentInPlainText($session);
};

$testSslToPlainServer = function () use ($assertNothingSentInPlainText) : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::STARTTLS);
    $emailer = TestFixtures::createEmailer($server, SecurityProtocol::SSL);

    $send = function () use ($emailer) : void {
        $emailer->send('SSL', 'Hello', null, TestFixtures::createAnn());
    };

    SmokeTest::expectException(ExceptionFailedToSendEmail::class, $send);
    $assertNothingSentInPlainText($server->fetchOnlySession());
};

$smoke->run("security: NONE never upgrades to TLS, even when the server offers it", $testNoneNeverUpgrades);
$smoke->run("security: TLS sends everything after STARTTLS, with a trusted cert", $testTlsWithTrustedCertificate);
$smoke->run("security: TLS refuses a certificate it can't verify, before logging in", $testTlsWithUntrustedCertificate);
$smoke->run("security: TLS doesn't fall back to plain text when STARTTLS isn't offered", $testTlsWhenNotOffered);
$smoke->run("security: SSL sends everything over TLS, with a certificate it trusts", $testSslWithTrustedCertificate);
$smoke->run("security: SSL refuses a certificate it can't verify, before logging in", $testSslWithUntrustedCertificate);
$smoke->run("security: SSL to a server that doesn't use TLS from the start sends nothing", $testSslToPlainServer);
