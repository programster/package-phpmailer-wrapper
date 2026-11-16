<?php

/*
 * Turns a document's examples into a script and runs it, so the docs can't drift from what the code does:
 *
 * - its php code blocks run in order, as one script, in a separate PHP process, with a use statement added for every
 *   class in the package.
 *
 * - the emails they send go to fake SMTP servers on this machine (see FakeSmtpServer), rather than anywhere real:
 *   "smtp.example.com" becomes "127.0.0.1", and the port in "smtpPort: 587" becomes a server's that offers STARTTLS,
 *   "smtpPort: 465" one's that uses TLS from the start, and "smtpPort: 1025" one's with no TLS (like MailHog). The
 *   script trusts the servers' certificates, so TLS is used, and verified, as it is for a real server. The server on
 *   port 587 refuses no-such-user@example.com (550 5.1.1) and full@example.com (452 4.2.2), for the examples of
 *   failures.
 *
 * - the files the examples attach (invoice.pdf, notes.txt and logo.png, in __DIR__) are copies of the test files.
 *
 * - a php block whose first line is "// Throws SomeException" must throw that exception (which can be given by its
 *   short name, for the package's own exceptions).
 *
 * - what the examples echo is thrown away, but any error, warning, notice or deprecation fails them.
 *
 * - $smoke is a SmokeTest, as in a test file, for the examples of writing a test. If any of its tests fail, so does
 *   the document.
 */

namespace Programster\Phpmailer\Testing;

use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpRefusal;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServer;
use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerMode;


final readonly class DocsExampleRunner
{
    private string $m_repositoryDirectory;


    /**
     * @param string $repositoryDirectory - the package's root folder, which holds vendor/autoload.php.
     */
    public function __construct(string $repositoryDirectory)
    {
        $this->m_repositoryDirectory = $repositoryDirectory;
    }


    /**
     * Count the php code blocks in a document, so a test can tell whether there's anything to run.
     * @param string $documentPath - the Markdown file.
     * @return int
     */
    public function countExamples(string $documentPath) : int
    {
        return count($this->getPhpBlocks($documentPath));
    }


    /**
     * Run a document's examples, and throw if they fail.
     * @param string $documentPath - the Markdown file.
     * @return int - the number of connections made to the fake SMTP servers, so a test can check they were used.
     * @throws \Exception - if the script fails, with its output.
     */
    public function run(string $documentPath) : int
    {
        $startTlsServer = FakeSmtpServer::create(
            FakeSmtpServerMode::STARTTLS,
            FakeSmtpRefusal::createRecipientRefusal('no-such-user@example.com', '550 5.1.1 No such user'),
            FakeSmtpRefusal::createRecipientRefusal('full@example.com', '452 4.2.2 Mailbox full')
        );

        $implicitTlsServer = FakeSmtpServer::create(FakeSmtpServerMode::IMPLICIT_TLS);
        $plainServer = FakeSmtpServer::create(FakeSmtpServerMode::NO_TLS);
        $directory = sys_get_temp_dir() . '/phpmailer-docs-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        copy(TestFixtures::SAMPLE_PDF, "{$directory}/invoice.pdf");
        copy(TestFixtures::NOTES_TXT, "{$directory}/notes.txt");
        copy(TestFixtures::SAMPLE_PNG, "{$directory}/logo.png");

        $certificates = TestFixtures::readFile($startTlsServer->getCertificatePath())
            . TestFixtures::readFile($implicitTlsServer->getCertificatePath());

        file_put_contents("{$directory}/trusted-certificates.pem", $certificates);

        $ports = [
            587 => $startTlsServer->getPort(),
            465 => $implicitTlsServer->getPort(),
            1025 => $plainServer->getPort(),
        ];

        $scriptPath = "{$directory}/examples.php";
        file_put_contents($scriptPath, $this->buildScript($this->getPhpBlocks($documentPath), $ports));

        $command = escapeshellarg(PHP_BINARY)
            . ' -d zend.assertions=1 -d assert.exception=1 -d error_reporting=-1 -d display_errors=stderr'
            . ' -d ' . escapeshellarg("openssl.cafile={$directory}/trusted-certificates.pem")
            . ' ' . escapeshellarg($scriptPath) . ' 2>&1 1>/dev/null';

        exec($command, $outputLines, $exitCode);

        foreach (glob("{$directory}/*") ?: [] as $path)
        {
            unlink($path);
        }

        rmdir($directory);
        $numConnections = 0;

        foreach ([$startTlsServer, $implicitTlsServer, $plainServer] as $server)
        {
            $numConnections += count($server->fetchSessions());
        }

        // What the examples echo is thrown away, so this is just PHP's errors, which it writes to STDERR.
        $output = trim(implode("\n", array_slice($outputLines, -20)));

        if ($exitCode !== 0 || $output !== '')
        {
            $name = basename($documentPath);
            $diagnostics = $startTlsServer->fetchDiagnostics();
            throw new \Exception("The examples in {$name} failed (exit code {$exitCode}): {$output} ({$diagnostics})");
        }

        return $numConnections;
    }


    /**
     * Get the contents of a document's php code blocks, in order.
     * @param string $documentPath - the Markdown file.
     * @return list<string>
     */
    private function getPhpBlocks(string $documentPath) : array
    {
        preg_match_all('/^```php\n(.*?)^```/ms', TestFixtures::readFile($documentPath), $matches);
        return $matches[1];
    }


    /**
     * Build the script for a document's examples.
     * @param list<string> $blocks - the php code blocks.
     * @param array<int, int> $ports - the fake SMTP servers' ports, by the port in the docs they replace.
     * @return string
     */
    private function buildScript(array $blocks, array $ports) : string
    {
        $packageUses = $this->getPackageUseStatements();
        $autoloadPath = var_export($this->m_repositoryDirectory . '/vendor/autoload.php', true);

        // Any warning, notice or deprecation fails the examples, as it does a test.
        $script = "<?php\n\nrequire_once({$autoloadPath});\n\n" . implode("\n", $packageUses) . "\n\n"
            . "\$failOnWarning = function (int \$number, string \$message, string \$file, int \$line) : bool {\n"
            . "    throw new \\ErrorException(\$message, 0, \$number, \$file, \$line);\n};\n\n"
            . "set_error_handler(\$failOnWarning);\n\n"
            . "\$smoke = new \\Programster\\Phpmailer\\Testing\\SmokeTest();\n\n";

        foreach ($blocks as $block)
        {
            $script .= $this->prepareBlock($block, $packageUses, $ports) . "\n\n";
        }

        $script .= "if (count(\$smoke->getFailures()) > 0)\n{\n"
            . "    fwrite(STDERR, implode(\"\\n\", \$smoke->getFailures()) . \"\\n\");\n    exit(1);\n}\n";

        return $script;
    }


    /**
     * Prepare one block to go in the script: remove what the script already has (the opening tag, the autoloader and
     * the package's use statements), point it at the fake SMTP servers, and if it should throw, check that it does.
     *
     * @param string $block - the block's code.
     *
     * @param list<string> $packageUses - the use statements the script already has.
     *
     * @param array<int, int> $ports - the fake SMTP servers' ports, by the port in the docs they replace.
     *
     * @return string
     */
    private function prepareBlock(string $block, array $packageUses, array $ports) : string
    {
        $keptLines = [];

        foreach (explode("\n", $block) as $line)
        {
            $isOpeningTag = (trim($line) === '<?php');
            $isAutoloader = str_contains($line, 'vendor/autoload.php');
            $isPackageUse = in_array(trim($line), $packageUses, true);

            if (!$isOpeningTag && !$isAutoloader && !$isPackageUse)
            {
                $keptLines[] = $line;
            }
        }

        $codeForLocalhost = str_replace("'smtp.example.com'", "'127.0.0.1'", implode("\n", $keptLines));

        // Each port is replaced in one go, and only as a whole number, as a fake server's port can start with another
        // documented port (e.g. 46583 starts with 465), which replacing one at a time would replace again.
        $replacePort = function (array $matches) use ($ports) : string {
            $documentedPort = is_numeric($matches[1]) ? (int) $matches[1] : 0;
            return 'smtpPort: ' . ($ports[$documentedPort] ?? $documentedPort);
        };

        $code = preg_replace_callback('/smtpPort: ([0-9]+)\b/', $replacePort, $codeForLocalhost) ?? $codeForLocalhost;

        if (preg_match('#^\s*// Throws (\S+)#', $code, $matches) === 1)
        {
            $exception = var_export($matches[1], true);

            $code = "\$threw = false;\ntry\n{\n{$code}\n}\ncatch (\\Throwable \$e)\n{\n"
                . "    \$expected = class_exists({$exception}) ? {$exception} "
                . ": 'Programster\\\\Phpmailer\\\\' . {$exception};\n"
                . "    if (!(\$e instanceof \$expected)) { throw \$e; }\n"
                . "    \$threw = true;\n}\n"
                . "if (!\$threw) { throw new \\Exception('Expected ' . {$exception} . ' to be thrown.'); }";
        }

        return $code;
    }


    /**
     * Get a use statement for every class and enum in the package's src/ folder.
     * @return list<string>
     */
    private function getPackageUseStatements() : array
    {
        $uses = [];

        foreach (glob($this->m_repositoryDirectory . '/src/*.php') ?: [] as $path)
        {
            $uses[] = "use Programster\\Phpmailer\\" . basename($path, '.php') . ";";
        }

        sort($uses);
        return $uses;
    }
}
