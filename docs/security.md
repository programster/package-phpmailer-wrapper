# Security protocols

- [Which to use](#which-to-use)
- [TLS](#tls)
- [SSL](#ssl)
- [None](#none)
- [Certificates](#certificates)

## Which to use
The `securityProtocol` you give the emailer says how its connection to the SMTP server is encrypted:

| Protocol | Usual port | What it does |
| --- | --- | --- |
| `SecurityProtocol::TLS` | 587 | Connects in plain text, then upgrades the connection to TLS with STARTTLS, before logging in. |
| `SecurityProtocol::SSL` | 465 | Connects with TLS from the start (also called implicit TLS, or SMTPS). |
| `SecurityProtocol::NONE` | 1025, or 25 | Doesn't encrypt anything, for local testing tools such as MailHog or Mailpit. |

Use whichever your email provider says to. Both `TLS` and `SSL` use TLS (the names come from PHPMailer, and SSL is
TLS's old name), and both are secure.

## TLS
With `SecurityProtocol::TLS`, the emailer only sends the server its greeting (EHLO) and STARTTLS in plain text, then
everything else, including the login and the email, over TLS. If the server doesn't offer STARTTLS, or the TLS
connection can't be made, nothing else is sent, and `send()` throws an `ExceptionFailedToSendEmail`: it never falls
back to plain text.

```php
<?php

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\PhpMailerEmailer;
use Programster\Phpmailer\SecurityProtocol;

$to = new ContactCollection(new Contact('client@example.com', 'Client Name'));

$tlsMailer = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com',
    smtpUser: 'my.email@example.com',
    smtpPassword: 'myPasswordGoesHere',
    securityProtocol: SecurityProtocol::TLS,
    from: new Contact('my.email@example.com', 'My Name'),
    smtpPort: 587
);

$tlsMailer->send(subject: 'Sent with STARTTLS', plaintextMessage: 'Hello', htmlMessage: null, to: $to);
```

## SSL
With `SecurityProtocol::SSL`, the connection is encrypted from the start, so nothing at all is sent in plain text.

```php
$sslMailer = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com',
    smtpUser: 'my.email@example.com',
    smtpPassword: 'myPasswordGoesHere',
    securityProtocol: SecurityProtocol::SSL,
    from: new Contact('my.email@example.com', 'My Name'),
    smtpPort: 465
);

$sslMailer->send(subject: 'Sent over implicit TLS', plaintextMessage: 'Hello', htmlMessage: null, to: $to);
```

## None
`SecurityProtocol::NONE` sends everything in plain text, including the password, so only use it for a testing tool
on your own machine or network, such as [MailHog](https://github.com/mailhog/MailHog) or
[Mailpit](https://mailpit.axllent.org/). It's strict: the emailer won't upgrade to TLS even if the server offers
STARTTLS (which PHPMailer would otherwise do by itself).

```php
$mailhogMailer = new PhpMailerEmailer(
    smtpHost: 'smtp.example.com', # e.g. "mailhog", the name of its container
    smtpUser: 'anything',
    smtpPassword: 'anything',
    securityProtocol: SecurityProtocol::NONE,
    from: new Contact('my.email@example.com', 'My Name'),
    smtpPort: 1025
);

$mailhogMailer->send(subject: 'Caught by MailHog', plaintextMessage: 'Hello', htmlMessage: null, to: $to);
```

## Certificates
With `TLS` and `SSL`, the server's certificate is verified, as PHP verifies any other TLS connection: it must be
valid, for the host name you gave as `smtpHost`, and issued by a certificate authority PHP trusts (from the system's
certificates, or PHP's `openssl.cafile` setting). If it isn't, nothing is sent, and `send()` throws an
`ExceptionFailedToSendEmail` with no reply code. There's deliberately no option to switch this off, as it's what stops
someone in the middle reading your password and emails. For a server with a self-signed certificate, add its
certificate to the ones PHP trusts instead.
