<?php

namespace Cypher\Compiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\PackageManager\LockFile;

class LockFileTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/cyp_lock_' . uniqid() . '.lock';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testCreateAndSave(): void
    {
        $lock = new LockFile();
        $lock->addPackage('test/pkg', '1.0.0', '^1.0.0', ['dep' => '^2.0']);
        $lock->save($this->tmpFile);

        $this->assertFileExists($this->tmpFile);
    }

    public function testLoadAndRead(): void
    {
        $lock = new LockFile();
        $lock->addPackage('test/pkg', '1.0.0', '^1.0.0', []);
        $lock->save($this->tmpFile);

        $loaded = LockFile::load($this->tmpFile);
        $pkg = $loaded->getPackage('test/pkg');
        $this->assertNotNull($pkg);
        $this->assertSame('1.0.0', $pkg['version']);

        $this->assertTrue($loaded->hasPackage('test/pkg'));
        $this->assertFalse($loaded->hasPackage('unknown/pkg'));
    }

    public function testRemovePackage(): void
    {
        $lock = new LockFile();
        $lock->addPackage('test/pkg', '1.0.0', '^1.0.0', []);
        $lock->save($this->tmpFile);

        $loaded = LockFile::load($this->tmpFile);
        $loaded->removePackage('test/pkg');
        $this->assertFalse($loaded->hasPackage('test/pkg'));
    }

    public function testGetPackages(): void
    {
        $lock = new LockFile();
        $lock->addPackage('a', '1.0.0', '*', []);
        $lock->addPackage('b', '2.0.0', '^2.0', []);

        $packages = $lock->getPackages();
        $this->assertCount(2, $packages);
    }

    public function testLoadNonExistent(): void
    {
        $lock = LockFile::load('/nonexistent/path.lock');
        $this->assertEmpty($lock->getPackages());
    }
}
