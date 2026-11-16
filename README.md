PHPMailer Wrapper
=================

[![Tests](https://github.com/programster/package-phpmailer-wrapper/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/programster/package-phpmailer-wrapper/actions/workflows/tests.yml)

## Contents
- [About](#about)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Sending emails](docs/sending.md)
- [Contacts and collections](docs/contacts-and-collections.md)
- [Attachments and embedded images](docs/attachments-and-images.md)
- [Failures and reply codes](docs/failures.md)
- [Security protocols](docs/security.md)
- [Upgrading](docs/upgrading.md)
- [Testing](docs/testing.md)
- [Design principles](#design-principles)
- [License](#license)

## About
This package makes it easier to send more advanced emails through SMTP with
[PHPMailer](https://github.com/PHPMailer/PHPMailer): HTML emails with a plaintext alternative, CC and BCC recipients,
attachments and embedded images, in one call to `send()`. When an email can't be sent, it throws an exception that
says whether the SMTP server refused it for good (e.g. 550, for an address that doesn't exist) or just for now (e.g. a
4xx, as the server is busy), so you know whether to try again.

If you just want to be able to send basic emails, using a variety of different possible drivers, you may be interested
in the [emailers package](https://github.com/programster/package-emailers) instead.

## Requirements
- PHP 8.2, 8.3, 8.4 or 8.5.
- The `mbstring` PHP extension.
- An SMTP server to send through (e.g. your email provider's, or a local testing tool such as MailHog or Mailpit).

## Installation
```bash
composer require programster/phpmailer-wrapper
```

## Quick start
Create an emailer with your SMTP server's details, then `send()` as many emails as you like with it. This sends an
invoice as an attachment, with both an HTML message and a plaintext alternative, in case any of the recipients can't
view HTML emails, and CCs the accounts department:

```php
<?php

use Programster\Phpmailer\Attachment;
use Programster\Phpmailer\AttachmentCollection;
use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\SecurityProtocol;

require_once(__DIR__ . '/vendor/autoload.php');

# Create the emailer. We can use this to send() any number of emails.
$mailer = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com',
    smtpUser: 'my.email@example.com',
    smtpPassword: 'myPasswordGoesHere',
    securityProtocol: SecurityProtocol::TLS,
    from: new Contact('my.email@example.com', 'My Name'),
    smtpPort: 587
);

# Send an email
$mailer->send(
    subject: 'Latest Invoice',
    plaintextMessage: "Please find attached my latest invoice.",
    htmlMessage: "<p>Please find attached my latest invoice.</p>",
    to: new ContactCollection(new Contact("client@example.com", "Client Name")),
    cc: new ContactCollection(new Contact("accounts@example.com", "Accounts")),
    attachments: new AttachmentCollection(new Attachment(__DIR__ . '/invoice.pdf', "my-invoice.pdf")),
);
```

If it can't be sent, `send()` throws an `ExceptionFailedToSendEmail`, which says whether trying again could work:

```php
use Programster\Phpmailer\ExceptionFailedToSendEmail;

$to = new ContactCollection(new Contact("client@example.com", "Client Name"));

try
{
    $mailer->send(subject: 'Hello', plaintextMessage: 'Hello', htmlMessage: null, to: $to);
}
catch (ExceptionFailedToSendEmail $exception)
{
    if ($exception->isPermanentFailure())
    {
        # The server refused it for good (a 5xx reply, e.g. 550 for an address that doesn't exist), so don't retry.
    }
    else
    {
        # The server refused it for now (a 4xx reply, e.g. it's busy), or couldn't be reached: it may work later.
    }
}
```

See [Failures and reply codes](docs/failures.md) for more, and the [documentation](docs/README.md) for everything
else.

## Design principles
- **One call sends one email.** Everything about an email is given to `send()`, so nothing from one email (e.g. a
  recipient, or an attachment) can carry over into the next, however many you send with the same emailer.
- **Mistakes are caught where they're made.** An attachment or embedded image whose file doesn't exist, isn't a file,
  or can't be read, is rejected when it's created, rather than when the email is sent. The collections only accept
  their own type, and the security protocol and attachment encoding are enums, so a typo can't be written at all.
- **Secure by default.** `SecurityProtocol::TLS` and `SecurityProtocol::SSL` verify the server's certificate, and
  never fall back to sending in plain text. `SecurityProtocol::NONE` (for local testing tools) never upgrades to TLS,
  so it does exactly what it says.
- **Failures say whether to retry.** `ExceptionFailedToSendEmail` keeps the SMTP server's reply code, which PHPMailer
  itself loses before `send()` returns.
- **Readonly values.** Contacts, attachments, embedded images and the emailer itself can't change once they're
  created, so they're safe to share.

## License
MIT. See [LICENSE](LICENSE).
