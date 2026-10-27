<?php

namespace WpDocumentPdfDemo;

/**
 * Fills {{ placeholder }} tokens in a document template.
 *
 * Kept free of WordPress functions so the core document logic can be unit tested in isolation.
 */
final class TemplateRenderer
{
    private const PATTERN = '/\{\{\s*([a-z0-9_]+)\s*\}\}/i';

    /**
     * @param array<string, string> $vars
     *
     * @throws MissingPlaceholderException when the template uses a placeholder with no value.
     *         A legal document silently printed with blanks is worse than a visible error.
     */
    public function render(string $body, array $vars): string
    {
        $missing = array_values(array_diff($this->placeholders($body), array_keys($vars)));
        if ($missing !== []) {
            throw new MissingPlaceholderException($missing);
        }

        return preg_replace_callback(
            self::PATTERN,
            // Values come from user profiles, so escape them; the template body itself is trusted admin HTML.
            fn (array $m): string => htmlspecialchars($vars[$m[1]], ENT_QUOTES, 'UTF-8'),
            $body
        );
    }

    /**
     * @return list<string>
     */
    public function placeholders(string $body): array
    {
        preg_match_all(self::PATTERN, $body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
