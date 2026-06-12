<?php

namespace Cypher\Cloud\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Cloud\Deployment\DeploymentEngine;
use Cypher\Cloud\Deployment\Deployment;
use Cypher\Cloud\Platform\CloudClient;
use Cypher\Cloud\Platform\LocalDevServer;
use Cypher\Cloud\ManagedServices\ManagedDatabase;
use Cypher\Cloud\ManagedServices\ManagedVectorDB;
use Cypher\Cloud\AgentCloud\AgentCloudRuntime;
use Cypher\Cloud\Monitoring\MonitoringSystem;
use Cypher\Cloud\Security\SecurityPlatform;
use Cypher\Cloud\Billing\BillingPlatform;
use Cypher\Cloud\Marketplace\MarketplacePlatform;

class CloudTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cyp_cloud_test_' . uniqid();
        mkdir($this->testDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->testDir);
    }

    // === Deployment Engine ===
    public function testDeployProject(): void
    {
        // Create a mock project
        $projectDir = $this->testDir . '/my-app';
        mkdir($projectDir, 0700, true);
        file_put_contents($projectDir . '/app.cyp', 'say "Hello"');
        file_put_contents($projectDir . '/cyp.json', json_encode(['name' => 'my-app']));

        $engine = new DeploymentEngine(['data_dir' => $this->testDir . '/data']);
        $deployment = $engine->deploy($projectDir, ['version' => '1.0.0']);

        $this->assertNotNull($deployment);
        $this->assertSame('active', $deployment->status);
        $this->assertSame('1.0.0', $deployment->version);
        $this->assertGreaterThan(0, $deployment->duration);
    }

    public function testDeployInvalidProject(): void
    {
        $this->expectException(\Cypher\Cloud\Deployment\DeploymentException::class);
        $engine = new DeploymentEngine(['data_dir' => $this->testDir . '/data']);
        $engine->deploy('/nonexistent/path');
    }

    public function testRollback(): void
    {
        $projectDir = $this->testDir . '/rollback-app';
        mkdir($projectDir, 0700, true);
        file_put_contents($projectDir . '/app.cyp', 'say "v1"');

        $engine = new DeploymentEngine(['data_dir' => $this->testDir . '/data']);
        $v1 = $engine->deploy($projectDir, ['version' => '1.0.0']);

        $rollback = $engine->rollback($v1->id);
        $this->assertSame('active', $rollback->status);
        $this->assertSame($v1->id, $rollback->parentId);
    }

    public function testScale(): void
    {
        $projectDir = $this->testDir . '/scale-app';
        mkdir($projectDir, 0700, true);
        file_put_contents($projectDir . '/app.cyp', 'say "scale"');

        $engine = new DeploymentEngine(['data_dir' => $this->testDir . '/data']);
        $deploy = $engine->deploy($projectDir);
        $scaled = $engine->scale($deploy->id, 5);

        $this->assertSame(5, $scaled->replicas);
    }

    public function testListDeployments(): void
    {
        $projectDir = $this->testDir . '/list-app';
        mkdir($projectDir, 0700, true);
        file_put_contents($projectDir . '/app.cyp', 'say "list"');

        $engine = new DeploymentEngine(['data_dir' => $this->testDir . '/data']);
        $engine->deploy($projectDir, ['version' => '1.0.0']);

        $this->assertCount(1, $engine->listDeployments());
        $this->assertEquals(1, $engine->getDeploymentCount());
    }

    // === Local Dev Server ===
    public function testLocalDevServer(): void
    {
        $server = new LocalDevServer(['port' => 9090]);
        $server->start();
        $this->assertTrue($server->isRunning());

        $status = $server->getServiceStatus();
        $this->assertArrayHasKey('running', $status);
        $this->assertArrayHasKey('services', $status);
        $this->assertSame(9090, $server->getPort());

        $server->stop();
        $this->assertFalse($server->isRunning());
    }

    // === Managed Services ===
    public function testManagedDatabase(): void
    {
        $db = new ManagedDatabase(['data_dir' => $this->testDir . '/databases']);
        $created = $db->create('test-db', 'postgresql');
        $this->assertArrayHasKey('id', $created);
        $this->assertSame('test-db', $created['name']);

        $fetched = $db->get($created['id']);
        $this->assertNotNull($fetched);

        $all = $db->list();
        $this->assertCount(1, $all);
    }

    public function testManagedVectorDB(): void
    {
        $vdb = new ManagedVectorDB(['data_dir' => $this->testDir . '/vectors']);
        $idx = $vdb->createIndex('products', 128, 'cosine');
        $this->assertSame('products', $idx['name']);
        $this->assertSame(128, $idx['dimensions']);

        $this->assertCount(1, $vdb->listIndexes());

        // Test upsert and search
        $vdb->upsertVectors($idx['id'], [
            ['id' => 'v1', 'embedding' => array_fill(0, 128, 0.1), 'metadata' => ['name' => 'item1']],
            ['id' => 'v2', 'embedding' => array_fill(0, 128, 0.9), 'metadata' => ['name' => 'item2']],
        ]);

        $results = $vdb->search($idx['id'], array_fill(0, 128, 0.85), 5);
        $this->assertNotEmpty($results['results']);
        $this->assertEquals(2, $results['total']);
        $this->assertGreaterThan($results['results'][1]['score'], $results['results'][0]['score'],
            'First result should have higher similarity');
    }

    // === Agent Cloud ===
    public function testAgentCloudRuntime(): void
    {
        $ac = new AgentCloudRuntime(['data_dir' => $this->testDir . '/agents']);
        $cluster = $ac->createCluster('support-team', 'support_agent', 3);
        $this->assertSame('support-team', $cluster['name']);
        $this->assertSame(3, $cluster['replicas']);

        $scaled = $ac->scaleCluster($cluster['id'], 5);
        $this->assertSame(5, $scaled['replicas']);

        $this->assertCount(1, $ac->listClusters());
    }

    public function testAgentScheduling(): void
    {
        $ac = new AgentCloudRuntime(['data_dir' => $this->testDir . '/agents2']);
        $cluster = $ac->createCluster('worker', 'worker');
        $scheduleId = $ac->scheduleAgent($cluster['id'], '0 */6 * * *', 'process_data');
        $this->assertNotNull($scheduleId);

        $schedules = $ac->listSchedules();
        $this->assertCount(1, $schedules);
    }

    // === Monitoring ===
    public function testMonitoringSystem(): void
    {
        $mon = new MonitoringSystem(['data_dir' => $this->testDir . '/monitoring']);
        $mon->recordMetric('cpu_usage', 45.2, ['host' => 'web-1']);
        $mon->recordMetric('cpu_usage', 72.1, ['host' => 'web-2']);
        $mon->recordMetric('memory_usage', 1024, ['host' => 'web-1']);

        $avg = $mon->queryMetrics('cpu_usage', 'avg');
        $this->assertArrayHasKey('value', $avg);
        $this->assertEquals(2, $avg['count']);
    }

    public function testAlerts(): void
    {
        $mon = new MonitoringSystem(['data_dir' => $this->testDir . '/monitoring2']);
        $alertId = $mon->createAlert('High CPU', 'cpu_usage', '>', 90, 'email');
        $this->assertNotNull($alertId);

        $mon->recordMetric('cpu_usage', 95);
        $logs = $mon->queryLogs(['level' => 'warning']);
        $this->assertNotEmpty($logs);
    }

    public function testLogs(): void
    {
        $mon = new MonitoringSystem(['data_dir' => $this->testDir . '/monitoring3']);
        $mon->appendLog('app', 'info', 'Server started');
        $mon->appendLog('app', 'error', 'Connection failed', ['db' => 'primary']);
        $mon->appendLog('agent', 'info', 'Task completed');

        $logs = $mon->queryLogs(['source' => 'app']);
        $this->assertCount(2, $logs);

        $errorLogs = $mon->queryLogs(['level' => 'error']);
        $this->assertCount(1, $errorLogs);
    }

    // === Security ===
    public function testSecuritySecrets(): void
    {
        $sec = new SecurityPlatform(['data_dir' => $this->testDir . '/security']);
        $sec->storeSecret('db_password', 'super-secret-123');
        $value = $sec->getSecret('db_password');
        $this->assertSame('super-secret-123', $value);

        $secrets = $sec->listSecrets();
        $this->assertCount(1, $secrets);
        $this->assertSame('db_password', $secrets[0]['name']);
    }

    public function testRBAC(): void
    {
        $sec = new SecurityPlatform(['data_dir' => $this->testDir . '/security2']);
        $this->assertTrue($sec->hasPermission('admin', 'deploy'));
        $this->assertTrue($sec->hasPermission('developer', 'deploy'));
        $this->assertFalse($sec->hasPermission('viewer', 'deploy'));

        $sec->createRole('custom_role', ['custom_action']);
        $this->assertTrue($sec->hasPermission('custom_role', 'custom_action'));
    }

    public function testAuditLog(): void
    {
        $sec = new SecurityPlatform(['data_dir' => $this->testDir . '/security3']);
        $sec->storeSecret('key', 'value');
        $sec->getSecret('key');

        $log = $sec->getAuditLog();
        $this->assertCount(2, $log);
        $this->assertSame('secret.created', $log[1]['action']);
    }

    // === Billing ===
    public function testBillingPlans(): void
    {
        $bill = new BillingPlatform(['data_dir' => $this->testDir . '/billing']);
        $plans = $bill->listPlans();
        $this->assertArrayHasKey('free', $plans);
        $this->assertArrayHasKey('pro', $plans);
        $this->assertArrayHasKey('enterprise', $plans);
    }

    public function testBillingUsage(): void
    {
        $bill = new BillingPlatform(['data_dir' => $this->testDir . '/billing2', 'plan' => 'pro']);
        $bill->trackUsage('bandwidth_gb', 10);
        $bill->trackUsage('compute_hours', 5);

        $usage = $bill->getUsage('month');
        $this->assertArrayHasKey('bandwidth_gb', $usage);
        $this->assertArrayHasKey('compute_hours', $usage);
        $this->assertEquals(10, $usage['bandwidth_gb']);
        $this->assertEquals(5, $usage['compute_hours']);
    }

    public function testBillingPlanEnforcement(): void
    {
        $this->expectException(\RuntimeException::class);
        $bill = new BillingPlatform(['data_dir' => $this->testDir . '/billing3', 'plan' => 'free']);
        $bill->trackUsage('bandwidth_gb', 10);
    }

    public function testBillingInvoice(): void
    {
        $bill = new BillingPlatform(['data_dir' => $this->testDir . '/billing4', 'plan' => 'pro']);
        $invoice = $bill->getInvoice();
        $this->assertSame('pro', $invoice['plan']);
        $this->assertArrayHasKey('estimated_cost', $invoice);
    }

    // === Marketplace ===
    public function testMarketplace(): void
    {
        $mp = new MarketplacePlatform(['data_dir' => $this->testDir . '/marketplace']);
        $listing = $mp->publish('My Package', 'package', 'A useful package');
        $this->assertSame('published', $listing['status']);

        $results = $mp->search('My Package');
        $this->assertCount(1, $results);
    }

    public function testMarketplaceDuplicateRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $mp = new MarketplacePlatform(['data_dir' => $this->testDir . '/marketplace3']);
        $mp->publish('unique-name', 'package', 'First');
        $mp->publish('unique-name', 'package', 'Duplicate');
    }

    public function testMarketplaceUnpublish(): void
    {
        $mp = new MarketplacePlatform(['data_dir' => $this->testDir . '/marketplace4']);
        $listing = $mp->publish('Test', 'agent', 'Test agent');
        $mp->unpublish($listing['id']);
        $fetched = $mp->getListing($listing['id']);
        $this->assertSame('unpublished', $fetched['status']);
    }

    public function testMarketplaceDownloadTracking(): void
    {
        $mp = new MarketplacePlatform(['data_dir' => $this->testDir . '/marketplace2']);
        $listing = $mp->publish('Test', 'agent', 'Test agent');
        $mp->recordDownload($listing['id']);
        $mp->recordDownload($listing['id']);

        $stats = $mp->getStats();
        $this->assertEquals(2, $stats['total_downloads']);
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
