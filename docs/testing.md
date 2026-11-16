# Testing

- [Running them on every supported version of PHP](#running-them-on-every-supported-version-of-php)
- [Running them on your own PHP](#running-them-on-your-own-php)
- [On GitHub](#on-github)
- [How the tests send emails](#how-the-tests-send-emails)
- [PHPStan](#phpstan)
- [The documentation's examples](#the-documentations-examples)
- [The live test](#the-live-test)
- [Adding a test](#adding-a-test)

How to run the package's own tests, e.g. when changing the package. You don't need these to use it.

The package is tested with smoke tests (in `testing/`), rather than PHPUnit, so there's no test framework to install
or keep working on every version of PHP. They call every public method, check every way of misusing one is rejected
where the mistake is made, and send emails to a fake SMTP server, to check exactly what's sent. A test fails if it
throws, fails an `assert()`, or raises any warning, notice or deprecation. They need no network access, and no SMTP
account.

## Running them on every supported version of PHP
This lints every file and runs the tests on PHP 8.2, 8.3, 8.4 and 8.5, each in its own Docker container, with no
network, then runs PHPStan once, on the lowest version. It's what the GitHub workflow runs, so a pass here means a
pass there.

```bash
composer install
testing/run-all-php-versions.sh
```

- To test only some versions, pass them, e.g. `testing/run-all-php-versions.sh 8.2 8.5`.
- To rebuild the Docker images first, e.g. to pick up the latest patch releases, pass `--rebuild`.

The containers run as your user, rather than root, as root can read any file, so the tests of an unreadable
attachment would have to be skipped. When a new version of PHP is released, add it to `SUPPORTED_VERSIONS` at the top
of the script, the workflow's job name, `composer.json`'s `php` requirement and `phpstan.neon.dist`'s `phpVersion`,
and the README. `composer.json` sets Composer's platform to PHP 8.2, so the dependencies it installs work on every
supported version, whichever version you run Composer with.

## Running them on your own PHP
```bash
php -d zend.assertions=1 testing/main.php
```

`zend.assertions` must be 1, or the `assert()` checks would silently do nothing, so the tests refuse to run without it.
To run only the tests whose name contains some text, set `PHPMAILER_TEST_ONLY`, e.g.
`PHPMAILER_TEST_ONLY=security php -d zend.assertions=1 testing/main.php`. It fails if no test matches, so a typo can't
pass without checking anything. Run as root, the tests of an unreadable file are skipped (and listed as skipped).

## On GitHub
`.github/workflows/tests.yml` runs `testing/run-all-php-versions.sh` for every push to `main`, every tag, every pull
request into `main`, and by hand (with "Run workflow" on the Actions tab). It writes the results as annotations (with
`testing/github-annotations.sh`): a notice with each version's results, and an error for each failed test, which can
be read on the workflow's page without logging in to GitHub. The README's "Tests" badge shows whether they pass on
`main`. It doesn't run [the live test](#the-live-test).

## How the tests send emails
To a fake SMTP server (`testing/FakeSmtp/FakeSmtpServer.php`), which each test starts, on `127.0.0.1`, in its own
PHP process, as PHPMailer waits for the server's replies. It records everything it's sent, which the test reads back:
every command, before and after TLS started, the login, the envelope (`MAIL FROM` and each `RCPT TO`, including BCC
ones) and each message, which `testing/Mime/MimePart.php` parses, so a test can check its headers, each part's
decoded content, its attachments and embedded images, and the character set.

- **Refusals.** A test can tell the server to refuse a recipient, the sender, the login, STARTTLS or the message, with
  the reply it chooses (e.g. `550 5.1.1 No such user`), to check the reply codes `send()` throws with.
- **TLS.** The server can offer STARTTLS, or use TLS from the start, with a self-signed certificate made for each
  server (`TestCertificate`). The tests check a certificate the client can't verify is refused, before anything else
  is sent. To check TLS works with one it can verify, `ChildProcessSendResult` sends from another PHP process, started
  with `openssl.cafile` set to the certificate, as that setting can't be changed once PHP has started.

## PHPStan
```bash
vendor/bin/phpstan
```

It runs at the highest level, 10, set in `phpstan.neon.dist`, over `src/` and `testing/`, analysing the code as PHP
8.2 to 8.5. The only errors it ignores are in `CollectionsTest.php`, which passes the wrong type to the collections on
purpose, to check it's rejected.

## The documentation's examples
`testing/tests/DocsTest.php` runs the PHP examples in the README and every page in `docs/`, so the documentation can't
drift from what the code does. Each document's examples run in order, as one script, in a separate PHP process, with a
`use` statement for every class in the package added, and send their emails to fake SMTP servers:
`'smtp.example.com'` becomes `127.0.0.1`, and `smtpPort: 587`, `465` and `1025` become the ports of servers that offer
STARTTLS, use TLS from the start, and don't use TLS. The server for 587 refuses `no-such-user@example.com` (550) and
`full@example.com` (452), for the examples of failures. An example whose first line is `// Throws SomeException` must
throw that exception. See `testing/DocsExampleRunner.php`.

## The live test
`testing/live-test.php` sends real emails through a real SMTP server, to check the package works with one, not just
the fake. It's opt-in, and only runs locally, by hand: it isn't run by `main.php`, `run-all-php-versions.sh` or the
workflow, and refuses to run in CI (when `CI` or `GITHUB_ACTIONS` is set). To run it:

1. Copy `testing/settings.php.tmpl` to `testing/settings.php`, which is git-ignored, so your password is never
   committed.
2. Fill in an SMTP account for testing (e.g. a mailbox of your own, or a sandbox such as Mailtrap, or MailHog), never
   one a live site sends its emails through, and your own mailboxes as the recipients.
3. Set `isTestAccount: true`, to confirm it. The test refuses to run until you do.
4. Run it, then check your mailbox for the three emails it sends:

```bash
php -d zend.assertions=1 testing/live-test.php
```

The settings used to be in `testing/Settings.php`, as constants. If you have one, move its values into
`testing/settings.php`, and delete it.

## Adding a test
Each class has a file of tests in `testing/tests/` (e.g. `AttachmentTest.php`), and sending has files for what's
sent (`SendingTest.php`), the security protocols (`SecurityTest.php`) and failures (`FailuresTest.php`). `main.php`
runs every `*Test.php` file, giving each `$smoke`. A test is a named callback, assigned to a variable before it's run:

```php
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerMode;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

$testSubject = function () : void {
    $server = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
    $emailer = TestFixtures::createEmailer($server);
    $emailer->send('Hello', 'Hello', null, TestFixtures::createAnn());

    $message = $server->fetchOnlySession()->getOnlyMessage();
    SmokeTest::assertSame('Hello', $message->getHeader('Subject'), "the subject");
};

$smoke->run("sending: the subject is sent", $testSubject);
```

Use `SmokeTest::expectException()` to check something throws. It returns the exception, so it can be checked further.
Follow the coding standards used in `src/` in the tests too, e.g. no arrow functions, and closures assigned to a
descriptively named variable before being passed to `run()` or `expectException()`.
