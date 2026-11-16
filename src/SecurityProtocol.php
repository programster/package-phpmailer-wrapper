<?php

/*
 * An enum for the various security protocols that can be used for the connection to the SMTP emailer.
 */

namespace Programster\Phpmailer;


enum SecurityProtocol: string
{
    case TLS = "tls";
    case SSL = "ssl";
    case NONE = "none";
}
