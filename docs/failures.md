# Failures and reply codes

- [What send() throws](#what-send-throws)
- [Whether to retry](#whether-to-retry)
- [Reply codes](#reply-codes)
- [When some recipients are refused](#when-some-recipients-are-refused)
- [Why the wrapper keeps the reply code](#why-the-wrapper-keeps-the-reply-code)

## What send() throws
`send()` throws an `ExceptionFailedToSendEmail` whenever an email isn't sent:

- the email has no recipients (`to`, `cc` and `bcc` are all empty or `null`), which is refused without connecting to
  the server;
- the server can't be reached (e.g. the host or port is wrong, or the network is down);
- the TLS connection can't be made, e.g. the server's certificate can't be verified (see
  [Security protocols](security.md));
- the server refuses something: the login, the sender, a recipient or the email itself.

The exception's message is PHPMailer's description of what went wrong.

## Whether to retry
`isPermanentFailure()` says whether the server refused the email for good, with a 5xx reply, so sending it again
won't work, e.g. an address that doesn't exist (550), a login that's wrong (535) or an email rejected as spam (554).
Anything else isn't permanent: a 4xx reply (e.g. 450 for a busy mailbox, or 421 for a busy server) means try again
later, and no reply at all means the server wasn't reached, which may work later too.

```php
<?php

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\ExceptionFailedToSendEmail;
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

$to = new ContactCollection(new Contact('no-such-user@example.com', 'Nobody'));

try
{
    $mailer->send(subject: 'Hello', plaintextMessage: 'Hello', htmlMessage: null, to: $to);
}
catch (ExceptionFailedToSendEmail $exception)
{
    if ($exception->isPermanentFailure())
    {
        # The server refused it for good, so don't retry. E.g. mark the address as bad.
        echo "Refused: " . $exception->getSmtpReplyCode() . " " . $exception->getSmtpExtendedCode();
    }
    else
    {
        # The server refused it for now, or couldn't be reached: try again later.
    }
}
```

Here the server refuses the address with `550 5.1.1`, so `getSmtpReplyCode()` is 550, `getSmtpExtendedCode()` is
`"5.1.1"`, and `isPermanentFailure()` is true.

## Reply codes
| Method | Returns |
| --- | --- |
| `getSmtpReplyCode()` | The server's three digit reply code for what it refused, e.g. 550, or `null` if no server refused anything (it couldn't be reached, the TLS connection failed, or there were no recipients). |
| `getSmtpExtendedCode()` | The server's enhanced status code, e.g. `"5.1.1"`, or `null` if it didn't send one, as not every server does. |
| `isPermanentFailure()` | Whether the reply code is 5xx. |

Some common ones:

| Code | Meaning | Permanent? |
| --- | --- | --- |
| 421 | The server is busy, or closing the connection. | No |
| 450, 451, 452 | The mailbox is busy, there was an error, or the mailbox or server is full. | No |
| 454 | TLS isn't available right now. | No |
| 535 | The login was refused. | Yes |
| 550 | The mailbox doesn't exist, or the email isn't allowed. | Yes |
| 553 | The address isn't allowed, e.g. the sender. | Yes |
| 554 | The email was rejected, e.g. as spam. | Yes |

A 4xx reply that keeps coming back for the same email will probably never work, so a queue that retries should still
give up eventually.

```php
$full = new ContactCollection(new Contact('full@example.com', 'Full Mailbox'));

try
{
    $mailer->send(subject: 'Hello', plaintextMessage: 'Hello', htmlMessage: null, to: $full);
}
catch (ExceptionFailedToSendEmail $exception)
{
    $retryLater = !$exception->isPermanentFailure(); # true: the server replied 452 4.2.2, as the mailbox is full.
}
```

## When some recipients are refused
If the server refuses some of an email's recipients, but accepts others, PHPMailer still sends the email to the ones
it accepted, and `send()` then throws, with the reply code for the last recipient that was refused. So sending the
email again would send it to the accepted recipients a second time. If that matters (e.g. a queue that retries
failed emails), send an email to each recipient separately.

## Why the wrapper keeps the reply code
PHPMailer forgets the server's reply code after every command that works, and when it closes the connection, which
it does before `send()` says a recipient was refused. So the wrapper sends emails with `ReplyRecordingSmtp`, a
subclass of PHPMailer's SMTP client that keeps the reply to the last command the server refused, and puts it in the
exception. It's used by `PhpMailerEmailer`, and you shouldn't need to use it yourself.
