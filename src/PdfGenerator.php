<?php

namespace WpDocumentPdfDemo;

use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfGenerator
{
    public function generate(string $title, string $bodyHtml, string $path): void
    {
        $options = new Options();
        // Templates are written by admins, but blocking remote fetches keeps PDF rendering
        // from making outbound requests (SSRF) if template content is ever compromised.
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->wrap($title, $bodyHtml), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        file_put_contents($path, $dompdf->output());
    }

    private function wrap(string $title, string $bodyHtml): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return "<!doctype html><html><head><meta charset=\"utf-8\"><title>{$title}</title>"
            . '<style>body{font-family:"DejaVu Sans",sans-serif;font-size:12px;line-height:1.6}h1{font-size:18px}</style>'
            . "</head><body><h1>{$title}</h1>{$bodyHtml}</body></html>";
    }
}
