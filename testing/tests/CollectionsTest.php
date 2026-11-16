<?php

/*
 * Smoke tests for ContactCollection, AttachmentCollection and EmbeddedImageCollection: that each holds its elements
 * in order, and rejects anything else where it's added. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentCollection;
use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\EmbeddedImage;
use Programster\Phpmailer\EmbeddedImageCollection;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;
use Ramsey\Collection\Exception\InvalidArgumentException as CollectionInvalidArgumentException;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testContacts = function () : void {
    $ann = new Contact('ann@example.com', 'Ann');
    $bob = new Contact('bob@example.com', null);
    $contacts = new ContactCollection($ann, $bob);
    SmokeTest::assertSame([$ann, $bob], $contacts->toArray(), "elements");
    SmokeTest::assertSame(Contact::class, $contacts->getType(), "getType()");
    SmokeTest::assertSame(0, count(new ContactCollection()), "an empty collection");
};

$testAttachments = function () : void {
    $pdf = new Attachment(TestFixtures::SAMPLE_PDF);
    $notes = new Attachment(TestFixtures::NOTES_TXT);
    $attachments = new AttachmentCollection($pdf, $notes);
    SmokeTest::assertSame([$pdf, $notes], $attachments->toArray(), "elements");
    SmokeTest::assertSame(Attachment::class, $attachments->getType(), "getType()");
};

$testImages = function () : void {
    $image = new EmbeddedImage(TestFixtures::SAMPLE_PNG);
    $images = new EmbeddedImageCollection($image);
    SmokeTest::assertSame([$image], $images->toArray(), "elements");
    SmokeTest::assertSame(EmbeddedImage::class, $images->getType(), "getType()");
};

$testWrongTypeInConstructor = function () : void {
    $createWithWrongType = function () : void {
        new ContactCollection('ann@example.com');
    };

    SmokeTest::expectException(\TypeError::class, $createWithWrongType);
};

$testWrongTypeAdded = function () : void {
    $contacts = new ContactCollection();

    $addAttachment = function () use ($contacts) : void {
        $contacts->add(new Attachment(TestFixtures::SAMPLE_PDF));
    };

    $addString = function () use ($contacts) : void {
        $contacts[] = 'ann@example.com';
    };

    SmokeTest::expectException(CollectionInvalidArgumentException::class, $addAttachment);
    SmokeTest::expectException(CollectionInvalidArgumentException::class, $addString);
    SmokeTest::assertSame(0, count($contacts), "nothing was added");
};

$smoke->run("collections: a ContactCollection holds its contacts in order", $testContacts);
$smoke->run("collections: an AttachmentCollection holds its attachments in order", $testAttachments);
$smoke->run("collections: an EmbeddedImageCollection holds its images", $testImages);
$smoke->run("collections: the wrong type is rejected by the constructor", $testWrongTypeInConstructor);
$smoke->run("collections: the wrong type is rejected where it's added", $testWrongTypeAdded);
