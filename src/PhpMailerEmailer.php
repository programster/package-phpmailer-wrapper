<?php

/*
 * This class wraps around PHPMailer to make it easier to send emails using SMTP.
 */

namespace Programster\Phpmailer;

use PHPMailer\PHPMailer\PHPMailer;


readonly class PhpMailerEmailer
{
    private string $m_smtpHost;
    private string $m_smtpUser;
    private string $m_smtpPassword;
    private SecurityProtocol $m_securityProtocol;
    private Contact $m_from;
    private int $m_smtpPort;
    private Contact $m_replyTo;
    private string $m_charset;


    /**
     * Create an emailer object that wraps around PhpMailer to send an email through SMTP.
     *
     * @param string $smtpHost - the host of the SMTP provider.
     *
     * @param string $smtpUser - username or email address for authenticating with SMTP provider.
     *
     * @param string $smtpPassword - password for authenticating with SMTP provider.
     *
     * @param SecurityProtocol $securityProtocol - the security protocol to connect to the SMTP provider with. This
     * should usually be SecurityProtocol::TLS. Use SecurityProtocol::NONE for local testing tools such as MailHog.
     * SecurityProtocol::NONE is strict, so the connection will not be upgraded to TLS even if the server offers it.
     *
     * @param Contact $from - the name/email that the email should appear as if it is from.
     *
     * @param int $smtpPort - the port to connect to the SMTP provider with (defaults to 587)
     *
     * @param ?Contact $replyTo - the name and email of the reply to address. Leave as null for this to default to
     * the from address.
     *
     * @param string $charset - the character encoding to send emails with. Defaults to UTF-8. PHPMailer provides
     * constants for common values, such as PHPMailer::CHARSET_UTF8 and PHPMailer::CHARSET_ISO88591.
     */
    public function __construct(
        string $smtpHost,
        string $smtpUser,
        string $smtpPassword,
        SecurityProtocol $securityProtocol,
        Contact $from,
        int $smtpPort = 587,
        ?Contact $replyTo = null,
        string $charset = PHPMailer::CHARSET_UTF8,
    )
    {
        $this->m_smtpHost = $smtpHost;
        $this->m_smtpUser = $smtpUser;
        $this->m_smtpPassword = $smtpPassword;
        $this->m_securityProtocol = $securityProtocol;
        $this->m_from = $from;
        $this->m_smtpPort = $smtpPort;
        $this->m_replyTo = $replyTo ?? $from;
        $this->m_charset = $charset;
    }


    /**
     * Send an email.
     *
     * @param string $subject - subject of the email
     *
     * @param string $plaintextMessage - the plaintext body of the email. If an HTML message is also provided, this
     * is used as the alternative body for recipients that cannot view HTML emails.
     *
     * @param ?string $htmlMessage - the HTML body of the email, or null to send a plaintext-only email.
     *
     * @param ?ContactCollection $to - the contacts to send the email to.
     *
     * @param ?ContactCollection $cc - the contacts to CC the email to.
     *
     * @param ?ContactCollection $bcc - the contacts to BCC the email to.
     *
     * @param ?AttachmentCollection $attachments - files to attach to the email.
     *
     * @param ?EmbeddedImageCollection $images - images to embed in the email, which the HTML message can reference
     * by their cid (e.g. <img src="cid:my-image">).
     *
     * @return void
     *
     * @throws ExceptionFailedToSendEmail - if there are no recipients, or failed to send the email for whatever
     * reason. When the SMTP server refused it, the exception has the server's reply code, and isPermanentFailure()
     * says whether sending it again could work.
     *
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function send(
        string $subject,
        string $plaintextMessage,
        ?string $htmlMessage,
        ?ContactCollection $to,
        ?ContactCollection $cc = null,
        ?ContactCollection $bcc = null,
        ?AttachmentCollection $attachments = null,
        ?EmbeddedImageCollection $images = null
    ) : void
    {
        $mailer = new PHPMailer();

        // Keeps the server's reply to whatever it refuses, which PHPMailer itself clears before send() returns.
        $smtp = new ReplyRecordingSmtp();
        $mailer->setSMTPInstance($smtp);
        $mailer->CharSet = $this->m_charset;
        $mailer->isSMTP();
        $mailer->Host = $this->m_smtpHost;
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->m_smtpUser;
        $mailer->Password = $this->m_smtpPassword;

        if ($this->m_securityProtocol === SecurityProtocol::NONE)
        {
            // Stop PHPMailer from opportunistically upgrading to TLS if the server advertises STARTTLS.
            $mailer->SMTPAutoTLS = false;
        }
        else
        {
            $mailer->SMTPSecure = $this->m_securityProtocol->value;
        }

        $mailer->Port = $this->m_smtpPort;
        $mailer->setFrom($this->m_from->getEmail(), $this->m_from->getName());
        $mailer->addReplyTo($this->m_replyTo->getEmail(), $this->m_replyTo->getName());
        $mailer->Subject = $subject;

        if ($htmlMessage !== null)
        {
            $mailer->Body = $htmlMessage;
            $mailer->AltBody = strip_tags($plaintextMessage);
            $mailer->isHTML(true);
        }
        else
        {
            $mailer->Body = $plaintextMessage;
            $mailer->isHTML(false);
        }

        $hasRecipients = false;

        if ($to !== null && count($to) > 0)
        {
            $hasRecipients = true;

            foreach ($to as $toContact)
            {
                $mailer->addAddress($toContact->getEmail(), $toContact->getName());
            }
        }

        if ($cc !== null && count($cc) > 0)
        {
            $hasRecipients = true;

            foreach ($cc as $ccContact)
            {
                $mailer->addCC($ccContact->getEmail(), $ccContact->getName());
            }
        }

        if ($bcc !== null && count($bcc) > 0)
        {
            $hasRecipients = true;

            foreach ($bcc as $bccContact)
            {
                $mailer->addBCC($bccContact->getEmail(), $bccContact->getName());
            }
        }

        if ($attachments !== null && count($attachments) > 0)
        {
            foreach ($attachments as $attachment)
            {
                $mailer->addAttachment(
                    $attachment->getFilepath(),
                    $attachment->getName(),
                    $attachment->getEncoding()->value,
                    $attachment->getMimetype()
                );
            }
        }

        if ($images !== null && count($images) > 0)
        {
            foreach ($images as $image)
            {
                $mailer->addEmbeddedImage($image->getFilepath(), $image->getCid());
            }
        }

        if ($hasRecipients === false)
        {
            throw new ExceptionFailedToSendEmail("Cannot send email when there are no configured recipients.");
        }

        if ($mailer->send() === false)
        {
            // The server's reply to what it refused says whether that's permanent (5xx) or for now (4xx). There's
            // none if the server couldn't be reached.
            throw new ExceptionFailedToSendEmail(
                "Failed to send email: " . $mailer->ErrorInfo,
                $smtp->getLastRefusalCode(),
                $smtp->getLastRefusalExtendedCode()
            );
        }
    }
}
