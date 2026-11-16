# Sending emails

- [Creating an emailer](#creating-an-emailer)
- [Plaintext emails](#plaintext-emails)
- [HTML emails](#html-emails)
- [Recipients](#recipients)
- [Reply-to](#reply-to)
- [Character sets](#character-sets)

## Creating an emailer
A `PhpMailerEmailer` holds your SMTP server's details, and who emails are from. Create one, and use it to `send()` as
many emails as you like. Each email is sent in its own connection to the server, and nothing from one email carries
over to the next.

```php
<?php

use PHPMailer\PHPMailer\PHPMailer;
use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
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
```

| Parameter | What it's for |
| --- | --- |
| `smtpHost` | The SMTP server's host name, e.g. `smtp.gmail.com`. |
| `smtpUser`, `smtpPassword` | What to log in to the SMTP server with. The emailer always logs in. |
| `securityProtocol` | How the connection is encrypted. See [Security protocols](security.md). |
| `from` | Who the emails are from, as a `Contact`. |
| `smtpPort` | The port to connect to. Defaults to 587, the usual port for `SecurityProtocol::TLS`. |
| `replyTo` | Where replies go, as a `Contact`. Defaults to `from`. |
| `charset` | The character set the subject and messages are in. Defaults to UTF-8. |

## Plaintext emails
Pass `null` as the HTML message to send a plaintext email:

```php
$to = new ContactCollection(new Contact('client@example.com', 'Client Name'));

$mailer->send(
    subject: 'Your order has shipped',
    plaintextMessage: "Hello,\n\nYour order has shipped.",
    htmlMessage: null,
    to: $to
);
```

## HTML emails
Pass an HTML message as well, and the plaintext message is sent as its alternative, for anyone whose email program
can't (or is set not to) show HTML. Any HTML tags in the plaintext message are stripped out.

```php
$mailer->send(
    subject: 'Your order has shipped',
    plaintextMessage: "Hello,\n\nYour order has shipped.",
    htmlMessage: "<p>Hello,</p><p>Your order has <strong>shipped</strong>.</p>",
    to: $to
);
```

To show images in the HTML, embed them. See [Attachments and embedded images](attachments-and-images.md).

## Recipients
`to`, `cc` and `bcc` each take a `ContactCollection` (or `null`). An email needs at least one recipient in one of
them: `send()` throws an `ExceptionFailedToSendEmail` if they're all empty or `null`, without connecting to the
server. BCC recipients get the email, but aren't in it, so nobody else can see they were sent it. See
[Contacts and collections](contacts-and-collections.md).

```php
$mailer->send(
    subject: 'Newsletter',
    plaintextMessage: "This month's news.",
    htmlMessage: null,
    to: null,
    bcc: new ContactCollection(
        new Contact('subscriber.one@example.com', null),
        new Contact('subscriber.two@example.com', null),
    )
);
```

## Reply-to
By default, replies go to the `from` address. To send them somewhere else, give a `replyTo` contact:

```php
$mailerWithReplyTo = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com',
    smtpUser: 'noreply@example.com',
    smtpPassword: 'myPasswordGoesHere',
    securityProtocol: SecurityProtocol::TLS,
    from: new Contact('noreply@example.com', 'My Shop'),
    smtpPort: 587,
    replyTo: new Contact('support@example.com', 'My Shop Support')
);
```

## Character sets
Emails are sent as UTF-8 by default, which can hold any language's text. The character set says what the subject and
messages you pass to `send()` are already in: it doesn't convert them. To send in another character set, give the
emailer the character set's name (PHPMailer has constants for some, such as `PHPMailer::CHARSET_ISO88591`), and pass
it text in that character set:

```php
$latin1Mailer = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com',
    smtpUser: 'my.email@example.com',
    smtpPassword: 'myPasswordGoesHere',
    securityProtocol: SecurityProtocol::TLS,
    from: new Contact('my.email@example.com', 'My Name'),
    smtpPort: 587,
    charset: PHPMailer::CHARSET_ISO88591
);

$latin1Mailer->send(
    subject: mb_convert_encoding('Café opening', 'ISO-8859-1', 'UTF-8'),
    plaintextMessage: mb_convert_encoding('The café opens on Monday.', 'ISO-8859-1', 'UTF-8'),
    htmlMessage: null,
    to: $to
);
```

PHPMailer labels any part of an email that's only ASCII as `us-ascii`, whatever the character set, as ASCII is the
same in all of them.
