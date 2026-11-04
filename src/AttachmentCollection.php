<?php

namespace Programster\Phpmailer;

use Ramsey\Collection\AbstractCollection;


/**
 * @extends AbstractCollection<Attachment>
 */
final class AttachmentCollection extends AbstractCollection
{
    public function __construct(Attachment ...$attachments)
    {
        parent::__construct($attachments);
    }


    public function getType() : string
    {
        return Attachment::class;
    }
}
