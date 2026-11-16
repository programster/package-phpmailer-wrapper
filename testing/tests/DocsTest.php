<?php

/*
 * Runs the examples in the README and every page in docs/, sending their emails to fake SMTP servers, so the
 * documentation can't drift from what the code does. These are run by testing/main.php, which provides $smoke. See
 * DocsExampleRunner for how a document's examples run.
 */

use Programster\Phpmailer\Testing\DocsExampleRunner;
use Programster\Phpmailer\Testing\SmokeTest;
use Programster\Phpmailer\Testing\TestFixtures;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$repositoryDirectory = dirname(__DIR__, 2);
$docsRunner = new DocsExampleRunner($repositoryDirectory);
$documents = array_merge([$repositoryDirectory . '/README.md'], glob($repositoryDirectory . '/docs/*.md') ?: []);

foreach ($documents as $document)
{
    if ($docsRunner->countExamples($document) > 0)
    {
        $runExamples = function () use ($docsRunner, $document) : void {
            $numConnections = $docsRunner->run($document);
            # Examples that send to smtp.example.com send to the runner's fake SMTP servers, so check they did.
            $sendsEmails = str_contains(TestFixtures::readFile($document), "smtpHost: 'smtp.example.com'");
            SmokeTest::assertSame($sendsEmails, $numConnections > 0, "whether the examples sent to the fake servers");
        };

        $name = basename(dirname($document)) . "/" . basename($document);
        $smoke->run("docs: the examples in {$name}", $runExamples);
    }
}
