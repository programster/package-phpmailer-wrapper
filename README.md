PHPMailer Wrapper
=================
This package makes it easier to send more advanced emails with PHPMailer.

If you just want to be able to send basic emails, using a variety of different
possible drivers, you may be interested in the
[emailers package](https://github.com/programster/package-emailers) instead.

## Requirements
* PHP 8.2 or later
* The `mbstring` PHP extension

## Install
One can install the package with [Composer](https://getcomposer.org/) by running:

```bash
composer require programster/phpmailer-wrapper
```

## Example Usage

Below is an example of how one may use this tool to send an invoice as an attachment in an email to 
another person, being sure to provide both an HTML message, and the plaintext alternative, in case 
any of the recipients cannot view HTML emails. In this example, we also CC the accounts email 
address.

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
    smtpHost: 'smtp.gmail.com', 
    smtpUser: 'my.email@gmail.com', 
    smtpPassword: 'myPasswordGoesHere', 
    securityProtocol: SecurityProtocol::TLS,
    from: new \Programster\Phpmailer\Contact('myEmail@gmail.com', 'My Name'),
    smtpPort: 587
);

# Send an email
$mailer->send(
    subject: 'Latest Invoice',
    plaintextMessage: "Please find attached my latest invoice.",
    htmlMessage: "<p>Please find attached my latest invoice.</p>",
    to: new ContactCollection(new Contact("to.email@company.domain", "Client Name")),
    cc: new ContactCollection(new Contact("accounts@company.domain", "Accounts")),
    attachments: new AttachmentCollection(new Attachment(__DIR__ . '/invoice.pdf', "my-invoice.pdf")),
);
```

## When an Email Can't Be Sent
`send()` throws an `ExceptionFailedToSendEmail` if the email can't be sent. When the SMTP server refused it, the
exception has the server's reply code, so you can tell whether sending it again could work:

```php
use Programster\Phpmailer\ExceptionFailedToSendEmail;

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

    $replyCode = $exception->getSmtpReplyCode();       # e.g. 550, or null if there was no reply
    $extendedCode = $exception->getSmtpExtendedCode(); # e.g. "5.1.1", if the server sent one
}
```

The reply code is the one for the last SMTP command the server refused. PHPMailer itself clears it before `send()`
returns, so the wrapper keeps it as the email is sent.
