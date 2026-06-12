<?php

namespace Cypher\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Compiler;

class EndToEndTest extends TestCase
{
    public function testFullPipelineHelloWorld(): void
    {
        $code = "say \"Hello End-to-End\"\n";
        $compiler = new Compiler();
        $result = $compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->generatedFiles);

        $phpCode = $result->generatedFiles['app/App.php'] ?? '';
        $this->assertStringContainsString('echo', $phpCode);
        $this->assertStringContainsString('Hello End-to-End', $phpCode);
    }

    public function testFullPipelineWithVariables(): void
    {
        $code = "msg = \"Test\"\nsay msg\n";
        $compiler = new Compiler();
        $result = $compiler->compile($code);

        $this->assertTrue($result->success);
        $phpCode = $result->generatedFiles['app/App.php'] ?? '';
        $this->assertStringContainsString('echo', $phpCode);
    }

    public function testModelGeneration(): void
    {
        $code = "model User\n    id: int\n    name: string\nend\n";
        $compiler = new Compiler();
        $result = $compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('app/Models/User.php', $result->generatedFiles);

        $modelCode = $result->generatedFiles['app/Models/User.php'];
        $this->assertStringContainsString('class User extends Model', $modelCode);
        $this->assertStringContainsString('protected $fillable', $modelCode);
    }

    public function testApiRouteGeneration(): void
    {
        $code = "api GET \"/api/test\"\n    say \"test\"\nend\n";
        $compiler = new Compiler();
        $result = $compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('routes/api.php', $result->generatedFiles);

        $routes = $result->generatedFiles['routes/api.php'];
        $this->assertStringContainsString('/api/test', $routes);
    }

    public function testWithExampleFiles(): void
    {
        $examples = glob(__DIR__ . '/../examples/*.cyp');
        if (empty($examples)) {
            $this->markTestSkipped('No example files found');
        }

        $compiler = new Compiler();
        foreach ($examples as $file) {
            $code = file_get_contents($file);
            $result = $compiler->compile($code);
            $name = basename($file);
            $this->assertTrue($result->success, "Failed to compile {$name}: " . implode(', ', $result->errors));
        }
    }
}
