<?php

/*
 * Smoke tests for Contact. These are run by testing/main.php, which provides $smoke.
 */

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\Testing\SmokeTest;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testNamedContact = function () : void {
    $contact = new Contact('ann@example.com', 'Ann Smith');
    SmokeTest::assertSame('ann@example.com', $contact->getEmail(), "getEmail()");
    SmokeTest::assertSame('Ann Smith', $contact->getName(), "getName()");
};

$testUnnamedContact = function () : void {
    $contact = new Contact('ann@example.com', null);
    SmokeTest::assertSame('ann@example.com', $contact->getEmail(), "getEmail()");
    SmokeTest::assertSame('', $contact->getName(), "getName() of a contact with no name");
};

$testIsReadonly = function () : void {
    $isReadonly = (new \ReflectionClass(Contact::class))->isReadOnly();
    SmokeTest::assertSame(true, $isReadonly, "Contact is readonly, so it can't change once it's in a collection");
};

$smoke->run("contact: holds its email and name", $testNamedContact);
$smoke->run("contact: a contact with no name has an empty name", $testUnnamedContact);
$smoke->run("contact: is readonly", $testIsReadonly);
