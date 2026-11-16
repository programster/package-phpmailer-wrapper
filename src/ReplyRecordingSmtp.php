<?php

/*
 * PHPMailer's SMTP client, which also keeps the reply to the last SMTP command the server refused. PHPMailer clears
 * its SMTP error after every command that works, and when it closes the connection, which it does before saying a
 * recipient was refused, so by the time a send has failed, the server's reply code (e.g. 550 for an address that
 * doesn't exist) is usually gone. PhpMailerEmailer uses this to put the reply code in ExceptionFailedToSendEmail.
 *
 * @internal - used by PhpMailerEmailer only.
 */

namespace Programster\Phpmailer;

use PHPMailer\PHPMailer\SMTP;

final class ReplyRecordingSmtp extends SMTP
{
    private ?int $m_lastRefusalCode = null;
    private ?string $m_lastRefusalExtendedCode = null;


    /**
     * Send a command, as PHPMailer's SMTP client does, keeping the reply if the server refuses it.
     * @param string $command - the command's name, for errors.
     * @param string $commandstring - the command to send.
     * @param int|array<int> $expect - the reply code(s) that mean it worked.
     * @return bool - whether it worked.
     */
    protected function sendCommand($command, $commandstring, $expect)
    {
        $worked = parent::sendCommand($command, $commandstring, $expect);

        if ($worked === false)
        {
            $error = $this->getError();
            $rawCode = $error['smtp_code'] ?? "";
            $rawExtendedCode = $error['smtp_code_ex'] ?? "";
            $code = is_scalar($rawCode) ? trim((string) $rawCode) : "";
            $extendedCode = is_scalar($rawExtendedCode) ? trim((string) $rawExtendedCode) : "";

            // A command that never reached the server (e.g. it isn't connected) has no reply, so is left out.
            if (preg_match('/^[0-9]{3}$/', $code) === 1)
            {
                $this->m_lastRefusalCode = (int) $code;
                $this->m_lastRefusalExtendedCode = ($extendedCode === "") ? null : $extendedCode;
            }
        }

        return $worked;
    }


    # Accessors
    public function getLastRefusalCode() : ?int { return $this->m_lastRefusalCode; }
    public function getLastRefusalExtendedCode() : ?string { return $this->m_lastRefusalExtendedCode; }
}
