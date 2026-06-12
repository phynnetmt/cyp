<?php

namespace Cypher\Compiler\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Lexer\Lexer;
use Cypher\Compiler\Parser\Parser;
use Cypher\Compiler\AST\{
    ModuleNode, VarDeclStmt, SayStmt, TaskDeclStmt,
    LiteralExpr, IdentifierExpr, BinaryExpr,
};

class ParserTest extends TestCase
{
    public function testParsesSimpleSayStatement(): void
    {
        $code = "say \"Hello\"\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $this->assertInstanceOf(ModuleNode::class, $ast);
        $this->assertCount(1, $ast->statements);
        $this->assertInstanceOf(SayStmt::class, $ast->statements[0]);
    }

    public function testParsesVariableDeclaration(): void
    {
        $code = "name = \"Cypher\"\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $this->assertCount(1, $ast->statements);
        $stmt = $ast->statements[0];
        $this->assertInstanceOf(VarDeclStmt::class, $stmt);
        $this->assertSame('name', $stmt->name);
    }

    public function testParsesTaskDeclaration(): void
    {
        $code = "task greet(name)\n    say \"Hello\"\nend\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $this->assertCount(1, $ast->statements);
        $stmt = $ast->statements[0];
        $this->assertInstanceOf(TaskDeclStmt::class, $stmt);
        $this->assertSame('greet', $stmt->name);
        $this->assertCount(1, $stmt->params);
        $this->assertSame('name', $stmt->params[0]->name);
    }

    public function testParsesArithmeticExpression(): void
    {
        $code = "x = 1 + 2 * 3\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $stmt = $ast->statements[0];
        $this->assertInstanceOf(VarDeclStmt::class, $stmt);
        $this->assertInstanceOf(BinaryExpr::class, $stmt->initializer);
    }

    public function testParsesIfStatement(): void
    {
        $code = "if x > 5\n    say \"big\"\nend\n";
        $lexer = new Lexer($code);
        $parser = new Parser($lexer->tokenize());
        $ast = $parser->parse();

        $this->assertCount(1, $ast->statements);
    }
}
