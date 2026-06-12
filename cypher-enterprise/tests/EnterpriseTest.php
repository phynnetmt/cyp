<?php

namespace Cypher\Enterprise\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Enterprise\Identity\IdentityPlatform;
use Cypher\Enterprise\Governance\GovernancePlatform;
use Cypher\Enterprise\Security\EnterpriseSecurityPlatform;
use Cypher\Enterprise\Compliance\CompliancePlatform;
use Cypher\Enterprise\Audit\AuditSystem;
use Cypher\Enterprise\EnterpriseAgents\EnterpriseAgentPlatform;
use Cypher\Enterprise\CostManagement\CostManagementPlatform;
use Cypher\Enterprise\MultiTenancy\MultiTenancyPlatform;
use Cypher\Enterprise\EnterpriseDev\EnterpriseDevPlatform;
use Cypher\Enterprise\Analytics\AnalyticsPlatform;
use Cypher\Enterprise\EnterpriseMarketplace\EnterpriseMarketplacePlatform;
use Cypher\Enterprise\Certification\CertificationPlatform;

class EnterpriseTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cyp_ent_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->testDir);
    }

    // === Identity Platform ===
    public function testCreateUser(): void
    {
        $idp = new IdentityPlatform(['data_dir' => $this->testDir . '/identity']);
        $user = $idp->createUser('user@test.com', 'Test User', 'developer');
        $this->assertSame('user@test.com', $user['email']);
        $this->assertSame('active', $user['status']);
    }

    public function testDuplicateUserRejected(): void
    {
        $this->expectException(\Cypher\Enterprise\Identity\IdentityException::class);
        $idp = new IdentityPlatform(['data_dir' => $this->testDir . '/identity2']);
        $idp->createUser('dup@test.com', 'User', 'developer');
        $idp->createUser('dup@test.com', 'User 2', 'developer');
    }

    public function testUserAuthentication(): void
    {
        $idp = new IdentityPlatform(['data_dir' => $this->testDir . '/identity3']);
        $idp->createUser('auth@test.com', 'Auth User', 'developer');
        $idp->setPassword('auth@test.com', 'secure123');
        $this->assertTrue($idp->authenticate('auth@test.com', 'secure123'));
        $this->assertFalse($idp->authenticate('auth@test.com', 'wrong'));
    }

    public function testRolePermissions(): void
    {
        $idp = new IdentityPlatform(['data_dir' => $this->testDir . '/identity4']);
        $idp->createUser('admin@test.com', 'Admin', 'super_admin');
        $idp->createUser('dev@test.com', 'Dev', 'developer');

        $this->assertTrue($idp->hasPermission('admin@test.com', 'deploy'));
        $this->assertTrue($idp->hasPermission('dev@test.com', 'deploy'));
        $this->assertFalse($idp->hasPermission('dev@test.com', 'manage_org'));
    }

    public function testMFA(): void
    {
        $idp = new IdentityPlatform(['data_dir' => $this->testDir . '/identity5']);
        $idp->createUser('mfa@test.com', 'MFA User', 'developer');
        $secret = $idp->enableMFA('mfa@test.com');
        $this->assertNotEmpty($secret);
    }

    public function testRoleHierarchy(): void
    {
        $idp = new IdentityPlatform(['data_dir' => $this->testDir . '/identity6']);
        $idp->createRole('custom', ['custom_perm'], 75);
        $role = $idp->getRole('custom');
        $this->assertNotNull($role);
        $this->assertContains('custom_perm', $role['permissions']);
    }

    // === Governance ===
    public function testCreatePolicy(): void
    {
        $gov = new GovernancePlatform(['data_dir' => $this->testDir . '/gov']);
        $id = $gov->createPolicy('Max Deployments', 'deployment', [
            ['field' => 'replicas', 'operator' => 'lt', 'value' => 10],
        ]);
        $this->assertNotEmpty($id);
    }

    public function testPolicyEvaluation(): void
    {
        $gov = new GovernancePlatform(['data_dir' => $this->testDir . '/gov2']);
        $gov->createPolicy('Max Replicas', 'deployment', [
            ['field' => 'replicas', 'operator' => 'lt', 'value' => 5],
        ]);

        $result = $gov->evaluate('deployment', ['replicas' => 3]);
        $this->assertTrue($result->isAllowed());

        $result2 = $gov->evaluate('deployment', ['replicas' => 10]);
        $this->assertFalse($result2->isAllowed());
    }

    public function testApprovalWorkflow(): void
    {
        $gov = new GovernancePlatform(['data_dir' => $this->testDir . '/gov3']);
        $wfId = $gov->createApprovalWorkflow('Deploy Approval', 'deployment', [
            ['role' => 'manager'],
            ['role' => 'admin'],
        ]);

        $reqId = $gov->requestApproval($wfId, 'deploy-123', 'dev@test.com');
        $this->assertNotEmpty($reqId);
    }

    // === Enterprise Security ===
    public function testKeyManagement(): void
    {
        $sec = new EnterpriseSecurityPlatform(['data_dir' => $this->testDir . '/sec']);
        $key = $sec->generateKey('primary', 'aes-256-gcm');
        $this->assertSame('active', $key['status']);

        $sec->revokeKey($key['id']);
        $keys = $sec->listKeys();
        $this->assertSame('revoked', $keys[0]['status']);
    }

    public function testThreatDetection(): void
    {
        $sec = new EnterpriseSecurityPlatform(['data_dir' => $this->testDir . '/sec2']);
        $id = $sec->recordThreat('intrusion', 'high', '10.0.0.1', 'Unauthorized access attempt');
        $this->assertNotEmpty($id);

        $threats = $sec->listThreats('open');
        $this->assertCount(1, $threats);
    }

    public function testVulnerabilityScanning(): void
    {
        $sec = new EnterpriseSecurityPlatform(['data_dir' => $this->testDir . '/sec3']);
        $scan = $sec->runVulnerabilityScan('my-app.cyp');
        $this->assertSame('completed', $scan['status']);
        $this->assertArrayHasKey('findings', $scan);
    }

    // === Compliance ===
    public function testComplianceFrameworks(): void
    {
        $comp = new CompliancePlatform(['data_dir' => $this->testDir . '/comp']);
        $frameworks = $comp->listFrameworks();
        $this->assertArrayHasKey('soc2', $frameworks);
        $this->assertArrayHasKey('gdpr', $frameworks);
        $this->assertArrayHasKey('hipaa', $frameworks);
        $this->assertCount(7, $frameworks);
    }

    public function testComplianceAssessment(): void
    {
        $comp = new CompliancePlatform(['data_dir' => $this->testDir . '/comp2']);
        $assessment = $comp->assessFramework('soc2');
        $this->assertNotEmpty($assessment->status);

        $report = $comp->generateReport('soc2');
        $this->assertArrayHasKey('score', $report);
        $this->assertArrayHasKey('requirements', $report);
    }

    public function testAuditEvidence(): void
    {
        $comp = new CompliancePlatform(['data_dir' => $this->testDir . '/comp3']);
        $id = $comp->submitAuditEvidence('soc2', 'access_control', 'Access control policy document');
        $this->assertNotEmpty($id);

        $comp->verifyEvidence($id);
        $evidence = $comp->getEvidence($id);
        $this->assertTrue($evidence['verified']);
    }

    // === Audit System ===
    public function testAuditLogging(): void
    {
        $audit = new AuditSystem(['data_dir' => $this->testDir . '/audit']);
        $id = $audit->record('deploy', 'dev@test.com', 'app-123', ['version' => '1.0']);
        $this->assertNotEmpty($id);

        $entry = $audit->getEntry($id);
        $this->assertNotNull($entry);
        $this->assertSame('deploy', $entry['action']);
    }

    public function testAuditQuery(): void
    {
        $audit = new AuditSystem(['data_dir' => $this->testDir . '/audit2']);
        $audit->record('deploy', 'alice', 'app-1');
        $audit->record('deploy', 'bob', 'app-2');
        $audit->record('scale', 'alice', 'app-1');

        $deploys = $audit->query(['action' => 'deploy']);
        $this->assertCount(2, $deploys);

        $aliceActions = $audit->query(['actor' => 'alice']);
        $this->assertCount(2, $aliceActions);
    }

    public function testAuditIntegrity(): void
    {
        $audit = new AuditSystem(['data_dir' => $this->testDir . '/audit3']);
        $audit->record('deploy', 'test', 'app');
        $integrity = $audit->verifyIntegrity();
        $this->assertEquals(1, $integrity['verified']);
        $this->assertEquals(0, $integrity['tampered']);
    }

    // === Enterprise Agents ===
    public function testEnterpriseDepartments(): void
    {
        $ea = new EnterpriseAgentPlatform(['data_dir' => $this->testDir . '/ea']);
        $dept = $ea->createDepartment('Engineering', 'org-1');
        $this->assertSame('Engineering', $dept['name']);
    }

    public function testKnowledgeNetworks(): void
    {
        $ea = new EnterpriseAgentPlatform(['data_dir' => $this->testDir . '/ea2']);
        $dept = $ea->createDepartment('Research', 'org-1');
        $kn = $ea->createKnowledgeNetwork('Research Knowledge', $dept['id']);
        $this->assertSame('active', $kn['status']);

        $ea->indexDocument($kn['id'], 'Important research document');
        $results = $ea->searchKnowledge($kn['id'], 'research');
        $this->assertNotEmpty($results['results']);
    }

    // === Cost Management ===
    public function testBudgetCreation(): void
    {
        $cm = new CostManagementPlatform(['data_dir' => $this->testDir . '/cost']);
        $id = $cm->createBudget('Engineering Budget', 'dept-1', 5000);
        $this->assertNotEmpty($id);
    }

    public function testCostTracking(): void
    {
        $cm = new CostManagementPlatform(['data_dir' => $this->testDir . '/cost2']);
        $cm->trackUsage('dept-1', 'compute', 150.0);
        $cm->trackUsage('dept-1', 'storage', 50.0);

        $costs = $cm->getDepartmentCosts('dept-1', 'month');
        $this->assertEquals(200.0, $costs['total']);
    }

    public function testForecasting(): void
    {
        $cm = new CostManagementPlatform(['data_dir' => $this->testDir . '/cost3']);
        $cm->trackUsage('dept-1', 'compute', 3000);
        $forecast = $cm->getForecast('dept-1');
        $this->assertArrayHasKey('projected_monthly', $forecast);
    }

    // === Multi-Tenancy ===
    public function testOrganizationCreation(): void
    {
        $mt = new MultiTenancyPlatform(['data_dir' => $this->testDir . '/mt']);
        $org = $mt->createOrganization('Acme Corp', 'owner@acme.com');
        $this->assertSame('Acme Corp', $org['name']);
    }

    public function testWorkspaceIsolation(): void
    {
        $mt = new MultiTenancyPlatform(['data_dir' => $this->testDir . '/mt2']);
        $org = $mt->createOrganization('Org', 'owner@org.com');
        $ws = $mt->createWorkspace('Production', $org['id']);
        $this->assertSame('Production', $ws['name']);

        $workspaces = $mt->listWorkspaces($org['id']);
        $this->assertCount(1, $workspaces);
    }

    public function testOrganizationMembers(): void
    {
        $mt = new MultiTenancyPlatform(['data_dir' => $this->testDir . '/mt3']);
        $org = $mt->createOrganization('Team', 'lead@team.com');
        $mt->addMember($org['id'], 'dev1@team.com', 'developer');
        $mt->addMember($org['id'], 'dev2@team.com', 'developer');

        $members = $mt->listMembers($org['id']);
        $this->assertCount(2, $members);
    }

    // === Enterprise Dev ===
    public function testPrivateRegistry(): void
    {
        $ed = new EnterpriseDevPlatform(['data_dir' => $this->testDir . '/ed']);
        $reg = $ed->createPrivateRegistry('Internal Packages', 'org-1');
        $this->assertSame('active', $reg['status']);
    }

    public function testPrivateTemplates(): void
    {
        $ed = new EnterpriseDevPlatform(['data_dir' => $this->testDir . '/ed2']);
        $tpl = $ed->createTemplate('Microservice', 'application', 'org-1', [
            'files' => ['app.cyp', 'cyp.json'],
        ]);
        $this->assertSame('Microservice', $tpl['name']);
    }

    // === Analytics ===
    public function testEventTracking(): void
    {
        $an = new AnalyticsPlatform(['data_dir' => $this->testDir . '/an']);
        $an->trackEvent('deployment', 'application', ['value' => 1], 'org-1');
        $an->trackEvent('deployment', 'application', ['value' => 1], 'org-1');

        $result = $an->query('deployment', 'application', 'count');
        $this->assertEquals(2, $result['value']);
    }

    public function testDashboard(): void
    {
        $an = new AnalyticsPlatform(['data_dir' => $this->testDir . '/an2']);
        $dash = $an->createDashboard('Executive Overview', 'org-1', [
            ['type' => 'chart', 'metric' => 'deployments'],
        ]);
        $this->assertNotEmpty($dash['id']);

        $summary = $an->getExecutiveSummary('org-1');
        $this->assertArrayHasKey('total_applications', $summary);
    }

    // === Enterprise Marketplace ===
    public function testPrivateMarketplace(): void
    {
        $emp = new EnterpriseMarketplacePlatform(['data_dir' => $this->testDir . '/emp']);
        $listing = $emp->publishPrivate('Internal CRM', 'application', 'org-1', 'Enterprise CRM system');
        $this->assertSame('published', $listing['status']);

        $results = $emp->searchPrivate('CRM', 'org-1');
        $this->assertCount(1, $results);
    }

    public function testComponentCertification(): void
    {
        $emp = new EnterpriseMarketplacePlatform(['data_dir' => $this->testDir . '/emp2']);
        $cert = $emp->certifyComponent('Auth Component', '1.0.0', 'org-1', [
            'security_scan' => true,
            'code_review' => true,
        ]);
        $this->assertSame('certified', $cert['status']);
    }

    // === Certification ===
    public function testCertificationTracks(): void
    {
        $cp = new CertificationPlatform(['data_dir' => $this->testDir . '/cert']);
        $tracks = $cp->listTracks();
        $this->assertArrayHasKey('cyp_developer', $tracks);
        $this->assertArrayHasKey('cyp_architect', $tracks);
        $this->assertArrayHasKey('cyp_ai_engineer', $tracks);
        $this->assertCount(5, $tracks);
    }

    public function testEnrollmentAndProgress(): void
    {
        $cp = new CertificationPlatform(['data_dir' => $this->testDir . '/cert2']);
        $certId = $cp->enroll('user-1', 'cyp_developer');
        $this->assertNotEmpty($certId);

        $cert = $cp->getCertification($certId);
        $this->assertSame('enrolled', $cert['status']);

        $cp->completeModule($certId, 'Language Basics', 95);
        $updated = $cp->getCertification($certId);
        $this->assertGreaterThan(0, $updated['progress']);
    }

    public function testFullCertificationCompletion(): void
    {
        $cp = new CertificationPlatform(['data_dir' => $this->testDir . '/cert3']);
        $certId = $cp->enroll('user-1', 'cyp_developer');

        $track = $cp->getTrack('cyp_developer');
        foreach ($track['modules'] as $module) {
            $cp->completeModule($certId, $module, rand(70, 100));
        }

        $completed = $cp->getCertification($certId);
        $this->assertSame('completed', $completed['status']);
        $this->assertNotNull($completed['score']);
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
