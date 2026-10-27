<?php

namespace WpDocumentPdfDemo;

/**
 * Stores generated PDFs under uploads/ but blocks direct URL access.
 * Signed documents contain personal data, so they are only served through
 * the download handler after an ownership check.
 */
final class Storage
{
    public static function baseDir(): string
    {
        return trailingslashit(wp_upload_dir()['basedir']) . 'wpdd-documents';
    }

    public static function ensureProtectedDirectory(): void
    {
        $dir = self::baseDir();
        wp_mkdir_p($dir);

        if (!file_exists($dir . '/.htaccess')) {
            file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!file_exists($dir . '/index.php')) {
            file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        }
    }

    public static function pathFor(int $userId, int $templateId, int $version): string
    {
        self::ensureProtectedDirectory();
        $dir = self::baseDir() . '/' . $userId;
        wp_mkdir_p($dir);

        return sprintf('%s/template-%d-v%d-%s.pdf', $dir, $templateId, $version, gmdate('YmdHis'));
    }
}
