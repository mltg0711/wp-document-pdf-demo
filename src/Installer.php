<?php

namespace WpDocumentPdfDemo;

final class Installer
{
    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $versions = $wpdb->prefix . 'wpdd_template_versions';
        $acceptances = $wpdb->prefix . 'wpdd_acceptances';

        // Each version keeps a full copy of the text, so an acceptance can always be traced
        // back to the exact wording the member agreed to, even after the template is edited.
        dbDelta("CREATE TABLE {$versions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            version int(10) unsigned NOT NULL,
            title text NOT NULL,
            body longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY template_version (template_id, version)
        ) {$charset};");

        dbDelta("CREATE TABLE {$acceptances} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            template_id bigint(20) unsigned NOT NULL,
            version int(10) unsigned NOT NULL,
            pdf_path text NOT NULL,
            accepted_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            UNIQUE KEY user_template_version (user_id, template_id, version)
        ) {$charset};");

        Storage::ensureProtectedDirectory();
    }
}
