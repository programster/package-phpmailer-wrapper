<?php

/*
 * Starts the fake SMTP server in this process. Run by FakeSmtpServer, with the path to its config file:
 * php serve.php <config.json>
 */

use Programster\Phpmailer\Testing\FakeSmtp\FakeSmtpServerProcess;

require_once(__DIR__ . '/../../vendor/autoload.php');

$configPath = $argv[1] ?? '';
$config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($config))
{
    fwrite(STDERR, "The fake SMTP server's config in {$configPath} isn't valid.\n");
    exit(1);
}

$server = new FakeSmtpServerProcess($config);
$server->serve();
