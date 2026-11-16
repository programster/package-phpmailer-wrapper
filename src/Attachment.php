<?php

/*
 * A file to attach to an email.
 */

namespace Programster\Phpmailer;


readonly class Attachment
{
    private string $m_filepath;
    private string $m_name;
    private AttachmentEncoding $m_encoding;
    private string $m_mimetype;


    /**
     * Create an attachment for an email.
     *
     * @param string $filepath - the path to the file to add as an attachment.
     *
     * @param ?string $name - optionally specify a name for the attachment. If left as null, the name of the file
     * will be used.
     *
     * @param AttachmentEncoding $encoding - the encoding of the attachment.
     *
     * @param string|null $mimetype - the mimetype of the attached file. E.g. "application/pdf". If left as null,
     * this will try to be automatically determined (from the file's extension), and getMimetype() returns an empty
     * string.
     *
     * @throws ExceptionFileDoesNotExist - if there is no file at $filepath (including if it's a directory).
     * @throws ExceptionFileIsNotReadable - if the file at $filepath cannot be read to add as an attachment.
     */
    public function __construct(
        string $filepath,
        ?string $name = null,
        AttachmentEncoding $encoding = AttachmentEncoding::BASE_64,
        ?string $mimetype = null
    )
    {
        // A directory isn't a file, which PHPMailer would leave out of the email without saying so.
        if (is_file($filepath) === false)
        {
            throw new ExceptionFileDoesNotExist($filepath);
        }

        if (is_readable($filepath) === false)
        {
            throw new ExceptionFileIsNotReadable($filepath);
        }

        $this->m_filepath = $filepath;
        $this->m_name = $name ?? pathinfo($filepath, PATHINFO_BASENAME);
        $this->m_encoding = $encoding;
        $this->m_mimetype = $mimetype ?? '';
    }


    # Accessors
    public function getFilepath() : string { return $this->m_filepath; }
    public function getName() : string { return $this->m_name; }
    public function getEncoding() : AttachmentEncoding { return $this->m_encoding; }
    public function getMimetype() : string { return $this->m_mimetype; }
}
