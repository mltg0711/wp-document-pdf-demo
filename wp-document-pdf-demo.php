<?php
/**
 * Plugin Name: WP Document PDF Demo
 * Description: Versioned document templates, member acceptance records, and PDF generation.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Masaki Takagi
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

require __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, [\WpDocumentPdfDemo\Installer::class, 'activate']);

(new \WpDocumentPdfDemo\Plugin())->register();
