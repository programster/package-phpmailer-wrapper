# Contacts and collections

- [Contacts](#contacts)
- [Collections](#collections)

## Contacts
A `Contact` is an email address, and optionally a name, to send an email to or from. Pass `null` for a contact with
no name, and `getName()` returns an empty string.

```php
<?php

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;

$client = new Contact('client@example.com', 'Client Name');
$accounts = new Contact('accounts@example.com', null);

echo $client->getEmail(); # client@example.com
echo $client->getName();  # Client Name
echo $accounts->getName(); # (an empty string)
```

Contacts are readonly, so a contact can't change once it's been created, e.g. after it's been put in a collection.

> **Check addresses before sending them.** The address isn't checked when a contact is created. PHPMailer leaves out
> any recipient whose address isn't valid, without an error, and still sends the email to the rest. If none of the
> recipients are valid, `send()` throws an `ExceptionFailedToSendEmail` with no reply code. An invalid `from` address
> is sent as an empty sender. So check any address you didn't write yourself (e.g. one a user typed in) first, e.g.
> with `filter_var($email, FILTER_VALIDATE_EMAIL)`.

## Collections
`send()` takes the recipients as a `ContactCollection`, the attachments as an `AttachmentCollection` and the embedded
images as an `EmbeddedImageCollection`. Each only accepts its own type, so anything else is rejected where it's
added, rather than when the email is sent. Pass the elements to the constructor:

```php
$to = new ContactCollection($client, $accounts);

echo count($to); # 2

foreach ($to as $contact)
{
    echo $contact->getEmail() . "\n";
}
```

```php
// Throws TypeError
$to = new ContactCollection('client@example.com'); # A string isn't a Contact.
```

The collections are [ramsey/collection](https://github.com/ramsey/collection) collections, so they have all its
methods, e.g. `add()`, `filter()` and `map()`:

```php
$to->add(new Contact('director@example.com', 'Director'));
echo count($to); # 3
```

A recipient in more than one of `to`, `cc` and `bcc` (or twice in one of them) only gets the email once.
