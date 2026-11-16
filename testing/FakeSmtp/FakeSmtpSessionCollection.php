<?php

namespace Programster\Phpmailer\Testing\FakeSmtp;

use Ramsey\Collection\AbstractCollection;


/**
 * @extends AbstractCollection<FakeSmtpSession>
 */
final class FakeSmtpSessionCollection extends AbstractCollection
{
    public function __construct(FakeSmtpSession ...$sessions)
    {
        parent::__construct($sessions);
    }


    public function getType() : string
    {
        return FakeSmtpSession::class;
    }
}
