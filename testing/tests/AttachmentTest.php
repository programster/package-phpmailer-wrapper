<?php

/*
 * Smoke tests for Attachment, including that a file that can't be attached is rejected when the attachment is created,
 * rather than when the email is sent. These are run by testing/main.php, which provides $smoke. What's sent for an
 * attachment is tested in SendingTest.php.
 */

use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentEncoding;
use Programster\Phpmailer\ExceptionFileDoesNotExist;
use Programster\Phpmailer\ExceptionFileIsNotReadable;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testDefaults = function () : void {
    $attachment = new Attachment(TestFixtures::SAMPLE_PDF);
    SmokeTest::assertSame(TestFixtures::SAMPLE_PDF, $attachment->getFilepath(), "getFilepath()");
    SmokeTest::assertSame('sample.pdf', $attachment->getName(), "getName() defaults to the file's name");
    SmokeTest::assertSame(AttachmentEncoding::BASE_64, $attachment->getEncoding(), "getEncoding() defaults to base64");
    SmokeTest::assertSame('', $attachment->getMimetype(), "getMimetype() is empty, to be worked out when it's sent");
};

$testEverythingGiven = function () : void {
    $encoding = AttachmentEncoding::QUOTED_PRINTABLE;
    $attachment = new Attachment(TestFixtures::NOTES_TXT, 'my-notes.txt', $encoding, 'text/plain');
    SmokeTest::assertSame(TestFixtures::NOTES_TXT, $attachment->getFilepath(), "getFilepath()");
    SmokeTest::assertSame('my-notes.txt', $attachment->getName(), "getName()");
    SmokeTest::assertSame(AttachmentEncoding::QUOTED_PRINTABLE, $attachment->getEncoding(), "getEncoding()");
    SmokeTest::assertSame('text/plain', $attachment->getMimetype(), "getMimetype()");
};

$testMissingFile = function () : void {
    $missingPath = __DIR__ . '/no-such-file.pdf';

    $attachMissingFile = function () use ($missingPath) : void {
        new Attachment($missingPath);
    };

    $exception = SmokeTest::expectException(ExceptionFileDoesNotExist::class, $attachMissingFile, $missingPath);
    SmokeTest::assertSame($missingPath, $exception->getFilepath(), "the exception's getFilepath()");
};

$testDirectory = function () : void {
    $attachDirectory = function () : void {
        new Attachment(__DIR__);
    };

    $exception = SmokeTest::expectException(ExceptionFileDoesNotExist::class, $attachDirectory);
    SmokeTest::assertSame(__DIR__, $exception->getFilepath(), "the exception's getFilepath()");
};

$smoke->run("attachment: defaults its name, encoding and mimetype", $testDefaults);
$smoke->run("attachment: holds the name, encoding and mimetype it's given", $testEverythingGiven);
$smoke->run("attachment: a file that doesn't exist is rejected when it's created", $testMissingFile);
$smoke->run("attachment: a directory is rejected when it's created", $testDirectory);

# Root can read any file, whatever its permissions, so this can only be tested as another user.
$unreadablePath = sys_get_temp_dir() . '/phpmailer-unreadable-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($unreadablePath, 'secret');
chmod($unreadablePath, 0);
clearstatcache();

if (is_readable($unreadablePath))
{
    $smoke->skip("attachment: an unreadable file is rejected when it's created", "running as root, which can read it");
}
else
{
    $testUnreadableFile = function () use ($unreadablePath) : void {
        $attachUnreadableFile = function () use ($unreadablePath) : void {
            new Attachment($unreadablePath);
        };

        $exceptionClass = ExceptionFileIsNotReadable::class;
        $exception = SmokeTest::expectException($exceptionClass, $attachUnreadableFile, $unreadablePath);
        SmokeTest::assertSame($unreadablePath, $exception->getFilepath(), "the exception's getFilepath()");
    };

    $smoke->run("attachment: an unreadable file is rejected when it's created", $testUnreadableFile);
}

unlink($unreadablePath);
