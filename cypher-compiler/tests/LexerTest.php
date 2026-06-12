<?php

namespace Cypher\Compiler\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Compiler\Lexer\Lexer;
use Cypher\Compiler\Lexer\TokenType;

class LexerTest extends TestCase
{
    public function testTokenizesSimpleAssignment(): void
    {
        $lexer = new Lexer("name = \"Cypher\"\n");
        $tokens = $lexer->tokenize();

        $this->assertCount(5, $tokens);
        $this->assertSame(TokenType::Identifier, $tokens[0]->type);
        $this->assertSame('name', $tokens[0]->value);
        $this->assertSame(TokenType::Equals, $tokens[1]->type);
        $this->assertSame(TokenType::String, $tokens[2]->type);
        $this->assertSame('Cypher', $tokens[2]->value);
        $this->assertSame(TokenType::Newline, $tokens[3]->type);
        $this->assertSame(TokenType::EOF, $tokens[4]->type);
    }

    public function testTokenizesKeywords(): void
    {
        $lexer = new Lexer("task greet(name)\n    say \"hi\"\nend\n");
        $tokens = $lexer->tokenize();

        $types = array_map(fn($t) => $t->type->value, $tokens);
        $this->assertContains(TokenType::KeywordTask->value, $types);
        $this->assertContains(TokenType::KeywordSay->value, $types);
        $this->assertContains(TokenType::KeywordEnd->value, $types);
    }

    public function testTokenizesNumbers(): void
    {
        $lexer = new Lexer("42\n3.14\n");
        $tokens = $lexer->tokenize();

        $this->assertSame('42', $tokens[0]->value);
        $this->assertSame(TokenType::Number, $tokens[0]->type);
        $this->assertSame('3.14', $tokens[2]->value);
        $this->assertSame(TokenType::Number, $tokens[2]->type);
    }

    public function testSkipsComments(): void
    {
        $lexer = new Lexer("# this is a comment\nx = 1\n");
        $tokens = $lexer->tokenize();

        $hasComment = false;
        foreach ($tokens as $t) {
            if ($t->type === TokenType::Comment) $hasComment = true;
        }
        $this->assertFalse($hasComment, 'Comments should be filtered out');
    }
}
