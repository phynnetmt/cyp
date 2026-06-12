<?php

namespace Cypher\Compiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\PackageManager\PackageManager;
use Cypher\Compiler\PackageManager\PackageJson;
use Cypher\Compiler\PackageManager\LockFile;
use Cypher\Compiler\PackageManager\DependencyResolver;

class PackageManagerTest extends TestCase
{
    private string $testDir;
    private PackageManager $pm;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/cyp_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
        chdir($this->testDir);

        // Create a basic cyp.json
        $pkg = PackageJson::create('test/project', [
            'version' => '1.0.0',
            'description' => 'Test project',
            'author' => 'test',
            'license' => 'CYP-1.0',
        ]);
        $pkg->save($this->testDir . '/cyp.json');

        $this->pm = new PackageManager(['vendor_dir' => $this->testDir . '/vendor']);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->testDir);
    }

    public function testInstallWithoutCypJson(): void
    {
        // Store current dir and move to empty dir
        $cwd = getcwd();
        $emptyDir = sys_get_temp_dir() . '/cyp_empty_' . uniqid();
        mkdir($emptyDir);
        chdir($emptyDir);

        $pm = new PackageManager();
        $caught = false;
        try {
            $pm->install('test/pkg');
        } catch (\Exception $e) {
            $caught = true;
            $this->assertStringContainsString('No cyp.json found', $e->getMessage());
        }
        $this->assertTrue($caught);

        chdir($cwd);
        $this->rmdir($emptyDir);
    }

    public function testInstallSamplePackages(): void
    {
        $result = $this->pm->installSamplePackages();
        $this->assertNotEmpty($result);
        $this->assertContains('cyp/std', $result);
        $this->assertContains('cyp/http', $result);
        $this->assertDirectoryExists($this->testDir . '/vendor/cyp/std');
    }

    public function testListInstalled(): void
    {
        $this->pm->installSamplePackages();
        $installed = $this->pm->listInstalled();
        $this->assertNotEmpty($installed);
        $this->assertArrayHasKey('cyp/std', $installed);
    }

    public function testRemovePackage(): void
    {
        $this->pm->installSamplePackages();
        $result = $this->pm->remove('cyp/std');
        $this->assertTrue($result['success']);
        $this->assertSame('cyp/std', $result['removed']);
    }

    public function testPackageManagerFlow(): void
    {
        // Install
        $result = $this->pm->installSamplePackages();
        $this->assertCount(4, $result);
        $this->assertEquals(4, $this->pm->getInstalledCount());

        // Remove
        $this->pm->remove('cyp/std');
        $this->assertEquals(3, $this->pm->getInstalledCount());
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
