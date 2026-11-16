# Attachments and embedded images

- [Attachments](#attachments)
- [Embedded images](#embedded-images)
- [When a file can't be used](#when-a-file-cant-be-used)

## Attachments
An `Attachment` is a file to attach to an email. Give it the path to the file, and optionally:

- `name`: the file's name in the email. Defaults to the file's own name.
- `encoding`: how the file is encoded in the email, as an `AttachmentEncoding`. Defaults to `BASE_64`, which works
  for any file.
- `mimetype`: the file's type, e.g. `application/pdf`. By default, it's worked out from the file's extension, when
  the email is sent.

```php
<?php

use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentCollection;
use Programster\Phpmailer\AttachmentEncoding;
use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\EmbeddedImage;
use Programster\Phpmailer\EmbeddedImageCollection;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\SecurityProtocol;

$mailer = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com',
    smtpUser: 'my.email@example.com',
    smtpPassword: 'myPasswordGoesHere',
    securityProtocol: SecurityProtocol::TLS,
    from: new Contact('my.email@example.com', 'My Name'),
    smtpPort: 587
);

$to = new ContactCollection(new Contact('client@example.com', 'Client Name'));

$attachments = new AttachmentCollection(
    new Attachment(__DIR__ . '/invoice.pdf', 'invoice-2026-11.pdf'),
    new Attachment(__DIR__ . '/notes.txt', 'notes.txt', AttachmentEncoding::QUOTED_PRINTABLE, 'text/plain'),
);

$mailer->send(
    subject: 'Your invoice',
    plaintextMessage: 'Please find your invoice attached.',
    htmlMessage: null,
    to: $to,
    attachments: $attachments
);
```

The encodings are the ones PHPMailer supports: `BASE_64`, `QUOTED_PRINTABLE` (for text that's mostly ASCII),
`SEVEN_BIT`, `EIGHT_BIT` and `BINARY`. Leave it as `BASE_64` unless you have a reason not to.

## Embedded images
An `EmbeddedImage` is an image sent inside an email, for its HTML message to show, rather than as an attachment. The
HTML refers to it by its content ID (cid), with `cid:` in front, e.g. `<img src="cid:logo">`. The cid defaults to the
file's name (e.g. `logo.png`).

```php
$images = new EmbeddedImageCollection(
    new EmbeddedImage(__DIR__ . '/logo.png', 'logo'),
);

$mailer->send(
    subject: 'Welcome',
    plaintextMessage: 'Welcome to My Shop.',
    htmlMessage: '<p><img src="cid:logo" alt="My Shop"></p><p>Welcome to My Shop.</p>',
    to: $to,
    images: $images
);
```

An email can have attachments and embedded images at the same time.

## When a file can't be used
An attachment or embedded image is checked when it's created, so the mistake is caught where it's made, rather than
when the email is sent:

- `ExceptionFileDoesNotExist` if there's no file at the path (including if it's a directory).
- `ExceptionFileIsNotReadable` if the file exists, but can't be read (e.g. because of its permissions).

Both have `getFilepath()`, for the path that was given.

```php
use Programster\Phpmailer\ExceptionFileDoesNotExist;

try
{
    new Attachment(__DIR__ . '/no-such-invoice.pdf');
}
catch (ExceptionFileDoesNotExist $exception)
{
    echo "Couldn't find " . $exception->getFilepath();
}
```

```php
// Throws ExceptionFileDoesNotExist
new EmbeddedImage(__DIR__); # A directory isn't an image.
```
