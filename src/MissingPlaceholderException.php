<?php

namespace WpDocumentPdfDemo;

final class MissingPlaceholderException extends \RuntimeException
{
    /**
     * @param list<string> $missing
     */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('Missing values for placeholders: ' . implode(', ', $missing));
    }
}
