<?php

/*
 * The result of sending an email with PhpMailerEmailer in another PHP process (send-in-child.php), which trusts the
 * fake SMTP server's self-signed certificate, so the tests can check that TLS works with a certificate the client can
 * verify. The certificate is trusted with PHP's openssl.cafile setting, which can only be set as PHP starts, as
 * PhpMailerEmailer (rightly) has no way to switch off verifying it.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;

use Programster\Phpmailer\SecurityProtocol;


final readonly class ChildProcessSendResult
{
    private bool $m_sent;
    private string $m_message;
    private ?int $m_replyCode;


    private function __construct(bool $sent, string $message, ?int $replyCode)
    {
        $this->m_sent = $sent;
        $this->m_message = $message;
        $this->m_replyCode = $replyCode;
    }


    /**
     * Send an email to the fake SMTP server, as 127.0.0.1 (the address its certificate is for), trusting its
     * certificate, and get the result.
     *
     * @param FakeSmtpServer $server - the server to send to.
     *
     * @param SecurityProtocol $protocol - the security protocol to send with.
     *
     * @return ChildProcessSendResult - the result: whether it was sent, and if not, the exception's message and code.
     *
     * @throws \RuntimeException - if the child process doesn't give a result.
     */
    public static function createBySendingTrustingCertificate(
        FakeSmtpServer $server,
        SecurityProtocol $protocol
    ) : ChildProcessSendResult
    {
        $settings = json_encode(
            ['host' => '127.0.0.1', 'port' => $server->getPort(), 'protocol' => $protocol->value],
            JSON_THROW_ON_ERROR
        );

        $command = escapeshellarg(PHP_BINARY)
            . ' -d ' . escapeshellarg('openssl.cafile=' . $server->getCertificatePath())
            . ' ' . escapeshellarg(__DIR__ . '/send-in-child.php')
            . ' ' . escapeshellarg($settings) . ' 2>&1';

        exec($command, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);
        $result = json_decode($output, true);
        $sent = is_array($result) ? ($result['sent'] ?? null) : null;
        $message = is_array($result) ? ($result['message'] ?? null) : null;
        $replyCode = is_array($result) ? ($result['replyCode'] ?? null) : null;

        if ($exitCode !== 0 || !is_bool($sent) || !is_string($message) || !(is_int($replyCode) || $replyCode === null))
        {
            throw new \RuntimeException("Sending in a child process gave no result (exit code {$exitCode}): {$output}");
        }

        return new ChildProcessSendResult($sent, $message, $replyCode);
    }


    # Accessors
    public function wasSent() : bool { return $this->m_sent; }
    public function getMessage() : string { return $this->m_message; }
    public function getReplyCode() : ?int { return $this->m_replyCode; }
}
