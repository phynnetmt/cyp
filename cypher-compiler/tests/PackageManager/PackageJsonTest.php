<?php

namespace Cypher\Compiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\PackageManager\PackageJson;

class PackageJsonTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/test_cyp_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testCreatePackage(): void
    {
        $pkg = PackageJson::create('test/pkg', [
            'version' => '1.0.0',
            'description' => 'Test package',
        ]);

        $this->assertSame('test/pkg', $pkg->name);
        $this->assertSame('1.0.0', $pkg->version);
        $this->assertSame('Test package', $pkg->description);
    }

    public function testSaveAndLoad(): void
    {
        $pkg = PackageJson::create('test/save', ['version' => '1.2.3']);
        $pkg->dependencies = ['cyp/std' => '^1.0.0'];
        $pkg->save($this->tmpFile);

        $loaded = PackageJson::load($this->tmpFile);
        $this->assertSame('test/save', $loaded->name);
        $this->assertSame('1.2.3', $loaded->version);
        $this->assertArrayHasKey('cyp/std', $loaded->dependencies);
    }

    public function testValidateValidPackage(): void
    {
        $pkg = PackageJson::create('valid/pkg', [
            'version' => '1.0.0',
            'description' => 'A valid package',
            'author' => 'test',
            'license' => 'CYP-1.0',
        ]);
        $errors = $pkg->validate();
        $this->assertEmpty($errors);
    }

    public function testValidateInvalidName(): void
    {
        $pkg = PackageJson::create('Invalid Name!', []);
        $errors = $pkg->validate();
        $this->assertNotEmpty($errors);
    }

    public function testValidateMissingVersion(): void
    {
        $pkg = PackageJson::create('test/pkg', ['version' => 'invalid']);
        $errors = $pkg->validate();
        $this->assertNotEmpty($errors);
    }
}
