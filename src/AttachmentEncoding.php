<?php

namespace Programster\Phpmailer;

enum AttachmentEncoding : string
{
    case BASE_64 = 'base64';
    case SEVEN_BIT = '7bit';
    case EIGHT_BIT = '8bit';
    case BINARY = 'binary';
    case QUOTED_PRINTABLE = 'quoted-printable';
}
