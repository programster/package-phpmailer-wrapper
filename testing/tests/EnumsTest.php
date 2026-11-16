<?php

/*
 * Smoke tests for SecurityProtocol and AttachmentEncoding: that each case is a value PHPMailer understands. These are
 * run by testing/main.php, which provides $smoke. What each security protocol does is tested in SecurityTest.php.
 */

use PHPMailer\PHPMailer\PHPMailer;
use Programster\Phpmailer\AttachmentEncoding;
use Programster\Phpmailer\SecurityProtocol;
use Programster\Phpmailer\Testing\SmokeTest;

/** @var \Programster\Phpmailer\Testing\SmokeTest $smoke */

$testSecurityProtocols = function () : void {
    SmokeTest::assertSame(PHPMailer::ENCRYPTION_STARTTLS, SecurityProtocol::TLS->value, "TLS is PHPMailer's STARTTLS");
    SmokeTest::assertSame(PHPMailer::ENCRYPTION_SMTPS, SecurityProtocol::SSL->value, "SSL is PHPMailer's SMTPS");
    SmokeTest::assertSame(3, count(SecurityProtocol::cases()), "the number of protocols, so a new one gets a test");
};

$testAttachmentEncodings = function () : void {
    $phpMailerEncodings = [
        PHPMailer::ENCODING_BASE64,
        PHPMailer::ENCODING_7BIT,
        PHPMailer::ENCODING_8BIT,
        PHPMailer::ENCODING_BINARY,
        PHPMailer::ENCODING_QUOTED_PRINTABLE,
    ];

    $caseValues = [];

    foreach (AttachmentEncoding::cases() as $encoding)
    {
        $caseValues[] = $encoding->value;
    }

    sort($phpMailerEncodings);
    sort($caseValues);
    SmokeTest::assertSame($phpMailerEncodings, $caseValues, "the encodings are exactly PHPMailer's");
};

$smoke->run("enums: each security protocol is one PHPMailer understands", $testSecurityProtocols);
$smoke->run("enums: each attachment encoding is one PHPMailer understands", $testAttachmentEncodings);
