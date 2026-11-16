<?php

/*
 * How the fake SMTP server handles encryption, so the tests can check what each SecurityProtocol does.
 */

namespace Programster\Phpmailer\Testing\FakeSmtp;


enum FakeSmtpServerMode : string
{
    // Plain SMTP, which doesn't offer STARTTLS, and refuses it (502) if it's sent anyway.
    case NO_TLS = 'no-tls';

    // Plain SMTP, which offers STARTTLS (as most servers on port 587 do), and upgrades to TLS if it's sent.
    case STARTTLS = 'starttls';

    // TLS from the moment the connection is made (as servers on port 465 do), before any SMTP is spoken.
    case IMPLICIT_TLS = 'implicit-tls';
}
