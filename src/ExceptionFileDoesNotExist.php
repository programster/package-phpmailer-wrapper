<?php

/*
 * A custom exception to raise if a file, such as an attachment or embedded image, does not exist.
 * Using a custom exception allows the developer to gracefully handle different erroneous situations at a higher
 * level.
 */

namespace Programster\Phpmailer;

class ExceptionFileDoesNotExist extends \Exception
{
    private readonly string $m_filepath;


    public function __construct(string $filepath)
    {
        $this->m_filepath = $filepath;
        parent::__construct("File does not exist: {$filepath}");
    }


    # Accessors
    public function getFilepath() : string { return $this->m_filepath; }
}
