<?php

namespace Cypher\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Foundation\Foundation\CypherFoundation;
use Cypher\Foundation\Governance\GovernanceFramework;
use Cypher\Foundation\Standards\StandardsProgram;
use Cypher\Foundation\Certification\CertificationAuthority;
use Cypher\Foundation\Security\FoundationSecurityProgram;
use Cypher\Foundation\Stability\EcosystemStability;
use Cypher\Foundation\Chapters\GlobalChapters;
use Cypher\Foundation\Finance\FinancialSustainability;
use Cypher\Foundation\Research\ResearchProgram;

class FoundationTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cyp_fnd_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->testDir);
    }

    // === Cypher Foundation ===
    public function testFoundationCreation(): void
    {
        $fnd = new CypherFoundation(['data_dir' => $this->testDir . '/foundation']);
        $bylaws = $fnd->getBylaws();
        $this->assertSame('Cypher Foundation', $bylaws['name']);
        $this->assertCount(5, $bylaws['principles']);
    }

    public function testBoardAppointment(): void
    {
        $fnd = new CypherFoundation(['data_dir' => $this->testDir . '/foundation2']);
        $member = $fnd->appointBoardMember('Alice Smith', 'Chairperson');
        $this->assertSame('active', $member['status']);
        $this->assertCount(1, $fnd->getBoard());
    }

    public function testFoundationMembers(): void
    {
        $fnd = new CypherFoundation(['data_dir' => $this->testDir . '/foundation3']);
        $fnd->addMember('Bob', 'bob@test.com', 'individual', 100);
        $fnd->addMember('Corp Inc', 'corp@test.com', 'enterprise', 5000);

        $members = $fnd->getMembers('enterprise');
        $this->assertCount(1, $members);
        $this->assertEquals(5100, $fnd->getStats()['total_contributions']);
    }

    public function testFoundationPolicies(): void
    {
        $fnd = new CypherFoundation(['data_dir' => $this->testDir . '/foundation4']);
        $id = $fnd->createPolicy('Code of Conduct', 'governance', 'Be respectful');
        $fnd->approvePolicy($id);
        $policies = $fnd->getPolicies('approved');
        $this->assertCount(1, $policies);
    }

    // === Governance ===
    public function testCommitteeAppointment(): void
    {
        $gov = new GovernanceFramework(['data_dir' => $this->testDir . '/gov']);
        $gov->appointToCommittee('technical_steering', 'Dr. Chen', 'chair');
        $committees = $gov->getCommittees();
        $this->assertArrayHasKey('technical_steering', $committees);
        $this->assertEquals(1, $committees['technical_steering']['member_count']);
    }

    public function testGovernanceProposal(): void
    {
        $gov = new GovernanceFramework(['data_dir' => $this->testDir . '/gov2']);
        $prop = $gov->submitGovernanceProposal('New Feature', 'dev@cyp.dev', 'Add X', 'Details');
        $this->assertSame('submitted', $prop['status']);

        for ($i = 0; $i < 3; $i++) {
            $gov->voteOnProposal($prop['id'], "voter-{$i}", 'technical_steering', true);
        }

        $proposals = $gov->getProposals('approved');
        $this->assertCount(1, $proposals);
    }

    // === Standards ===
    public function testStandardsProgram(): void
    {
        $std = new StandardsProgram(['data_dir' => $this->testDir . '/standards']);
        $specs = $std->getSpecifications();
        $this->assertCount(5, $specs);
        $this->assertArrayHasKey('language_spec', $specs);
    }

    public function testPublishSpecification(): void
    {
        $std = new StandardsProgram(['data_dir' => $this->testDir . '/standards2']);
        $std->publishSpecification('language_spec', '2.0.0', 'Formal spec content');
        $std->approveSpecification('language_spec');

        $spec = $std->getSpecification('language_spec');
        $this->assertSame('approved', $spec['status']);
    }

    // === Certification ===
    public function testCertificationPrograms(): void
    {
        $ca = new CertificationAuthority(['data_dir' => $this->testDir . '/cert']);
        $programs = $ca->getPrograms();
        $this->assertCount(4, $programs);
        $this->assertArrayHasKey('developer', $programs);
        $this->assertArrayHasKey('architect', $programs);
    }

    public function testCertificationExam(): void
    {
        $ca = new CertificationAuthority(['data_dir' => $this->testDir . '/cert2']);
        $candId = $ca->registerCandidate('Alice', 'alice@test.com', 'developer', 'professional');
        $this->assertNotEmpty($candId);

        $result = $ca->conductExam($candId, []);
        $this->assertArrayHasKey('passed', $result);
        $this->assertArrayHasKey('score', $result);

        $cert = $ca->getCertification($candId);
        $this->assertContains($cert['status'], ['certified', 'failed']);
    }

    // === Security ===
    public function testIncidentResponse(): void
    {
        $sec = new FoundationSecurityProgram(['data_dir' => $this->testDir . '/sec']);
        $id = $sec->reportIncident('security_breach', 'critical', 'Unauthorized access detected');
        $this->assertNotEmpty($id);

        $sec->resolveIncident($id, 'Access revoked');
        $incidents = $sec->getIncidents('resolved');
        $this->assertCount(1, $incidents);
    }

    public function testBountyProgram(): void
    {
        $sec = new FoundationSecurityProgram(['data_dir' => $this->testDir . '/sec2']);
        $id = $sec->submitBountyReport('researcher-1', 'cyp/core', 'XSS', 'Vulnerability details');
        $sec->reviewBounty($id, 'high', 2500);

        $reports = $sec->getBountyReports('reviewed');
        $this->assertCount(1, $reports);
        $this->assertEquals(2500, $reports[0]['reward']);
    }

    public function testVulnerabilityDisclosure(): void
    {
        $sec = new FoundationSecurityProgram(['data_dir' => $this->testDir . '/sec3']);
        $id = $sec->registerVulnerability('CVE-2026-0001', 'cyp/std', '1.0.0', 'Buffer overflow', 'critical');
        $sec->discloseVulnerability($id);
        $sec->patchVulnerability($id);

        $vulns = $sec->getVulnerabilities('patched');
        $this->assertCount(1, $vulns);
    }

    // === Ecosystem Stability ===
    public function testReleaseManagement(): void
    {
        $st = new EcosystemStability(['data_dir' => $this->testDir . '/stability']);
        $rel = $st->createRelease('1.0.0', 'lts', '2026-01-01', '2028-12-31');
        $st->publishRelease($rel['id']);

        $lts = $st->getActiveLTSVersions();
        $this->assertNotEmpty($lts);
    }

    public function testDeprecation(): void
    {
        $st = new EcosystemStability(['data_dir' => $this->testDir . '/stability2']);
        $st->deprecateFeature('old_function', '1.0.0', 'new_function', '2.0.0');
        $st->createMigration('upgrade-1.0-to-2.0', '1.0.0', '2.0.0', 'Replace old_function with new_function');

        $deps = $st->getDeprecations('1.0.0');
        $this->assertCount(1, $deps);
    }

    // === Global Chapters ===
    public function testChapterActivation(): void
    {
        $ch = new GlobalChapters(['data_dir' => $this->testDir . '/chapters']);
        $regions = $ch->getRegions();
        $this->assertCount(7, $regions);

        $ch->activateChapter('north_america');
        $chapters = $ch->getChapters('active');
        $this->assertCount(1, $chapters);
    }

    public function testChapterEvents(): void
    {
        $ch = new GlobalChapters(['data_dir' => $this->testDir . '/chapters2']);
        $ch->activateChapter('europe');
        $ch->appointLead('europe', 'Marie Curie', 'marie@cyp.dev');
        $ch->recordEvent('europe', 'CYP Paris Meetup', '2026-03-15', 50);

        $leads = $ch->getLeads('europe');
        $this->assertCount(1, $leads);
        $this->assertSame('Marie Curie', $leads[0]['name']);
    }

    // === Financial Sustainability ===
    public function testFinancialRecords(): void
    {
        $fin = new FinancialSustainability(['data_dir' => $this->testDir . '/finance']);
        $fin->recordRevenue('membership', 'individual', 1000, 'Annual memberships');
        $fin->recordRevenue('sponsorship', 'enterprise', 50000, 'Corporate sponsorship');
        $fin->recordExpense('operations', 15000, 'Infrastructure costs');

        $statement = $fin->getFinancialStatement('year');
        $this->assertEquals(51000, $statement['total_revenue']);
        $this->assertEquals(15000, $statement['total_expenses']);
        $this->assertEquals(36000, $statement['net_income']);
    }

    public function testMembershipTiers(): void
    {
        $fin = new FinancialSustainability(['data_dir' => $this->testDir . '/finance2']);
        $tiers = $fin->getMembershipTiers();
        $this->assertArrayHasKey('individual', $tiers);
        $this->assertArrayHasKey('enterprise', $tiers);
        $this->assertEquals(500, $tiers['enterprise']['fee_monthly']);
    }

    // === Research ===
    public function testResearchGrants(): void
    {
        $res = new ResearchProgram(['data_dir' => $this->testDir . '/research']);
        $grant = $res->awardGrant('AI Safety Research', 'Dr. Smith', 'MIT', 100000, 'AI Safety');
        $res->completeGrant($grant['id'], [['title' => 'Paper', 'url' => 'arxiv.org/...']]);

        $completed = $res->getGrants('completed');
        $this->assertCount(1, $completed);
    }

    public function testScholarships(): void
    {
        $res = new ResearchProgram(['data_dir' => $this->testDir . '/research2']);
        $id = $res->awardScholarship('Jane Doe', 'jane@uni.edu', 'Stanford', 15000, 'Computer Science');
        $this->assertNotEmpty($id);

        $res->organizeConference('CYP Research Summit', 'Boston', '2026-08-01', 'AI-Native Dev');
        $stats = $res->getStats();
        $this->assertEquals(1, $stats['conferences_organized']);
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
