<?php

namespace WpDocumentPdfDemo;

/**
 * Decides when a template edit becomes a new version.
 *
 * Acceptance records point at a version number, so a version must never change after members accept it.
 * Every real content change therefore creates a new version, while whitespace-only edits
 * (common when the block editor re-saves) do not, to avoid asking members to re-accept identical text.
 */
final class VersionPolicy
{
    /**
     * @return int|null The version number to create, or null when no new version is needed.
     */
    public function nextVersion(?string $latestBody, int $latestVersion, string $newBody): ?int
    {
        if ($latestBody !== null && $this->normalize($latestBody) === $this->normalize($newBody)) {
            return null;
        }

        return $latestVersion + 1;
    }

    private function normalize(string $body): string
    {
        return trim(preg_replace('/\s+/', ' ', $body));
    }
}
