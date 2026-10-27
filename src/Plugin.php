<?php

namespace WpDocumentPdfDemo;

/**
 * WordPress glue: hooks, shortcodes and request handlers.
 * Business rules live in TemplateRenderer / VersionPolicy so they stay testable without WordPress.
 */
final class Plugin
{
    public const POST_TYPE = 'wpdd_template';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'snapshotVersion'], 10, 2);
        add_shortcode('wpdd_document', [$this, 'renderDocument']);
        add_shortcode('wpdd_my_documents', [$this, 'renderMyDocuments']);
        add_action('admin_post_wpdd_accept', [$this, 'handleAccept']);
        add_action('admin_post_wpdd_download', [$this, 'handleDownload']);
    }

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'label' => __('Document Templates', 'wpdd'),
            // Members only see documents through the shortcode, never as public posts.
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title', 'editor'],
            'capability_type' => 'page',
        ]);
    }

    public function snapshotVersion(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || $post->post_status !== 'publish') {
            return;
        }

        $latest = $this->versions()->latest($postId);
        $next = (new VersionPolicy())->nextVersion($latest['body'] ?? null, $latest['version'] ?? 0, $post->post_content);
        if ($next !== null) {
            $this->versions()->save($postId, $next, $post->post_title, $post->post_content);
        }
    }

    /**
     * [wpdd_document id="123"]
     */
    public function renderDocument(array|string $atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view this document.', 'wpdd') . '</p>';
        }

        $templateId = (int) shortcode_atts(['id' => 0], $atts)['id'];
        $version = $this->versions()->latest($templateId);
        if ($version === null) {
            return '';
        }

        $user = wp_get_current_user();
        try {
            $html = (new TemplateRenderer())->render($version['body'], $this->varsFor($user));
        } catch (MissingPlaceholderException $e) {
            // Show the configuration problem to editors only; members just don't see a broken document.
            return current_user_can('edit_pages') ? '<p>' . esc_html($e->getMessage()) . '</p>' : '';
        }

        $accepted = $this->acceptances()->findFor($user->ID, $templateId, $version['version']);

        ob_start();
        ?>
        <div class="wpdd-document">
            <h2><?php echo esc_html($version['title']); ?> <small>v<?php echo (int) $version['version']; ?></small></h2>
            <div class="wpdd-document__body"><?php echo wp_kses_post($html); ?></div>
            <?php if ($accepted) : ?>
                <p>
                    <?php echo esc_html(sprintf(__('Accepted on %s', 'wpdd'), $this->formatDate($accepted['accepted_at']))); ?>
                    — <a href="<?php echo esc_url($this->downloadUrl($accepted['id'])); ?>"><?php esc_html_e('Download PDF', 'wpdd'); ?></a>
                </p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpdd_accept">
                    <input type="hidden" name="template_id" value="<?php echo (int) $templateId; ?>">
                    <input type="hidden" name="version" value="<?php echo (int) $version['version']; ?>">
                    <?php wp_nonce_field('wpdd_accept_' . $templateId); ?>
                    <label><input type="checkbox" required> <?php esc_html_e('I have read and agree to this document.', 'wpdd'); ?></label>
                    <button type="submit"><?php esc_html_e('Accept', 'wpdd'); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * [wpdd_my_documents] — the member's archive of accepted documents.
     */
    public function renderMyDocuments(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $rows = $this->acceptances()->forUser(get_current_user_id());
        if ($rows === []) {
            return '<p>' . esc_html__('You have no signed documents yet.', 'wpdd') . '</p>';
        }

        $items = '';
        foreach ($rows as $row) {
            $version = $this->versions()->find($row['template_id'], $row['version']);
            $items .= sprintf(
                '<li>%s (v%d) — %s — <a href="%s">%s</a></li>',
                esc_html($version['title'] ?? __('(deleted template)', 'wpdd')),
                $row['version'],
                esc_html($this->formatDate($row['accepted_at'])),
                esc_url($this->downloadUrl($row['id'])),
                esc_html__('Download PDF', 'wpdd')
            );
        }

        return '<ul class="wpdd-my-documents">' . $items . '</ul>';
    }

    public function handleAccept(): void
    {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $shownVersion = (int) ($_POST['version'] ?? 0);
        check_admin_referer('wpdd_accept_' . $templateId);

        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in.', 'wpdd'), '', ['response' => 403]);
        }

        $user = wp_get_current_user();
        $latest = $this->versions()->latest($templateId);

        // If the template changed after the page was loaded, the member would be accepting text they never read.
        if ($latest === null || $latest['version'] !== $shownVersion) {
            wp_die(
                esc_html__('This document was updated. Please reload the page and review it again.', 'wpdd'),
                '',
                ['response' => 409, 'back_link' => true]
            );
        }

        // Re-submitting the form (double click, back button) must not create duplicate records or emails.
        if ($this->acceptances()->findFor($user->ID, $templateId, $latest['version']) === null) {
            $html = (new TemplateRenderer())->render($latest['body'], $this->varsFor($user));
            $path = Storage::pathFor($user->ID, $templateId, $latest['version']);

            (new PdfGenerator())->generate($latest['title'], $html, $path);
            $this->acceptances()->record($user->ID, $templateId, $latest['version'], $path);

            wp_mail(
                $user->user_email,
                sprintf(__('Your signed copy: %s', 'wpdd'), $latest['title']),
                __('Thank you. A copy of the document you accepted is attached.', 'wpdd'),
                [],
                [$path]
            );
        }

        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }

    public function handleDownload(): void
    {
        $acceptanceId = (int) ($_GET['acceptance'] ?? 0);
        check_admin_referer('wpdd_download_' . $acceptanceId);

        if (!is_user_logged_in()) {
            wp_die(esc_html__('Please log in.', 'wpdd'), '', ['response' => 403]);
        }

        $path = $this->acceptances()->pdfPathOwnedBy($acceptanceId, get_current_user_id());
        $real = $path ? realpath($path) : false;
        $base = realpath(Storage::baseDir());

        // Serve only files inside our storage directory, so a tampered database row can't expose other files.
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            wp_die(esc_html__('Document not found.', 'wpdd'), '', ['response' => 404]);
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($real) . '"');
        header('Content-Length: ' . filesize($real));
        readfile($real);
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function varsFor(\WP_User $user): array
    {
        $vars = [
            'member_name' => $user->display_name,
            'member_email' => $user->user_email,
            'date' => wp_date(get_option('date_format')),
        ];

        // Lets a site add its own values, e.g. subscription plan or membership number.
        return apply_filters('wpdd_template_vars', $vars, $user);
    }

    private function downloadUrl(int $acceptanceId): string
    {
        return wp_nonce_url(
            add_query_arg(['action' => 'wpdd_download', 'acceptance' => $acceptanceId], admin_url('admin-post.php')),
            'wpdd_download_' . $acceptanceId
        );
    }

    private function formatDate(string $gmtDatetime): string
    {
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($gmtDatetime . ' UTC'));
    }

    private function versions(): VersionRepository
    {
        global $wpdb;

        return new VersionRepository($wpdb);
    }

    private function acceptances(): AcceptanceRepository
    {
        global $wpdb;

        return new AcceptanceRepository($wpdb);
    }
}
