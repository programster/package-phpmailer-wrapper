<?php

/*
 * The settings for the opt-in live test (live-test.php), which sends real emails through a real SMTP server. They're
 * in testing/settings.php, which is git-ignored, and returns one of these (copy settings.php.tmpl to make it).
 */

namespace Programster\Phpmailer\Testing;

use Programster\Phpmailer\Contact;
use Programster\Phpmailer\ContactCollection;
use Programster\Phpmailer\SecurityProtocol;


final readonly class LiveTestSettings
{
    private string $m_smtpHost;
    private string $m_smtpUser;
    private string $m_smtpPassword;
    private SecurityProtocol $m_securityProtocol;
    private int $m_smtpPort;
    private Contact $m_from;
    private Contact $m_replyTo;
    private ContactCollection $m_to;
    private ContactCollection $m_cc;
    private ContactCollection $m_bcc;


    /**
     * @param string $smtpHost - the SMTP server's host.
     *
     * @param string $smtpUser - the username to log in with.
     *
     * @param string $smtpPassword - the password to log in with.
     *
     * @param SecurityProtocol $securityProtocol - the security protocol.
     *
     * @param int $smtpPort - the port.
     *
     * @param Contact $from - who the test emails are from.
     *
     * @param Contact $replyTo - where replies to them go.
     *
     * @param ContactCollection $to - who they're sent to: your own mailboxes, as you'll need to check they arrived.
     *
     * @param ContactCollection $cc - who they're CC'd to (it can be empty).
     *
     * @param ContactCollection $bcc - who they're BCC'd to (it can be empty).
     *
     * @param bool $isTestAccount - true to confirm the SMTP account is one for testing (e.g. a mailbox of your own, or
     * a sandbox such as Mailtrap), rather than one a live site sends its emails through, and that every recipient is
     * a mailbox of your own. The test refuses to run until this is true.
     *
     * @throws \LogicException - if the account isn't confirmed to be for testing, or there's no one to send to.
     */
    public function __construct(
        string $smtpHost,
        string $smtpUser,
        string $smtpPassword,
        SecurityProtocol $securityProtocol,
        int $smtpPort,
        Contact $from,
        Contact $replyTo,
        ContactCollection $to,
        ContactCollection $cc,
        ContactCollection $bcc,
        bool $isTestAccount
    )
    {
        if ($isTestAccount !== true)
        {
            throw new \LogicException(
                "The live test only runs against an SMTP account for testing, and sends only to your own mailboxes. "
                . "Confirm that it is, with isTestAccount: true in testing/settings.php."
            );
        }

        if ($smtpHost === '' || count($to) === 0)
        {
            throw new \LogicException("testing/settings.php needs an SMTP host, and at least one contact in \$to.");
        }

        $this->m_smtpHost = $smtpHost;
        $this->m_smtpUser = $smtpUser;
        $this->m_smtpPassword = $smtpPassword;
        $this->m_securityProtocol = $securityProtocol;
        $this->m_smtpPort = $smtpPort;
        $this->m_from = $from;
        $this->m_replyTo = $replyTo;
        $this->m_to = $to;
        $this->m_cc = $cc;
        $this->m_bcc = $bcc;
    }


    # Accessors
    public function getSmtpHost() : string { return $this->m_smtpHost; }
    public function getSmtpUser() : string { return $this->m_smtpUser; }
    public function getSmtpPassword() : string { return $this->m_smtpPassword; }
    public function getSecurityProtocol() : SecurityProtocol { return $this->m_securityProtocol; }
    public function getSmtpPort() : int { return $this->m_smtpPort; }
    public function getFrom() : Contact { return $this->m_from; }
    public function getReplyTo() : Contact { return $this->m_replyTo; }
    public function getTo() : ContactCollection { return clone $this->m_to; }
    public function getCc() : ContactCollection { return clone $this->m_cc; }
    public function getBcc() : ContactCollection { return clone $this->m_bcc; }
}
