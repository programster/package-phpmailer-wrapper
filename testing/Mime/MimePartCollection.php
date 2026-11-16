<?php

namespace Programster\Phpmailer\Testing\Mime;

use Ramsey\Collection\AbstractCollection;


/**
 * @extends AbstractCollection<MimePart>
 */
final class MimePartCollection extends AbstractCollection
{
    public function __construct(MimePart ...$parts)
    {
        parent::__construct($parts);
    }


    public function getType() : string
    {
        return MimePart::class;
    }
}
