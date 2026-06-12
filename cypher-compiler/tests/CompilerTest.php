<?php

namespace Cypher\Compiler\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Compiler;

class CompilerTest extends TestCase
{
    private Compiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new Compiler();
    }

    public function testCompilesHelloWorld(): void
    {
        $code = "say \"Hello, World!\"\n";
        $result = $this->compiler->compile($code);

        $this->assertTrue($result->success, 'Compilation should succeed');
        $this->assertFalse($result->hasErrors(), 'No errors expected');
        $this->assertNotEmpty($result->generatedFiles, 'Should generate output files');
    }

    public function testCompilesVariableAssignment(): void
    {
        $code = "name = \"Cypher\"\nsay name\n";
        $result = $this->compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testCompilesTaskDeclaration(): void
    {
        $code = "task add(a, b)\n    return a + b\nend\n";
        $result = $this->compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testCompilesModelDeclaration(): void
    {
        $code = "model User\n    id: int\n    name: string\nend\n";
        $result = $this->compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testCompilesApiRoute(): void
    {
        $code = "api GET \"/api/users\"\n    return [\"ok\"]\nend\n";
        $result = $this->compiler->compile($code);

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testReportsSyntaxError(): void
    {
        $code = "say \"Hello\n";
        $result = $this->compiler->compile($code);

        $this->assertFalse($result->success);
        $this->assertTrue($result->hasErrors());
    }

    public function testComprehensiveProgram(): void
    {
        $code = <<<'CYP'
# A comprehensive test program
name = "Cypher"
version = "0.1.0"

say "Hello from {name} v{version}"

task add(a: int, b: int): int
    return a + b
end

result = add(5, 3)
say "5 + 3 = {result}"

if result > 5
    say "Greater"
else
    say "Less or equal"
end

items = ["a", "b", "c"]
for item in items
    say "Item: {item}"
end

repeat 3
    say "Loop"
end

model Product
    id: int
    name: string
    price: float
end

api GET "/api/products"
    return Product:all()
end
CYP;

        $result = $this->compiler->compile($code);
        $this->assertTrue($result->success, 'Full program should compile: ' . implode(', ', $result->errors));
        $this->assertFalse($result->hasErrors());
    }
}
