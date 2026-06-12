<?php

namespace Cypher\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Compiler;
use Cypher\Compiler\Interpreter\AstInterpreter;

class BootstrapTest extends TestCase
{
    public function testLexerTokenizesCyp(): void
    {
        $source = 'say "Hello from self-hosted CYP!"';
        $compiler = new Compiler(['skip_semantic' => true]);
        $result = $compiler->compile($source);

        $this->assertTrue($result->success, 'Compilation should succeed');
        $this->assertNotNull($result->ast, 'AST should be produced');
    }

    public function testParserParsesComplexProgram(): void
    {
        $source = '
task greet(name)
    say "Hello, {name}!"
end

greet("World")
items = ["apple", "banana"]
for item in items
    say "Item: {item}"
end
if true
    say "True branch"
else
    say "False branch"
end
result = 42
say "Result: {result}"
';

        $compiler = new Compiler(['skip_semantic' => true]);
        $result = $compiler->compile($source);

        $this->assertTrue($result->success, 'Complex program should compile');
        $this->assertNotNull($result->ast, 'AST should be produced');
    }

    public function testAstInterpreterExecutesProgram(): void
    {
        $source = '
say "Hello, World!"
name = "CYP"
say "Welcome to {name}"
';

        $compiler = new Compiler(['skip_semantic' => true]);
        $compileResult = $compiler->compile($source);
        $this->assertTrue($compileResult->success);

        $interpreter = new AstInterpreter();
        $result = $interpreter->interpret($compileResult->ast);

        $this->assertTrue($result->success, 'Execution should succeed');
        $this->assertStringContainsString('Hello, World!', $result->getOutput());
        $this->assertStringContainsString('Welcome to CYP', $result->getOutput());
    }

    public function testRecursiveTaskExecution(): void
    {
        $source = '
task factorial(n)
    if n <= 1
        return 1
    else
        return n * factorial(n - 1)
    end
end

result = factorial(5)
say "Factorial of 5 is " + result
';

        $compiler = new Compiler(['skip_semantic' => true]);
        $compileResult = $compiler->compile($source);
        $this->assertTrue($compileResult->success);

        $interpreter = new AstInterpreter();
        $ir = $interpreter->interpret($compileResult->ast);

        $this->assertTrue($ir->success, 'Execution should succeed');
        $this->assertStringContainsString('Factorial of 5 is 120', $ir->getOutput());
    }

    public function testLoopControlAndStringConcat(): void
    {
        $source = '
result = ""
for c in "hello"
    result = result + c + ","
end
say result
';

        $compiler = new Compiler(['skip_semantic' => true]);
        $compileResult = $compiler->compile($source);
        $this->assertTrue($compileResult->success);

        $interpreter = new AstInterpreter();
        $ir = $interpreter->interpret($compileResult->ast);

        $this->assertTrue($ir->success, 'Execution should succeed');
        $this->assertEquals('h,e,l,l,o,', $ir->getOutput());
    }
}
