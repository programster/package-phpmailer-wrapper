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
