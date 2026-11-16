<?php

/*
 * Smoke tests for EmbeddedImage, including that a file that can't be embedded is rejected when the image is created,
 * rather than when the email is sent. These are run by testing/main.php, which provides $smoke. What's sent for an
 * embedded image is tested in SendingTest.php.
 */

use Programster\Phpmailer\EmbeddedImage;
use Programster\Phpmailer\ExceptionFileDoesNotExist;
use Programster\Phpmailer\ExceptionFileIsNotReadable;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testDefaultCid = function () : void {
    $image = new EmbeddedImage(TestFixtures::SAMPLE_PNG);
    SmokeTest::assertSame(TestFixtures::SAMPLE_PNG, $image->getFilepath(), "getFilepath()");
    SmokeTest::assertSame('sample.png', $image->getCid(), "getCid() defaults to the file's name");
};

$testGivenCid = function () : void {
    $image = new EmbeddedImage(TestFixtures::SAMPLE_PNG, 'logo');
    SmokeTest::assertSame('logo', $image->getCid(), "getCid()");
};

$testMissingFile = function () : void {
    $missingPath = __DIR__ . '/no-such-image.png';

    $embedMissingFile = function () use ($missingPath) : void {
        new EmbeddedImage($missingPath);
    };

    $exception = SmokeTest::expectException(ExceptionFileDoesNotExist::class, $embedMissingFile, $missingPath);
    SmokeTest::assertSame($missingPath, $exception->getFilepath(), "the exception's getFilepath()");
};

$testDirectory = function () : void {
    $embedDirectory = function () : void {
        new EmbeddedImage(__DIR__);
    };

    SmokeTest::expectException(ExceptionFileDoesNotExist::class, $embedDirectory);
};

$smoke->run("embedded image: defaults its cid to the file's name", $testDefaultCid);
$smoke->run("embedded image: holds the cid it's given", $testGivenCid);
$smoke->run("embedded image: a file that doesn't exist is rejected when it's created", $testMissingFile);
$smoke->run("embedded image: a directory is rejected when it's created", $testDirectory);

# Root can read any file, whatever its permissions, so this can only be tested as another user.
$unreadablePath = sys_get_temp_dir() . '/phpmailer-unreadable-' . bin2hex(random_bytes(4)) . '.png';
file_put_contents($unreadablePath, 'secret');
chmod($unreadablePath, 0);
clearstatcache();

if (is_readable($unreadablePath))
{
    $label = "embedded image: an unreadable file is rejected when it's created";
    $smoke->skip($label, "running as root, which can read it");
}
else
{
    $testUnreadableFile = function () use ($unreadablePath) : void {
        $embedUnreadableFile = function () use ($unreadablePath) : void {
            new EmbeddedImage($unreadablePath);
        };

        $exceptionClass = ExceptionFileIsNotReadable::class;
        $exception = SmokeTest::expectException($exceptionClass, $embedUnreadableFile, $unreadablePath);
        SmokeTest::assertSame($unreadablePath, $exception->getFilepath(), "the exception's getFilepath()");
    };

    $smoke->run("embedded image: an unreadable file is rejected when it's created", $testUnreadableFile);
}

unlink($unreadablePath);
