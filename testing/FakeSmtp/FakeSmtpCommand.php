<?php

/*
 * The SMTP commands (other than RCPT TO, which is refused per address) the fake SMTP server can be told to refuse.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;


enum FakeSmtpCommand : string
{
    case MAIL_FROM = 'MAIL';
    case DATA = 'DATA';
    case AUTH = 'AUTH';
    case STARTTLS = 'STARTTLS';
}
