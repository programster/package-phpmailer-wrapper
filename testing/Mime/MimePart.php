<?php

/*
 * A parsed email message, or one part of a multipart one, so the tests can check what was sent: its headers, the
 * decoded content of each part, and the parts inside a multipart one. It understands just enough MIME for what
 * PHPMailer sends, and isn't a general purpose parser.
 */

namespace Programster\Phpmailer\Testing\Mime;


final readonly class MimePart
{
    /** @var list<array{0: string, 1: string}> - each header's name and (unfolded) value, in order */
    private array $m_headers;
    private string $m_rawBody;
    private MimePartCollection $m_parts;


    /**
     * @param list<array{0: string, 1: string}> $headers - each header's name and value.
     * @param string $rawBody - the body, as sent (e.g. still in base64).
     * @param MimePartCollection $parts - the parts inside it, if it's multipart.
     */
    private function __construct(array $headers, string $rawBody, MimePartCollection $parts)
    {
        $this->m_headers = $headers;
        $this->m_rawBody = $rawBody;
        $this->m_parts = $parts;
    }


    /**
     * Parse a message (or part of one), with its headers, a blank line, then its body.
     * @param string $raw - the message, with "\r\n" line endings.
     * @return MimePart
     */
    public static function createFromString(string $raw) : MimePart
    {
        $sections = explode("\r\n\r\n", $raw, 2);
        $headers = [];

        // A line that starts with a space or tab continues the header above it.
        $unfolded = preg_replace('/\r\n[ \t]+/', ' ', $sections[0]) ?? $sections[0];

        foreach (explode("\r\n", $unfolded) as $line)
        {
            $nameAndValue = explode(':', $line, 2);

            if (count($nameAndValue) === 2)
            {
                $headers[] = [trim($nameAndValue[0]), trim($nameAndValue[1])];
            }
        }

        $rawBody = $sections[1] ?? '';
        $parts = new MimePartCollection();
        $matchedBoundary = preg_match('/boundary="?([^";]+)"?/i', self::findHeader($headers, 'Content-Type'), $matches);

        if ($matchedBoundary === 1)
        {
            // The parts are between the "--boundary" lines, and the last ends at "--boundary--". The line break before
            // each boundary is part of the boundary, rather than of the part before it.
            $sectionsByBoundary = explode("\r\n--{$matches[1]}", "\r\n" . $rawBody);

            foreach (array_slice($sectionsByBoundary, 1) as $section)
            {
                if (!str_starts_with($section, '--'))
                {
                    $parts->add(self::createFromString(substr($section, 2)));
                }
            }
        }

        return new MimePart($headers, $rawBody, $parts);
    }


    /**
     * Get a header's value, e.g. "text/plain; charset=utf-8" for Content-Type.
     * @param string $name - the header's name, in any case.
     * @return string - its value, or an empty string if there's no such header.
     */
    public function getHeader(string $name) : string
    {
        return self::findHeader($this->m_headers, $name);
    }


    /**
     * Get a header's value, decoding any encoded words (e.g. "=?utf-8?B?...?="), as used for text that isn't ASCII.
     * @param string $name - the header's name, in any case.
     * @return string - its value, or an empty string if there's no such header.
     */
    public function getDecodedHeader(string $name) : string
    {
        return mb_decode_mimeheader($this->getHeader($name));
    }


    /**
     * Find out whether the part has a header.
     * @param string $name - the header's name, in any case.
     * @return bool
     */
    public function hasHeader(string $name) : bool
    {
        $found = false;

        foreach ($this->m_headers as $header)
        {
            $found = $found || strcasecmp($header[0], $name) === 0;
        }

        return $found;
    }


    /**
     * Get the part's content type, without its parameters, in lower case, e.g. "multipart/mixed".
     * @return string
     */
    public function getContentType() : string
    {
        return strtolower(trim(explode(';', $this->getHeader('Content-Type'))[0]));
    }


    /**
     * Get one of the content type's parameters, e.g. "charset", without any quotes.
     * @param string $name - the parameter's name.
     * @return string - its value, or an empty string if it hasn't got one.
     */
    public function getContentTypeParameter(string $name) : string
    {
        $pattern = '/;\s*' . preg_quote($name, '/') . '="?([^";]*)"?/i';
        $matched = preg_match($pattern, $this->getHeader('Content-Type'), $matches);
        return ($matched === 1) ? $matches[1] : '';
    }


    /**
     * Get the body, decoded from its Content-Transfer-Encoding (e.g. base64), so it's the bytes that were encoded.
     * @return string
     */
    public function getDecodedBody() : string
    {
        $encoding = strtolower($this->getHeader('Content-Transfer-Encoding'));

        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $this->m_rawBody) ?? '', true),
            'quoted-printable' => quoted_printable_decode($this->m_rawBody),
            default => $this->m_rawBody,
        };
    }


    /**
     * Get the body as text: decoded, and without the line endings PHPMailer adds at the end (which differ between
     * a part, and a message that isn't multipart).
     *
     * @return string
     */
    public function getDecodedText() : string
    {
        return rtrim($this->getDecodedBody(), "\r\n");
    }


    /**
     * Find the parts of a type, searching every part inside this one (and this one itself), however deeply nested.
     * @param string $contentType - the type, e.g. "text/html".
     * @return MimePartCollection
     */
    public function findPartsOfType(string $contentType) : MimePartCollection
    {
        $isOfType = function (MimePart $part) use ($contentType) : bool {
            return $part->getContentType() === strtolower($contentType);
        };

        return $this->findParts($isOfType);
    }


    /**
     * Find the only part of a type, however deeply nested, throwing if there isn't exactly one.
     * @param string $contentType - the type, e.g. "text/html".
     * @return MimePart
     * @throws \LogicException - if there isn't exactly one.
     */
    public function findOnlyPartOfType(string $contentType) : MimePart
    {
        $parts = $this->findPartsOfType($contentType);

        if (count($parts) !== 1)
        {
            throw new \LogicException("Expected one {$contentType} part, but there were " . count($parts));
        }

        return $parts->first();
    }


    /**
     * Find the part whose Content-Disposition gives this filename (e.g. an attachment), however deeply nested.
     * @param string $filename - the filename.
     * @return MimePart
     * @throws \LogicException - if there isn't exactly one.
     */
    public function findPartWithFilename(string $filename) : MimePart
    {
        $hasFilename = function (MimePart $part) use ($filename) : bool {
            $disposition = $part->getHeader('Content-Disposition');
            return str_contains($disposition, "filename={$filename}") || str_contains($disposition, "\"{$filename}\"");
        };

        $parts = $this->findParts($hasFilename);

        if (count($parts) !== 1)
        {
            $numParts = count($parts);
            throw new \LogicException("Expected one part with the filename {$filename}, but there were {$numParts}");
        }

        return $parts->first();
    }


    /**
     * Find the part with this Content-ID (e.g. an embedded image), however deeply nested.
     * @param string $contentId - the ID, without the angle brackets it's sent in.
     * @return MimePart
     * @throws \LogicException - if there isn't exactly one.
     */
    public function findPartWithContentId(string $contentId) : MimePart
    {
        $hasContentId = function (MimePart $part) use ($contentId) : bool {
            return $part->getHeader('Content-ID') === "<{$contentId}>";
        };

        $parts = $this->findParts($hasContentId);

        if (count($parts) !== 1)
        {
            $numParts = count($parts);
            throw new \LogicException("Expected one part with the Content-ID {$contentId}, but there were {$numParts}");
        }

        return $parts->first();
    }


    /**
     * Find the parts a callback matches, out of this part and every part inside it, however deeply nested.
     * @param callable(MimePart): bool $matches - whether a part is one to find.
     * @return MimePartCollection
     */
    private function findParts(callable $matches) : MimePartCollection
    {
        $found = new MimePartCollection();

        foreach ($this->getAllParts() as $part)
        {
            if ($matches($part))
            {
                $found->add($part);
            }
        }

        return $found;
    }


    /**
     * Get this part, and every part inside it, however deeply nested.
     * @return MimePartCollection
     */
    private function getAllParts() : MimePartCollection
    {
        $allParts = new MimePartCollection($this);

        foreach ($this->m_parts as $part)
        {
            foreach ($part->getAllParts() as $nestedPart)
            {
                $allParts->add($nestedPart);
            }
        }

        return $allParts;
    }


    /**
     * Find a header's value in a list of headers.
     * @param list<array{0: string, 1: string}> $headers - each header's name and value.
     * @param string $name - the header's name, in any case.
     * @return string - the first header's value, or an empty string if there isn't one.
     */
    private static function findHeader(array $headers, string $name) : string
    {
        $value = '';
        $found = false;

        foreach ($headers as $header)
        {
            if (!$found && strcasecmp($header[0], $name) === 0)
            {
                $value = $header[1];
                $found = true;
            }
        }

        return $value;
    }


    # Accessors
    public function getParts() : MimePartCollection { return $this->m_parts; }
}
