<?php

/*
 * Smoke tests for the package's exceptions. These are run by testing/main.php, which provides $smoke. When each one is
 * thrown is tested in the files for the classes that throw them (e.g. FailuresTest.php).
 */

use Programster\Phpmailer\ExceptionFailedToSendEmail;
use Programster\Phpmailer\ExceptionFileDoesNotExist;
use Programster\Phpmailer\ExceptionFileIsNotReadable;
use Programster\Phpmailer\Testing\SmokeTest;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testMessageOnly = function () : void {
    $exception = new ExceptionFailedToSendEmail("It failed.");
    SmokeTest::assertSame("It failed.", $exception->getMessage(), "getMessage()");
    SmokeTest::assertSame(null, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame(null, $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame(false, $exception->isPermanentFailure(), "isPermanentFailure() with no reply");
    SmokeTest::assertSame(null, $exception->getPrevious(), "getPrevious()");
};

$testEverythingGiven = function () : void {
    $previous = new \RuntimeException("The cause.");
    $exception = new ExceptionFailedToSendEmail("It failed.", 550, "5.1.1", $previous);
    SmokeTest::assertSame(550, $exception->getSmtpReplyCode(), "getSmtpReplyCode()");
    SmokeTest::assertSame("5.1.1", $exception->getSmtpExtendedCode(), "getSmtpExtendedCode()");
    SmokeTest::assertSame($previous, $exception->getPrevious(), "getPrevious()");
    SmokeTest::assertSame(\Exception::class, get_parent_class($exception), "it extends \\Exception");
};

$testIsPermanentFailure = function () : void {
    $expectedByCode = [250 => false, 421 => false, 450 => false, 499 => false, 500 => true, 550 => true, 599 => true];

    foreach ($expectedByCode as $code => $expected)
    {
        $exception = new ExceptionFailedToSendEmail("It failed.", $code);
        SmokeTest::assertSame($expected, $exception->isPermanentFailure(), "isPermanentFailure() for {$code}");
    }

    $outOfRange = new ExceptionFailedToSendEmail("It failed.", 600);
    SmokeTest::assertSame(false, $outOfRange->isPermanentFailure(), "isPermanentFailure() for 600, which isn't 5xx");
};

$testFileExceptions = function () : void {
    $doesNotExist = new ExceptionFileDoesNotExist('/no/such/file.pdf');
    SmokeTest::assertSame('/no/such/file.pdf', $doesNotExist->getFilepath(), "ExceptionFileDoesNotExist's path");
    SmokeTest::assertSame("File does not exist: /no/such/file.pdf", $doesNotExist->getMessage(), "its message");

    $isNotReadable = new ExceptionFileIsNotReadable('/secret.pdf');
    SmokeTest::assertSame('/secret.pdf', $isNotReadable->getFilepath(), "ExceptionFileIsNotReadable::getFilepath()");
    SmokeTest::assertSame("Cannot read file: /secret.pdf", $isNotReadable->getMessage(), "its message");
};

$smoke->run("exceptions: a failed send with just a message has no reply code", $testMessageOnly);
$smoke->run("exceptions: a failed send holds its reply codes and previous exception", $testEverythingGiven);
$smoke->run("exceptions: only a 5xx reply is a permanent failure", $testIsPermanentFailure);
$smoke->run("exceptions: the file exceptions hold the file's path", $testFileExceptions);
