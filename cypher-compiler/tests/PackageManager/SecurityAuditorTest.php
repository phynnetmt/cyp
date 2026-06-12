<?php

namespace Cypher\Compiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Registry\SecurityAuditor;

class SecurityAuditorTest extends TestCase
{
    private SecurityAuditor $auditor;

    protected function setUp(): void
    {
        $this->auditor = new SecurityAuditor();
    }

    public function testAuditEmptyDeps(): void
    {
        $results = $this->auditor->audit([]);
        $this->assertEmpty($results);
        $this->assertFalse($this->auditor->hasIssues());
    }

    public function testAuditSecurePackage(): void
    {
        $results = $this->auditor->audit(['cyp/secure' => '1.0.0']);
        $this->assertEmpty($results);
    }

    public function testAuditVulnerablePackage(): void
    {
        $results = $this->auditor->audit(['cyp/insecure' => '1.1.0']);
        $this->assertNotEmpty($results);
        $this->assertTrue($this->auditor->hasIssues());
    }

    public function testAuditSummary(): void
    {
        $this->auditor->audit(['cyp/insecure' => '1.1.0']);
        $summary = $this->auditor->getSummary();
        $this->assertStringContainsString('critical', $summary);
    }

    public function testAuditCleanSummary(): void
    {
        $this->auditor->audit(['cyp/safe' => '1.0.0']);
        $summary = $this->auditor->getSummary();
        $this->assertStringContainsString('No vulnerabilities', $summary);
    }
}
