# WP Document PDF Demo

A small WordPress plugin that shows how I build document workflows for membership sites:
**versioned document templates → member acceptance records → generated PDFs → per-member archive and email.**

It is intentionally compact so the whole design can be reviewed in a few minutes.

## Features

- **Versioned templates** — admins edit documents (agreements, notices) as a custom post type. Every real content change creates a new version, and the full text of each version is stored.
- **Acceptance records** — members accept the latest version from a page; the plugin records who accepted which version and when.
- **PDF generation** — the accepted version is rendered with the member's details (`{{ member_name }}`, `{{ date }}`, …) and saved as a PDF with [Dompdf](https://github.com/dompdf/dompdf).
- **Member archive** — `[wpdd_my_documents]` lists everything the member has signed, with secure downloads.
- **Email** — the signed PDF is emailed to the member.

## Usage

1. Activate the plugin (tables and the storage folder are created on activation).
2. Create a document under **Document Templates**. Use placeholders such as `{{ member_name }}`, `{{ member_email }}` and `{{ date }}`.
3. Add `[wpdd_document id="123"]` to a page where members should read and accept it.
4. Add `[wpdd_my_documents]` to the member account page.

Add site-specific values (e.g. a subscription plan or membership number) with a filter:

```php
add_filter('wpdd_template_vars', function (array $vars, WP_User $user): array {
    $vars['membership_no'] = get_user_meta($user->ID, 'membership_no', true);
    return $vars;
}, 10, 2);
```

## Design decisions

- **Versions are immutable snapshots.** An acceptance points to a version number, and that version's text never changes, so you can always prove exactly what a member agreed to. Whitespace-only edits (common when the block editor re-saves) don't create a new version.
- **Members can only accept what they actually saw.** The form submits the version shown on the page; if the template changed in the meantime, the acceptance is rejected and the member is asked to review again.
- **Missing data fails loudly.** If a template uses a placeholder with no value, rendering stops instead of producing a legal document with blanks.
- **Signed PDFs are private.** Files live under `uploads/wpdd-documents/` with direct access blocked, and are served only after a nonce check and an ownership check done in the database query.
- **Idempotent acceptance.** Double submits don't create duplicate records or emails.
- **Testable core.** Placeholder rendering and versioning rules are plain PHP classes with no WordPress dependency, covered by PHPUnit tests that run in CI.

## Project structure

```
src/
  Plugin.php                 Hooks, shortcodes, request handlers (WordPress glue)
  TemplateRenderer.php       Placeholder rendering with escaping       ← unit tested
  VersionPolicy.php          When an edit becomes a new version        ← unit tested
  VersionRepository.php      Template version snapshots ($wpdb)
  AcceptanceRepository.php   Acceptance records ($wpdb)
  PdfGenerator.php           Dompdf wrapper (remote fetching disabled)
  Storage.php                Protected PDF storage
  Installer.php              Tables + storage folder on activation
tests/                       PHPUnit tests
```

## Development

```bash
composer install
composer test
```

## Not included (out of scope for a demo)

- Migration of documents and records from a legacy plugin. In a real project I would build it as a WP-CLI command with a dry-run mode that reports counts before writing anything.
- Admin screens for browsing all acceptances, and integration tests against a WordPress test install.

## License

GPL-2.0-or-later
