<?php

namespace Programster\Phpmailer;

use Ramsey\Collection\AbstractCollection;


/**
 * @extends AbstractCollection<EmbeddedImage>
 */
final class EmbeddedImageCollection extends AbstractCollection
{
    public function __construct(EmbeddedImage ...$images)
    {
        parent::__construct($images);
    }


    public function getType() : string
    {
        return EmbeddedImage::class;
    }
}
