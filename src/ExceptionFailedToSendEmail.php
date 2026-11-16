<?php

/*
 * Thrown when an email can't be sent. When the SMTP server refused it, it carries the server's reply code (e.g. 550)
 * and enhanced status code (e.g. "5.1.1"), so the caller can tell a permanent refusal (a 5xx reply, e.g. an address
 * that doesn't exist), which will fail again however often it's retried, from a temporary one (a 4xx reply, e.g. the
 * mailbox is full or the server is busy), or a failure to reach the server at all (no reply), which may work later.
 */

namespace Programster\Phpmailer;

class ExceptionFailedToSendEmail extends \Exception
{
    private readonly ?int $m_smtpReplyCode;
    private readonly ?string $m_smtpExtendedCode;


    /**
     * @param string $message - what went wrong.
     *
     * @param int|null $smtpReplyCode - the SMTP server's reply code for the refusal (e.g. 550), or null if there was
     * no reply, e.g. the server couldn't be reached, or the email wasn't sent to a server at all (e.g. it had no
     * recipients).
     *
     * @param string|null $smtpExtendedCode - the server's enhanced status code (e.g. "5.1.1"), if it sent one.
     *
     * @param \Throwable|null $previous - the exception that caused this one, if any.
     */
    public function __construct(
        string $message,
        ?int $smtpReplyCode = null,
        ?string $smtpExtendedCode = null,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, 0, $previous);
        $this->m_smtpReplyCode = $smtpReplyCode;
        $this->m_smtpExtendedCode = $smtpExtendedCode;
    }


    /**
     * Find out whether the SMTP server refused the email permanently (a 5xx reply), so sending it again won't work.
     * A temporary refusal (4xx), or no reply at all (e.g. the server couldn't be reached), isn't permanent.
     *
     * @return bool
     */
    public function isPermanentFailure() : bool
    {
        return ($this->m_smtpReplyCode !== null && $this->m_smtpReplyCode >= 500 && $this->m_smtpReplyCode <= 599);
    }


    # Accessors
    public function getSmtpReplyCode() : ?int { return $this->m_smtpReplyCode; }
    public function getSmtpExtendedCode() : ?string { return $this->m_smtpExtendedCode; }
}
