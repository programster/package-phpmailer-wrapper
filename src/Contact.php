<?php

/*
 * A contact to send an email to, or from: an email address, and optionally a name.
 */

namespace Programster\Phpmailer;


readonly class Contact
{
    private string $m_email;
    private string $m_name;


    /**
     * Create a contact to send an email to, or from.
     *
     * @param string $email - the email address of the contact.
     *
     * @param ?string $name - the name of the contact, or null if the contact has no name, in which case getName()
     * returns an empty string.
     */
    public function __construct(string $email, ?string $name)
    {
        $this->m_email = $email;
        $this->m_name = $name ?? '';
    }


    # Accessors
    public function getEmail() : string { return $this->m_email; }
    public function getName() : string { return $this->m_name; }
}
