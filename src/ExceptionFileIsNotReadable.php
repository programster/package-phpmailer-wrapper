<?php

/*
 * A custom exception to raise if a file, such as an attachment or embedded image, exists but cannot be read.
 * Using a custom exception allows the developer to gracefully handle different erroneous situations at a higher
 * level.
 */

namespace Programster\Phpmailer;

class ExceptionFileIsNotReadable extends \Exception
{
    private readonly string $m_filepath;


    public function __construct(string $filepath)
    {
        $this->m_filepath = $filepath;
        parent::__construct("Cannot read file: {$filepath}");
    }


    # Accessors
    public function getFilepath() : string { return $this->m_filepath; }
}
