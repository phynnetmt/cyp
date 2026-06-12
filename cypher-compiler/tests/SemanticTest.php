<?php

namespace Cypher\Compiler\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Lexer\Lexer;
use Cypher\Compiler\Parser\Parser;
use Cypher\Compiler\Semantic\SemanticAnalyzer;

class SemanticTest extends TestCase
{
    public function testDetectsUndefinedVariable(): void
    {
        $code = "say undefinedVar\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $analyzer = new SemanticAnalyzer();
        $errors = $analyzer->analyze($ast);

        $this->assertNotEmpty($errors, 'Should detect undefined variable');
    }

    public function testDetectsDuplicateVariableDeclaration(): void
    {
        $code = "x = 1\nx = 2\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $analyzer = new SemanticAnalyzer();
        $errors = $analyzer->analyze($ast);

        $this->assertNotEmpty($errors, 'Should detect duplicate declaration');
    }

    public function testValidProgramNoErrors(): void
    {
        $code = "x = 1\ny = 2\nz = x + y\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $analyzer = new SemanticAnalyzer();
        $errors = $analyzer->analyze($ast);

        $this->assertEmpty($errors, 'Valid program should have no errors');
    }
}
