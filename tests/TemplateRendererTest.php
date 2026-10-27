<?php

namespace WpDocumentPdfDemo\Tests;

use PHPUnit\Framework\TestCase;
use WpDocumentPdfDemo\MissingPlaceholderException;
use WpDocumentPdfDemo\TemplateRenderer;

final class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer();
    }

    public function testReplacesPlaceholders(): void
    {
        $html = $this->renderer->render('<p>Dear {{ member_name }}, signed on {{date}}.</p>', [
            'member_name' => 'Jane Doe',
            'date' => '2026-10-27',
        ]);

        $this->assertSame('<p>Dear Jane Doe, signed on 2026-10-27.</p>', $html);
    }

    public function testEscapesValuesButKeepsTemplateHtml(): void
    {
        $html = $this->renderer->render('<strong>{{ member_name }}</strong>', [
            'member_name' => '<script>alert(1)</script>',
        ]);

        $this->assertSame('<strong>&lt;script&gt;alert(1)&lt;/script&gt;</strong>', $html);
    }

    public function testThrowsWhenAValueIsMissing(): void
    {
        try {
            $this->renderer->render('{{ member_name }} / {{ plan }} / {{ plan }}', ['member_name' => 'Jane']);
            $this->fail('Expected MissingPlaceholderException');
        } catch (MissingPlaceholderException $e) {
            $this->assertSame(['plan'], $e->missing);
        }
    }

    public function testListsUniquePlaceholders(): void
    {
        $this->assertSame(
            ['member_name', 'date'],
            $this->renderer->placeholders('{{member_name}} {{ date }} {{ member_name }}')
        );
    }
}
