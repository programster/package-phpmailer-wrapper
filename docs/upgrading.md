# Upgrading

- [Unreleased](#unreleased)
- [From 2.0.0 to 2.1.0](#from-200-to-210)
- [From 1.2.3 to 2.0.0](#from-123-to-200)
    - [Changes you need to make](#changes-you-need-to-make)
    - [Changes in behaviour](#changes-in-behaviour)
- [From 1.2.2 to 1.2.3](#from-122-to-123)
- [From 1.2.1 to 1.2.2](#from-121-to-122)
- [From 1.2.0 to 1.2.1](#from-120-to-121)
- [From 1.1.0 to 1.2.0](#from-110-to-120)
- [From 1.0.0 to 1.1.0](#from-100-to-110)

What changed in each release, newest first, and what, if anything, you need to change in your code when upgrading.

## Unreleased
Nothing needs to change in your code, unless it attaches or embeds a directory.

- **A directory is rejected as an attachment or embedded image.** `new Attachment()` and `new EmbeddedImage()` throw
  an `ExceptionFileDoesNotExist` for a directory, as they do for a path with nothing there. Before, they accepted it,
  and PHPMailer then left it out of the email without saying so.
- **PHP 9 isn't claimed.** `composer.json` requires PHP `^8.2`, rather than `>= 8.2.0`, as the package hasn't been
  tested on PHP 9, which isn't out yet. It's tested on PHP 8.2, 8.3, 8.4 and 8.5.
- **Tests and documentation.** The package now has offline tests, which send to a fake SMTP server, and full
  documentation, in `docs/`. See [Testing](testing.md).

## From 2.0.0 to 2.1.0
Nothing needs to change in your code.

- **New: the SMTP server's reply code, when a send fails.** `ExceptionFailedToSendEmail` has `getSmtpReplyCode()`
  (e.g. 550), `getSmtpExtendedCode()` (e.g. `"5.1.1"`) and `isPermanentFailure()`, which says whether the server
  refused the email for good (a 5xx reply), so you can tell whether to retry. See [Failures](failures.md).
- **New: `ReplyRecordingSmtp`**, the PHPMailer SMTP client the emailer now sends with, which keeps the reply to the
  last command the server refused, as PHPMailer forgets it before `send()` returns.
- `ExceptionFailedToSendEmail`'s constructor still works with just a message.

## From 1.2.3 to 2.0.0
PHP 8.2 is now the minimum, so the classes can be readonly.

### Changes you need to make
- **Read properties through their getters.** The public properties are now private, with a getter for each:

  | Before | Now |
  | --- | --- |
  | `$contact->email`, `$contact->name` | `$contact->getEmail()`, `$contact->getName()` |
  | `$attachment->filepath`, `->name`, `->encoding`, `->mimetype` | `getFilepath()`, `getName()`, `getEncoding()`, `getMimetype()` |
  | `$image->filepath`, `$image->cid` | `getFilepath()`, `getCid()` |
  | `$exception->filepath` (on the file exceptions) | `$exception->getFilepath()` |

  `Contact::getName()` returns an empty string for a contact with no name, where `->name` was `null`, and
  `Attachment::getMimetype()` returns an empty string when it'll be worked out from the file, where `->mimetype` was
  `null`.
- **The collections are [ramsey/collection](https://github.com/ramsey/collection) collections**, rather than
  `ArrayObject`s, so use their methods, e.g. `add()` instead of `append()`. Their constructors are the same.
- **`EmailerInterface` has been removed.** Type against `PhpMailerEmailer` instead.

### Changes in behaviour
- **Emails are sent as UTF-8 by default**, rather than PHPMailer's default of ISO-8859-1. The constructor's new
  `charset` parameter sets another. See [Character sets](sending.md#character-sets).
- **`SecurityProtocol::NONE` never upgrades to TLS.** Before, PHPMailer would still use STARTTLS if the server
  offered it.
- **Empty collections count as no recipients.** If `to`, `cc` and `bcc` are all empty (or `null`), `send()` throws an
  `ExceptionFailedToSendEmail` without connecting, saying there are no recipients. Before, only `null` counted, and
  an empty collection was refused by PHPMailer instead, with its own message.

## From 1.2.2 to 1.2.3
Nothing needs to change in your code.

- **Fix:** `send()`'s `$images` parameter is declared nullable (`?EmbeddedImageCollection`), as its default is `null`,
  which PHP 8.4 deprecates for a type that isn't nullable.

## From 1.2.1 to 1.2.2
Nothing needs to change in your code.

- **Fix:** `send()`'s `$attachments` parameter is declared nullable (`?AttachmentCollection`), for the same reason.

## From 1.2.0 to 1.2.1
Nothing needs to change in your code.

- **New: `SecurityProtocol::NONE`**, for sending without encryption, to local testing tools such as MailHog.

## From 1.1.0 to 1.2.0
Nothing needs to change in your code.

- **New: embedded images.** `EmbeddedImage` and `EmbeddedImageCollection`, and `send()`'s `$images` parameter, for
  images an HTML message shows with `<img src="cid:...">`.

## From 1.0.0 to 1.1.0
- **The namespace is now `Programster\Phpmailer`**, rather than `Programster\Emailers`, so it doesn't clash with the
  [emailers package](https://github.com/programster/package-emailers). Change your `use` statements.
