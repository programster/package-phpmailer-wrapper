<?php

namespace Programster\Phpmailer;


readonly class EmbeddedImage
{
    private string $m_filepath;
    private string $m_cid;


    /**
     * Create an image to embed within an email.
     * @param string $filepath - the path to the image file to embed.
     * @param ?string $cid - optionally specify the cid for the image. This is how the image is referenced within
     * the content of the body of the email (e.g. <img src="cid:my-image">). If left as null, the name of the file
     * will be used.
     * @throws ExceptionFileDoesNotExist - if there is no file at $filepath
     * @throws ExceptionFileIsNotReadable - if the file at $filepath cannot be read to embed in the email.
     */
    public function __construct(
        string $filepath,
        ?string $cid = null
    )
    {
        if (file_exists($filepath) === false)
        {
            throw new ExceptionFileDoesNotExist($filepath);
        }

        if (is_readable($filepath) === false)
        {
            throw new ExceptionFileIsNotReadable($filepath);
        }

        $this->m_filepath = $filepath;
        $this->m_cid = $cid ?? (string) pathinfo($filepath, PATHINFO_BASENAME);
    }


    public function getFilepath() : string
    {
        return $this->m_filepath;
    }


    public function getCid() : string
    {
        return $this->m_cid;
    }
}
