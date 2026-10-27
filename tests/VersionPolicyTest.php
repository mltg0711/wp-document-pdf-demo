<?php

namespace WpDocumentPdfDemo\Tests;

use PHPUnit\Framework\TestCase;
use WpDocumentPdfDemo\VersionPolicy;

final class VersionPolicyTest extends TestCase
{
    private VersionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new VersionPolicy();
    }

    public function testFirstSaveCreatesVersionOne(): void
    {
        $this->assertSame(1, $this->policy->nextVersion(null, 0, '<p>Terms</p>'));
    }

    public function testContentChangeCreatesNextVersion(): void
    {
        $this->assertSame(3, $this->policy->nextVersion('<p>Terms</p>', 2, '<p>New terms</p>'));
    }

    public function testWhitespaceOnlyChangeKeepsVersion(): void
    {
        $this->assertNull($this->policy->nextVersion("<p>Terms</p>\n", 2, "  <p>Terms</p>"));
    }

    public function testIdenticalContentKeepsVersion(): void
    {
        $this->assertNull($this->policy->nextVersion('<p>Terms</p>', 1, '<p>Terms</p>'));
    }
}
