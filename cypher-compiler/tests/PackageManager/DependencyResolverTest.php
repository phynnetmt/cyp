<?php

namespace Cypher\Compiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\PackageManager\DependencyResolver;

class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DependencyResolver();
    }

    // === Exact Match ===
    public function testExactVersionMatch(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.0.0', '1.0.0'));
        $this->assertFalse($this->resolver->satisfies('1.0.1', '1.0.0'));
    }

    // === Caret ===
    public function testCaretMajor(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.2.3', '^1.2.0'));
        $this->assertTrue($this->resolver->satisfies('1.9.9', '^1.0.0'));
        $this->assertFalse($this->resolver->satisfies('2.0.0', '^1.0.0'));
        $this->assertFalse($this->resolver->satisfies('0.9.0', '^1.0.0'));
    }

    public function testCaretZeroMinor(): void
    {
        // ^0.3.0 means >=0.3.0 <0.4.0
        $this->assertTrue($this->resolver->satisfies('0.3.0', '^0.3.0'));
        $this->assertTrue($this->resolver->satisfies('0.3.5', '^0.3.0'));
        $this->assertFalse($this->resolver->satisfies('0.4.0', '^0.3.0'));
        $this->assertFalse($this->resolver->satisfies('1.0.0', '^0.3.0'));
    }

    public function testCaretZeroPatch(): void
    {
        // ^0.0.3 means >=0.0.3 <0.0.4 — only 0.0.3 matches
        $this->assertTrue($this->resolver->satisfies('0.0.3', '^0.0.3'));
        $this->assertFalse($this->resolver->satisfies('0.0.4', '^0.0.3'));
        $this->assertFalse($this->resolver->satisfies('0.1.0', '^0.0.3'));
    }

    // === Tilde ===
    public function testTildePatch(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.2.3', '~1.2.0'));
        $this->assertTrue($this->resolver->satisfies('1.2.9', '~1.2.0'));
        $this->assertFalse($this->resolver->satisfies('1.3.0', '~1.2.0'));
    }

    public function testTildeMajor(): void
    {
        // ~1 means >=1.0.0 <2.0.0
        $this->assertTrue($this->resolver->satisfies('1.0.0', '~1'));
        $this->assertTrue($this->resolver->satisfies('1.9.9', '~1'));
        $this->assertFalse($this->resolver->satisfies('2.0.0', '~1'));
        $this->assertTrue($this->resolver->satisfies('1.5.0', '~1'));
    }

    // === Wildcard ===
    public function testWildcardAny(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.0.0', '*'));
        $this->assertTrue($this->resolver->satisfies('99.99.99', '*'));
        $this->assertTrue($this->resolver->satisfies('0.0.1', '*'));
    }

    public function testWildcardMajor(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.0.0', '1.*'));
        $this->assertTrue($this->resolver->satisfies('1.9.9', '1.*'));
        $this->assertFalse($this->resolver->satisfies('2.0.0', '1.*'));
    }

    public function testWildcardX(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.0.0', '1.x'));
        $this->assertTrue($this->resolver->satisfies('1.5.0', '1.x'));
        $this->assertFalse($this->resolver->satisfies('2.0.0', '1.x'));
    }

    // === Range operators ===
    public function testRangeOperators(): void
    {
        $this->assertTrue($this->resolver->satisfies('2.0.0', '>=1.0.0'));
        $this->assertFalse($this->resolver->satisfies('0.5.0', '>=1.0.0'));
        $this->assertTrue($this->resolver->satisfies('1.0.0', '<=2.0.0'));
        $this->assertFalse($this->resolver->satisfies('3.0.0', '<=2.0.0'));
        $this->assertTrue($this->resolver->satisfies('2.0.0', '>1.0.0'));
        $this->assertFalse($this->resolver->satisfies('1.0.0', '>1.0.0'));
        $this->assertTrue($this->resolver->satisfies('1.0.0', '<2.0.0'));
        $this->assertFalse($this->resolver->satisfies('2.0.0', '<2.0.0'));
        $this->assertTrue($this->resolver->satisfies('1.0.0', '!=2.0.0'));
        $this->assertFalse($this->resolver->satisfies('2.0.0', '!=2.0.0'));
    }

    // === Space-separated AND range ===
    public function testAndRange(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.5.0', '>=1.0.0 <2.0.0'));
        $this->assertFalse($this->resolver->satisfies('2.5.0', '>=1.0.0 <2.0.0'));
        $this->assertFalse($this->resolver->satisfies('0.5.0', '>=1.0.0 <2.0.0'));
    }

    // === Hyphen range ===
    public function testHyphenRange(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.5.0', '1.0.0 - 2.0.0'));
        $this->assertTrue($this->resolver->satisfies('2.0.0', '1.0.0 - 2.0.0'));
        $this->assertFalse($this->resolver->satisfies('2.5.0', '1.0.0 - 2.0.0'));
    }

    // === OR constraints ===
    public function testOrConstraint(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.0.0', '^1.0.0 || ^2.0.0'));
        $this->assertTrue($this->resolver->satisfies('2.0.0', '^1.0.0 || ^2.0.0'));
        $this->assertFalse($this->resolver->satisfies('3.0.0', '^1.0.0 || ^2.0.0'));
    }

    public function testMixedAndOr(): void
    {
        // ">=1.0 <2.0 || >=3.0" should match 1.5 and 3.5 but not 2.5
        $this->assertTrue($this->resolver->satisfies('1.5.0', '>=1.0 <2.0 || >=3.0'));
        $this->assertTrue($this->resolver->satisfies('3.5.0', '>=1.0 <2.0 || >=3.0'));
        $this->assertFalse($this->resolver->satisfies('2.5.0', '>=1.0 <2.0 || >=3.0'));
    }

    // === Latest keyword ===
    public function testLatestKeyword(): void
    {
        $this->assertTrue($this->resolver->satisfies('1.0.0', 'latest'));
        $this->assertTrue($this->resolver->satisfies('99.0.0', 'latest'));
    }

    // === findBestVersion ===
    public function testFindBestVersion(): void
    {
        $versions = [
            '1.0.0' => ['dependencies' => []],
            '1.1.0' => ['dependencies' => []],
            '1.2.0' => ['dependencies' => []],
            '2.0.0' => ['dependencies' => []],
        ];
        $best = $this->resolver->findBestVersion($versions, '^1.0.0');
        $this->assertSame('1.2.0', $best);

        $best2 = $this->resolver->findBestVersion($versions, '^2.0.0');
        $this->assertSame('2.0.0', $best2);
    }

    public function testFindBestVersionNoMatch(): void
    {
        $versions = ['1.0.0' => []];
        $best = $this->resolver->findBestVersion($versions, '^2.0.0');
        $this->assertNull($best);
    }

    // === Resolve ===
    public function testResolveDependencies(): void
    {
        $available = [
            'cyp/std' => [
                'versions' => [
                    '1.0.0' => ['dependencies' => []],
                    '1.1.0' => ['dependencies' => []],
                ],
            ],
            'cyp/http' => [
                'versions' => [
                    '1.0.0' => ['dependencies' => ['cyp/std' => '^1.0.0']],
                ],
            ],
        ];

        $result = $this->resolver->resolve(['cyp/http' => '^1.0.0'], $available);

        $this->assertArrayHasKey('cyp/http', $result);
        $this->assertArrayHasKey('cyp/std', $result);
        $this->assertSame('1.1.0', $result['cyp/std']['version']);
        $this->assertFalse($this->resolver->hasErrors());
    }

    public function testResolvePackageNotFound(): void
    {
        $result = $this->resolver->resolve(['unknown/pkg' => '^1.0'], []);
        $this->assertEmpty($result);
        $this->assertTrue($this->resolver->hasErrors());
    }

    public function testDetectsConflict(): void
    {
        $available = [
            'pkg/a' => [
                'versions' => ['1.0.0' => ['dependencies' => ['pkg/b' => '^1.0.0']]],
            ],
            'pkg/b' => [
                'versions' => ['1.0.0' => ['dependencies' => []]],
            ],
        ];

        $result = $this->resolver->resolve(['pkg/a' => '^1.0.0', 'pkg/b' => '^2.0.0'], $available);
        $this->assertTrue($this->resolver->hasErrors());
    }

    public function testCircularDependency(): void
    {
        $available = [
            'pkg/a' => [
                'versions' => ['1.0.0' => ['dependencies' => ['pkg/b' => '^1.0.0']]],
            ],
            'pkg/b' => [
                'versions' => ['1.0.0' => ['dependencies' => ['pkg/a' => '^1.0.0']]],
            ],
        ];

        $result = $this->resolver->resolve(['pkg/a' => '^1.0.0'], $available);
        $this->assertNotEmpty($result);
        $this->assertFalse($this->resolver->hasErrors(), 'Circular deps should be tolerated');
    }
}
