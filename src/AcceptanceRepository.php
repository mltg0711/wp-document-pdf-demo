<?php

namespace WpDocumentPdfDemo;

final class AcceptanceRepository
{
    private string $table;

    public function __construct(private \wpdb $db)
    {
        $this->table = $db->prefix . 'wpdd_acceptances';
    }

    public function record(int $userId, int $templateId, int $version, string $pdfPath): void
    {
        $this->db->insert($this->table, [
            'user_id' => $userId,
            'template_id' => $templateId,
            'version' => $version,
            'pdf_path' => $pdfPath,
            'accepted_at' => current_time('mysql', true),
        ], ['%d', '%d', '%d', '%s', '%s']);
    }

    /**
     * @return array{id:int, accepted_at:string}|null
     */
    public function findFor(int $userId, int $templateId, int $version): ?array
    {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT id, accepted_at FROM {$this->table} WHERE user_id = %d AND template_id = %d AND version = %d",
            $userId,
            $templateId,
            $version
        ), ARRAY_A);

        return $row ? ['id' => (int) $row['id'], 'accepted_at' => $row['accepted_at']] : null;
    }

    /**
     * @return list<array{id:int, template_id:int, version:int, accepted_at:string}>
     */
    public function forUser(int $userId): array
    {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT id, template_id, version, accepted_at FROM {$this->table} WHERE user_id = %d ORDER BY accepted_at DESC",
            $userId
        ), ARRAY_A);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'template_id' => (int) $r['template_id'],
            'version' => (int) $r['version'],
            'accepted_at' => $r['accepted_at'],
        ], $rows ?: []);
    }

    /**
     * Ownership is checked in the query itself so a guessed ID never returns someone else's document.
     */
    public function pdfPathOwnedBy(int $acceptanceId, int $userId): ?string
    {
        $path = $this->db->get_var($this->db->prepare(
            "SELECT pdf_path FROM {$this->table} WHERE id = %d AND user_id = %d",
            $acceptanceId,
            $userId
        ));

        return $path ?: null;
    }
}
