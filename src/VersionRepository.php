<?php

namespace WpDocumentPdfDemo;

final class VersionRepository
{
    private string $table;

    public function __construct(private \wpdb $db)
    {
        $this->table = $db->prefix . 'wpdd_template_versions';
    }

    /**
     * @return array{version:int, title:string, body:string}|null
     */
    public function latest(int $templateId): ?array
    {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT version, title, body FROM {$this->table} WHERE template_id = %d ORDER BY version DESC LIMIT 1",
            $templateId
        ), ARRAY_A);

        return $row ? ['version' => (int) $row['version'], 'title' => $row['title'], 'body' => $row['body']] : null;
    }

    /**
     * @return array{version:int, title:string, body:string}|null
     */
    public function find(int $templateId, int $version): ?array
    {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT version, title, body FROM {$this->table} WHERE template_id = %d AND version = %d",
            $templateId,
            $version
        ), ARRAY_A);

        return $row ? ['version' => (int) $row['version'], 'title' => $row['title'], 'body' => $row['body']] : null;
    }

    public function save(int $templateId, int $version, string $title, string $body): void
    {
        $this->db->insert($this->table, [
            'template_id' => $templateId,
            'version' => $version,
            'title' => $title,
            'body' => $body,
            'created_at' => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s']);
    }
}
