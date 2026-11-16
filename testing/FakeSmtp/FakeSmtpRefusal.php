<?php

/*
 * A reply the fake SMTP server refuses something with, e.g. "550 5.1.1 No such user" for a recipient.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;


final readonly class FakeSmtpRefusal
{
    // What the server refuses, as it looks it up: a FakeSmtpCommand's value (e.g. "DATA"), or "RCPT <address>".
    private string $m_key;
    private string $m_reply;


    /**
     * @param string $key - what's refused, as the server looks it up. Made by the creator methods.
     * @param string $reply - the reply, which must start with a 4xx or 5xx code, e.g. "550 5.1.1 No such user".
     * @throws \LogicException - if the reply isn't a refusal.
     */
    private function __construct(string $key, string $reply)
    {
        if (preg_match('/^[45][0-9]{2}( |$)/', $reply) !== 1 || str_contains($reply, "\n"))
        {
            throw new \LogicException("A refusal's reply must be one line starting with a 4xx or 5xx code: {$reply}");
        }

        $this->m_key = $key;
        $this->m_reply = $reply;
    }


    /**
     * Create a refusal of one recipient (RCPT TO), e.g. as an address that doesn't exist.
     * @param string $address - the recipient's email address, in any case.
     * @param string $reply - the reply, e.g. "550 5.1.1 No such user".
     * @return FakeSmtpRefusal
     */
    public static function createRecipientRefusal(string $address, string $reply) : FakeSmtpRefusal
    {
        return new FakeSmtpRefusal(self::createRecipientKey($address), $reply);
    }


    /**
     * Create a refusal of a command, e.g. DATA, which is refused once the whole message has been sent.
     * @param FakeSmtpCommand $command - the command to refuse.
     * @param string $reply - the reply, e.g. "554 5.7.1 Rejected as spam".
     * @return FakeSmtpRefusal
     */
    public static function createCommandRefusal(FakeSmtpCommand $command, string $reply) : FakeSmtpRefusal
    {
        return new FakeSmtpRefusal($command->value, $reply);
    }


    /**
     * Get the key the server looks a recipient's refusal up by.
     * @param string $address - the recipient's email address, in any case.
     * @return string
     */
    public static function createRecipientKey(string $address) : string
    {
        return 'RCPT ' . strtolower($address);
    }


    # Accessors
    public function getKey() : string { return $this->m_key; }
    public function getReply() : string { return $this->m_reply; }
}
