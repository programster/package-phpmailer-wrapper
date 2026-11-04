<?php

namespace Programster\Phpmailer;

use Ramsey\Collection\AbstractCollection;


/**
 * @extends AbstractCollection<Contact>
 */
final class ContactCollection extends AbstractCollection
{
    public function __construct(Contact ...$contacts)
    {
        parent::__construct($contacts);
    }


    public function getType() : string
    {
        return Contact::class;
    }
}
