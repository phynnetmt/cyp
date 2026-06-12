<?php

namespace Cypher\Compiler\Parser;

use Cypher\Compiler\Lexer\Token;
use Cypher\Compiler\Lexer\TokenType;
use Cypher\Compiler\AST\{
    ModuleNode, VarDeclStmt, AssignStmt, SayStmt, IfStmt, WhileStmt,
    RepeatStmt, ForStmt, ReturnStmt, TaskDeclStmt, FuncDeclStmt,
    ModelDeclStmt, PageDeclStmt, ApiDeclStmt, ComponentDeclStmt,
    ImportStmt, ExportStmt, TryCatchStmt, ThrowStmt, ClassDeclStmt,
    AgentDeclStmt, ExpressionStmt, ParamDecl, ModelField, ModelRelationship,
    LiteralExpr, IdentifierExpr, BinaryExpr, UnaryExpr, CallExpr,
    PropertyAccessExpr, IndexExpr, ArrayExpr, RecordExpr, FieldExpr,
    MatchExpr, MatchArm, LambdaExpr, EmbedExpr, TernaryExpr,
    InterpolatedStringExpr, StringPart,
};

class Parser
{
    private array $tokens;
    private int $pos = 0;
    private int $len;

    private const BLOCK_KEYWORDS = [
        'if', 'else', 'elif', 'for', 'while', 'repeat', 'task', 'func',
        'model', 'page', 'api', 'component', 'class', 'agent',
        'try', 'catch', 'finally', 'match',
    ];

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->len = count($tokens);
    }

    public function parse(): ModuleNode
    {
        $statements = [];
        while (!$this->isAtEnd()) {
            $stmt = $this->parseStatement();
            if ($stmt !== null) {
                $statements[] = $stmt;
            }
        }
        $tk = $this->peek();
        return new ModuleNode($statements, $tk?->line ?? 1, $tk?->column ?? 1);
    }

    private function parseStatement(): ?\Cypher\Compiler\AST\StmtNode
    {
        $this->skipNewlines();

        if ($this->isAtEnd()) return null;

        $token = $this->peek();

        // Allow certain keywords to be used as variable names when followed by =
        if (in_array($token->type, [
            TokenType::KeywordFunc, TokenType::KeywordType, TokenType::KeywordRecord,
            TokenType::KeywordEnum, TokenType::KeywordUnion, TokenType::KeywordNot,
        ], true)) {
            $savePos = $this->pos;
            $this->pos++;
            $isAssign = $this->check(TokenType::Equals);
            $this->pos = $savePos;
            if ($isAssign) {
                return $this->parseExpressionStmt();
            }
        }

        return match ($token->type) {
            TokenType::KeywordVar => $this->parseVarDecl(),
            TokenType::KeywordLet => $this->parseLetDecl(),
            TokenType::KeywordTask => $this->parseTaskDecl(),
            TokenType::KeywordFunc => $this->parseFuncDecl(),
            TokenType::KeywordIf => $this->parseIfStmt(),
            TokenType::KeywordWhile => $this->parseWhileStmt(),
            TokenType::KeywordRepeat => $this->parseRepeatStmt(),
            TokenType::KeywordFor => $this->parseForStmt(),
            TokenType::KeywordReturn => $this->parseReturnStmt(),
            TokenType::KeywordSay => $this->parseSayStmt(),
            TokenType::KeywordModel => $this->parseModelDecl(),
            TokenType::KeywordPage => $this->parsePageDecl(),
            TokenType::KeywordApi => $this->parseApiDecl(),
            TokenType::KeywordComponent => $this->parseComponentDecl(),
            TokenType::KeywordImport => $this->parseImportStmt(),
            TokenType::KeywordExport => $this->parseExportStmt(),
            TokenType::KeywordTry => $this->parseTryCatchStmt(),
            TokenType::KeywordThrow => $this->parseThrowStmt(),
            TokenType::KeywordClass => $this->parseClassDecl(),
            TokenType::KeywordAgent => $this->parseAgentDecl(),
            TokenType::KeywordAsync => $this->parseAsyncStmt(),
            default => $this->parseExpressionStmt(),
        };
    }

    private function parseVarDecl(): VarDeclStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected variable name')->value;
        $typeHint = null;

        if ($this->match(TokenType::Colon)) {
            $typeHint = $this->consume(TokenType::Identifier, 'Expected type name')->value;
        }

        $this->consume(TokenType::Equals, "Expected '=' in variable declaration");
        $initializer = $this->parseExpression();

        $this->skipNewlines();
        return new VarDeclStmt($token->line, $token->column, $name, $typeHint, $initializer, true);
    }

    private function parseLetDecl(): VarDeclStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected variable name')->value;
        $typeHint = null;

        if ($this->match(TokenType::Colon)) {
            $typeHint = $this->consume(TokenType::Identifier, 'Expected type name')->value;
        }

        $this->consume(TokenType::Equals, "Expected '=' in let declaration");
        $initializer = $this->parseExpression();

        $this->skipNewlines();
        return new VarDeclStmt($token->line, $token->column, $name, $typeHint, $initializer, false);
    }

    private function parseTaskDecl(): TaskDeclStmt
    {
        $token = $this->advance();
        $modifiers = [];

        $name = $this->consume(TokenType::Identifier, 'Expected function name')->value;
        $this->consume(TokenType::LParen, "Expected '(' after function name");
        $params = $this->parseParamList();
        $this->consume(TokenType::RParen, "Expected ')' after parameters");

        $returnType = null;
        if ($this->match(TokenType::Colon)) {
            $returnType = $this->consume(TokenType::Identifier, 'Expected return type')->value;
        }

        $body = $this->parseBlock();
        return new TaskDeclStmt($token->line, $token->column, $name, $params, $returnType, $body, $modifiers);
    }

    private function parseFuncDecl(): FuncDeclStmt
    {
        $token = $this->advance();
        $modifiers = [];

        $name = $this->consume(TokenType::Identifier, 'Expected function name')->value;
        $this->consume(TokenType::LParen, "Expected '(' after function name");
        $params = $this->parseParamList();
        $this->consume(TokenType::RParen, "Expected ')' after parameters");

        $returnType = null;
        if ($this->match(TokenType::Colon)) {
            $returnType = $this->consume(TokenType::Identifier, 'Expected return type')->value;
        }

        $this->consume(TokenType::Equals, "Expected '=' in func declaration");
        $bodyExpr = $this->parseExpression();
        $this->skipNewlines();
        return new FuncDeclStmt($token->line, $token->column, $name, $params, $returnType, [
            new ReturnStmt($bodyExpr->getLine(), $bodyExpr->getColumn(), $bodyExpr)
        ], $modifiers);
    }

    private function parseParamList(): array
    {
        $params = [];
        if ($this->check(TokenType::RParen)) return $params;

        do {
            $name = $this->consume(TokenType::Identifier, 'Expected parameter name')->value;
            $typeHint = null;

            if ($this->match(TokenType::Colon)) {
                $typeHint = $this->consume(TokenType::Identifier, 'Expected type name')->value;
            }

            $default = null;
            if ($this->match(TokenType::Equals)) {
                $default = $this->parseExpression();
            }

            $params[] = new ParamDecl($name, $typeHint, $default);
        } while ($this->match(TokenType::Comma));

        return $params;
    }

    private function parseIfStmt(bool $isElif = false): IfStmt
    {
        $token = $isElif ? $this->previous() : $this->advance();
        $condition = $this->parseExpression();

        $this->skipNewlines();
        $thenBody = $this->parseBlock();

        $elseIf = null;
        $elseBody = null;

        $this->skipNewlines();
        if ($this->match(TokenType::KeywordElif)) {
            $elseIf = $this->parseIfStmt(true);
        } elseif ($this->match(TokenType::KeywordElse)) {
            $this->skipNewlines();
            $elseBody = $this->parseBlock();
        }

        return new IfStmt($token->line, $token->column, $condition, $thenBody, $elseIf, $elseBody);
    }

    private function parseWhileStmt(): WhileStmt
    {
        $token = $this->advance();
        $condition = $this->parseExpression();
        $this->skipNewlines();
        $body = $this->parseBlock();
        return new WhileStmt($token->line, $token->column, $condition, $body);
    }

    private function parseRepeatStmt(): RepeatStmt
    {
        $token = $this->advance();
        $count = $this->parseExpression();
        $this->skipNewlines();
        $body = $this->parseBlock();
        return new RepeatStmt($token->line, $token->column, $count, $body);
    }

    private function parseForStmt(): ForStmt
    {
        $token = $this->advance();
        $var = $this->consume(TokenType::Identifier, 'Expected loop variable')->value;
        $this->consume(TokenType::KeywordIn, "Expected 'in' in for loop");
        $iterable = $this->parseExpression();
        $this->skipNewlines();
        $body = $this->parseBlock();
        return new ForStmt($token->line, $token->column, $var, $iterable, $body);
    }

    private function parseReturnStmt(): ReturnStmt
    {
        $token = $this->advance();
        $this->skipNewlines();
        if ($this->check(TokenType::KeywordEnd) || $this->check(TokenType::KeywordElse) ||
            $this->check(TokenType::KeywordElif) || $this->check(TokenType::KeywordCatch) ||
            $this->check(TokenType::KeywordFinally) || $this->check(TokenType::EOF)) {
            return new ReturnStmt($token->line, $token->column, null);
        }
        $value = $this->parseExpression();
        return new ReturnStmt($token->line, $token->column, $value);
    }

    private function parseSayStmt(): SayStmt
    {
        $token = $this->advance();
        $expr = $this->parseExpression();
        $this->skipNewlines();
        return new SayStmt($token->line, $token->column, $expr);
    }

    private function parseModelDecl(): ModelDeclStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected model name')->value;
        $fields = [];
        $relationships = [];
        $options = [];

        if ($this->match(TokenType::Colon)) {
            $parent = $this->consume(TokenType::Identifier, 'Expected parent model')->value;
            $options['extends'] = $parent;
        }

        $this->skipNewlines();
        $useBraces = $this->match(TokenType::LBrace);

        while (!$this->isAtEnd()) {
            $this->skipNewlines();
            if ($useBraces && $this->check(TokenType::RBrace)) break;
            if (!$useBraces && $this->check(TokenType::KeywordEnd)) break;
            if ($this->check(TokenType::EOF)) break;

            $fieldToken = $this->peek();
            if ($fieldToken->type !== TokenType::Identifier) break;

            $this->advance();
            $fieldName = $fieldToken->value;

            $this->skipNewlines();

            $relTypes = ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany',
                         'BelongsTo', 'HasMany', 'HasOne', 'BelongsToMany'];

            if ($this->check(TokenType::Colon)) {
                $this->advance();
                $fieldType = $this->consume(TokenType::Identifier, 'Expected field type')->value;
                $this->skipNewlines();

                $attributes = [];
                // Parse field attributes (unique, nullable, default, etc.)
                $knownAttrs = ['unique', 'nullable', 'required', 'hidden', 'fillable', 'guarded', 'index'];
                while ($this->check(TokenType::Identifier) && in_array(strtolower($this->peek()->value), $knownAttrs)) {
                    $attributes[] = strtolower($this->advance()->value);
                    $this->skipNewlines();
                }

                if ($this->match(TokenType::KeywordModel)) {
                    $relType = $this->consume(TokenType::Identifier, 'Expected relationship type')->value;
                    $target = $this->consume(TokenType::Identifier, 'Expected target model')->value;
                    $fk = null;
                    if ($this->match(TokenType::KeywordThrough)) {
                        $fk = $this->consume(TokenType::Identifier, 'Expected foreign key')->value;
                    }
                    $relationships[] = new ModelRelationship($fieldName, $relType, $target, $fk ?? $fieldName . '_id');
                } else {
                    $fields[] = new ModelField($fieldName, $fieldType, $attributes);
                }
            } elseif ($this->check(TokenType::Identifier)) {
                $nextId = $this->peek()->value;
                if (in_array($nextId, $relTypes, true)) {
                    $this->advance();
                    $relType = $nextId;
                    $target = $this->consume(TokenType::Identifier, 'Expected target model')->value;
                    $relationships[] = new ModelRelationship($fieldName, $relType, $target, $fieldName . '_id');
                } else {
                    throw new ParserException(
                        "Expected ':' or relationship type after field name '{$fieldName}'",
                        $fieldToken->line, $fieldToken->column
                    );
                }
            }
        }

        if ($useBraces) {
            $this->consume(TokenType::RBrace, "Expected '}' to close model");
        } else {
            $this->consume(TokenType::KeywordEnd, "Expected 'end' to close model");
        }

        return new ModelDeclStmt($token->line, $token->column, $name, $fields, $relationships, $options);
    }

    private function parsePageDecl(): PageDeclStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected page name')->value;
        $options = [];

        if ($this->match(TokenType::LBrace)) {
            while ($this->check(TokenType::Identifier)) {
                $key = $this->advance()->value;
                $this->consume(TokenType::Colon, "Expected ':'");
                $val = $this->parseExpression();
                $options[$key] = $val;
                $this->skipNewlines();
            }
            $this->consume(TokenType::RBrace, "Expected '}'");
        }

        $this->skipNewlines();
        $body = $this->parseBlock();
        return new PageDeclStmt($token->line, $token->column, $name, $body, $options);
    }

    private function parseApiDecl(): ApiDeclStmt
    {
        $token = $this->advance();
        $method = 'GET';
        $path = '';

        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        if ($this->check(TokenType::Identifier) && in_array(strtoupper($this->peek()->value), $methods)) {
            $method = strtoupper($this->advance()->value);
        }

        $path = $this->consume(TokenType::String, 'Expected API path string')->value;
        $options = [];

        if ($this->match(TokenType::LBrace)) {
            while ($this->check(TokenType::Identifier)) {
                $key = $this->advance()->value;
                $this->consume(TokenType::Colon, "Expected ':'");
                $val = $this->parseExpression();
                $options[$key] = $val;
                $this->skipNewlines();
            }
            $this->consume(TokenType::RBrace, "Expected '}'");
        }

        $this->skipNewlines();
        $body = $this->parseBlock();
        return new ApiDeclStmt($token->line, $token->column, $path, $method, $body, $options);
    }

    private function parseComponentDecl(): ComponentDeclStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected component name')->value;
        $props = [];

        if ($this->match(TokenType::LParen)) {
            if (!$this->check(TokenType::RParen)) {
                do {
                    $propName = $this->consume(TokenType::Identifier, 'Expected prop name')->value;
                    $propType = null;
                    if ($this->match(TokenType::Colon)) {
                        $propType = $this->consume(TokenType::Identifier, 'Expected prop type')->value;
                    }
                    $props[] = new ParamDecl($propName, $propType);
                } while ($this->match(TokenType::Comma));
            }
            $this->consume(TokenType::RParen, "Expected ')'");
        }

        $this->skipNewlines();
        $body = $this->parseBlock();
        return new ComponentDeclStmt($token->line, $token->column, $name, $props, $body);
    }

    private function parseImportStmt(): ImportStmt
    {
        $token = $this->advance();
        $names = [];

        do {
            $names[] = $this->consume(TokenType::Identifier, 'Expected import name')->value;
        } while ($this->match(TokenType::Comma));

        if ($this->match(TokenType::KeywordFrom)) {
            $source = $this->consume(TokenType::String, 'Expected module source')->value;
        } else {
            $source = '';
        }

        $this->skipNewlines();
        return new ImportStmt($token->line, $token->column, $names, $source);
    }

    private function parseExportStmt(): ExportStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected export name')->value;
        $this->skipNewlines();
        return new ExportStmt($token->line, $token->column, $name);
    }

    private function parseTryCatchStmt(): TryCatchStmt
    {
        $token = $this->advance();
        $tryBody = $this->parseBlock();
        $catchVar = null;
        $catchBody = null;
        $finallyBody = null;

        $this->skipNewlines();
        if ($this->match(TokenType::KeywordCatch)) {
            if ($this->match(TokenType::Pipe)) {
                $catchVar = $this->consume(TokenType::Identifier, 'Expected exception variable')->value;
                $this->consume(TokenType::Pipe, "Expected '|'");
            }
            $catchBody = $this->parseBlock();
        }

        $this->skipNewlines();
        if ($this->match(TokenType::KeywordFinally)) {
            $finallyBody = $this->parseBlock();
        }

        return new TryCatchStmt($token->line, $token->column, $tryBody, $catchVar, $catchBody, $finallyBody);
    }

    private function parseThrowStmt(): ThrowStmt
    {
        $token = $this->advance();
        $expr = $this->parseExpression();
        $this->skipNewlines();
        return new ThrowStmt($token->line, $token->column, $expr);
    }

    private function parseClassDecl(): ClassDeclStmt
    {
        $token = $this->advance();
        $modifiers = [];
        $name = $this->consume(TokenType::Identifier, 'Expected class name')->value;
        $extends = null;
        $implements = [];

        if ($this->match(TokenType::KeywordExtends)) {
            $extends = $this->consume(TokenType::Identifier, 'Expected parent class')->value;
        }

        if ($this->match(TokenType::KeywordImplements)) {
            do {
                $implements[] = $this->consume(TokenType::Identifier, 'Expected interface name')->value;
            } while ($this->match(TokenType::Comma));
        }

        $this->skipNewlines();
        $this->consume(TokenType::LBrace, "Expected '{' for class body");
        $body = [];

        while (!$this->check(TokenType::RBrace) && !$this->isAtEnd()) {
            $this->skipNewlines();
            if ($this->check(TokenType::RBrace)) break;

            $mods = [];
            while ($this->check(TokenType::KeywordPublic) || $this->check(TokenType::KeywordPrivate) ||
                   $this->check(TokenType::KeywordProtected) || $this->check(TokenType::KeywordStatic) ||
                   $this->check(TokenType::KeywordReadonly)) {
                $mods[] = $this->advance()->value;
            }

            $stmt = $this->parseStatement();
            if ($stmt !== null) {
                $body[] = $stmt;
            }
        }

        $this->consume(TokenType::RBrace, "Expected '}' to close class");
        return new ClassDeclStmt($token->line, $token->column, $name, $extends, $implements, $body, $modifiers);
    }

    private function parseAgentDecl(): AgentDeclStmt
    {
        $token = $this->advance();
        $name = $this->consume(TokenType::Identifier, 'Expected agent name')->value;
        $model = null;
        $systemPrompt = null;
        $tools = [];

        if ($this->match(TokenType::Colon)) {
            $model = $this->consume(TokenType::Identifier, 'Expected model name')->value;
        }

        $this->skipNewlines();
        if ($this->match(TokenType::KeywordPrompt)) {
            $systemPrompt = $this->consume(TokenType::String, 'Expected prompt string')->value;
        }

        $this->skipNewlines();
        $body = $this->parseBlock();
        return new AgentDeclStmt($token->line, $token->column, $name, $model, $systemPrompt, $tools, $body);
    }

    private function parseAsyncStmt(): ?\Cypher\Compiler\AST\StmtNode
    {
        $this->advance();
        return $this->parseStatement();
    }

    private function parseExpressionStmt(): \Cypher\Compiler\AST\StmtNode
    {
        if (($this->check(TokenType::Identifier) || $this->check(TokenType::KeywordFunc)) && !$this->isAtEnd()) {
            $savePos = $this->pos;
            $idToken = $this->peek();

            $this->pos++;
            $isAssign = $this->check(TokenType::Equals);
            $this->pos = $savePos;

            if ($isAssign) {
                $name = $this->advance()->value;
                $this->consume(TokenType::Equals, "Expected '='");
                $value = $this->parseExpression();
                $this->skipNewlines();
                return new VarDeclStmt($idToken->line, $idToken->column, $name, null, $value, true);
            }
        }

        // Handle assignments to index expressions and property accesses
        $expr = $this->parseExpression();
        if ($this->match(TokenType::Equals)) {
            $value = $this->parseExpression();
            $this->skipNewlines();
            return new AssignStmt($expr->getLine(), $expr->getColumn(), $expr, $value);
        }
        $this->skipNewlines();
        return new ExpressionStmt($expr->getLine(), $expr->getColumn(), $expr);
    }

    private function parseBlock(): array
    {
        $statements = [];
        $this->skipNewlines();

        while (!$this->isAtEnd()) {
            if ($this->check(TokenType::KeywordEnd)) {
                $this->advance();
                break;
            }

            if ($this->check(TokenType::KeywordElse, TokenType::KeywordElif,
                TokenType::KeywordCatch, TokenType::KeywordFinally)) {
                break;
            }

            $stmt = $this->parseStatement();
            if ($stmt !== null) {
                $statements[] = $stmt;
            }
            $this->skipNewlines();
        }

        return $statements;
    }

    private function check(TokenType ...$types): bool
    {
        foreach ($types as $type) {
            if (!$this->isAtEnd() && $this->peek()->type === $type) {
                return true;
            }
        }
        return false;
    }

    private function parseExpression(): \Cypher\Compiler\AST\ExprNode
    {
        return $this->parseTernary();
    }

    private function parseTernary(): \Cypher\Compiler\AST\ExprNode
    {
        $expr = $this->parseOr();

        if ($this->match(TokenType::QuestionMark)) {
            $thenExpr = $this->parseExpression();
            $this->consume(TokenType::Colon, "Expected ':' in ternary");
            $elseExpr = $this->parseExpression();
            return new TernaryExpr($expr->getLine(), $expr->getColumn(), $expr, $thenExpr, $elseExpr);
        }

        return $expr;
    }

    private function parseOr(): \Cypher\Compiler\AST\ExprNode
    {
        $left = $this->parseAnd();

        while ($this->match(TokenType::Or) || $this->match(TokenType::KeywordOr)) {
            $op = $this->previous()->value;
            $right = $this->parseAnd();
            $left = new BinaryExpr($left->getLine(), $left->getColumn(), $left, $op, $right);
        }

        return $left;
    }

    private function parseAnd(): \Cypher\Compiler\AST\ExprNode
    {
        $left = $this->parseEquality();

        while ($this->match(TokenType::And) || $this->match(TokenType::KeywordAnd)) {
            $op = $this->previous()->value;
            $right = $this->parseEquality();
            $left = new BinaryExpr($left->getLine(), $left->getColumn(), $left, $op, $right);
        }

        return $left;
    }

    private function parseEquality(): \Cypher\Compiler\AST\ExprNode
    {
        $left = $this->parseComparison();

        while ($this->match(TokenType::EqualsEquals, TokenType::NotEquals)) {
            $op = $this->previous()->value;
            $right = $this->parseComparison();
            $left = new BinaryExpr($left->getLine(), $left->getColumn(), $left, $op, $right);
        }

        return $left;
    }

    private function parseComparison(): \Cypher\Compiler\AST\ExprNode
    {
        $left = $this->parseTerm();

        while ($this->match(TokenType::LessThan, TokenType::GreaterThan, TokenType::LessEquals, TokenType::GreaterEquals)) {
            $op = $this->previous()->value;
            $right = $this->parseTerm();
            $left = new BinaryExpr($left->getLine(), $left->getColumn(), $left, $op, $right);
        }

        return $left;
    }

    private function parseTerm(): \Cypher\Compiler\AST\ExprNode
    {
        $left = $this->parseFactor();

        while ($this->match(TokenType::Plus, TokenType::Minus)) {
            $op = $this->previous()->value;
            $right = $this->parseFactor();
            $left = new BinaryExpr($left->getLine(), $left->getColumn(), $left, $op, $right);
        }

        return $left;
    }

    private function parseFactor(): \Cypher\Compiler\AST\ExprNode
    {
        $left = $this->parseUnary();

        while ($this->match(TokenType::Star, TokenType::Slash, TokenType::Percent)) {
            $op = $this->previous()->value;
            $right = $this->parseUnary();
            $left = new BinaryExpr($left->getLine(), $left->getColumn(), $left, $op, $right);
        }

        return $left;
    }

    private function parseUnary(): \Cypher\Compiler\AST\ExprNode
    {
        if ($this->match(TokenType::Minus, TokenType::Not, TokenType::KeywordNot, TokenType::Tilde)) {
            $op = $this->previous()->value;
            $right = $this->parseUnary();
            return new UnaryExpr($right->getLine(), $right->getColumn(), $op, $right);
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): \Cypher\Compiler\AST\ExprNode
    {
        $this->skipNewlines();

        if ($this->match(TokenType::Number)) {
            $value = $this->previous()->value;
            return new LiteralExpr(
                $this->previous()->line, $this->previous()->column,
                str_contains($value, '.') ? (float)$value : (int)$value,
                str_contains($value, '.') ? 'float' : 'int'
            );
        }

        if ($this->match(TokenType::String)) {
            $raw = $this->previous()->value;
            if (str_contains($raw, '${')) {
                return $this->parseInterpolatedString($this->previous()->line, $this->previous()->column, $raw);
            }
            return new LiteralExpr(
                $this->previous()->line, $this->previous()->column,
                $raw, 'string'
            );
        }

        if ($this->match(TokenType::KeywordTrue, TokenType::KeywordFalse)) {
            return new LiteralExpr(
                $this->previous()->line, $this->previous()->column,
                $this->previous()->value === 'true', 'bool'
            );
        }

        if ($this->match(TokenType::KeywordNull)) {
            return new LiteralExpr(
                $this->previous()->line, $this->previous()->column,
                null, 'null'
            );
        }

        if ($this->match(TokenType::LBracket)) {
            $elements = [];
            while (!$this->check(TokenType::RBracket) && !$this->isAtEnd()) {
                $elements[] = $this->parseExpression();
                $this->match(TokenType::Comma);
            }
            $this->consume(TokenType::RBracket, "Expected ']'");
            return new ArrayExpr($this->previous()->line, $this->previous()->column, $elements);
        }

        if ($this->match(TokenType::LBrace)) {
            $fields = [];
            while (!$this->check(TokenType::RBrace) && !$this->isAtEnd()) {
                $this->skipNewlines();
                if ($this->check(TokenType::RBrace)) break;
                if ($this->check(TokenType::Identifier)) {
                    $key = $this->advance()->value;
                } elseif (!$this->isAtEnd() && !in_array($this->peek()->type, [
                    TokenType::RBrace, TokenType::Newline, TokenType::Comma, TokenType::EOF,
                ], true)) {
                    $key = $this->advance()->value;
                } else {
                    $key = $this->consume(TokenType::Identifier, 'Expected record key')->value;
                }
                $this->consume(TokenType::Colon, "Expected ':'");
                $val = $this->parseExpression();
                $fields[] = new FieldExpr($this->previous()->line, $this->previous()->column, $key, $val);
                $this->match(TokenType::Comma);
                $this->skipNewlines();
            }
            $this->consume(TokenType::RBrace, "Expected '}'");
            return new RecordExpr($this->previous()->line, $this->previous()->column, $fields);
        }

        if ($this->match(TokenType::LParen)) {
            $this->skipNewlines();
            $expr = $this->parseExpression();
            $this->skipNewlines();
            $this->consume(TokenType::RParen, "Expected ')'");
            return $expr;
        }

        if ($this->match(TokenType::KeywordMatch)) {
            return $this->parseMatchExpr();
        }

        if ($this->match(TokenType::KeywordEmbed)) {
            $lang = $this->consume(TokenType::Identifier, 'Expected language')->value;
            $content = $this->consume(TokenType::String, 'Expected embed content')->value;
            return new EmbedExpr($this->previous()->line, $this->previous()->column, $content, $lang);
        }

        if ($this->match(TokenType::Identifier)) {
            return $this->parseCallOrIdent();
        }

        // Allow keywords to be used as identifiers in expression context
        $keywordTypes = [
            TokenType::KeywordFunc, TokenType::KeywordType, TokenType::KeywordRecord,
            TokenType::KeywordEnum, TokenType::KeywordUnion, TokenType::KeywordNot,
            TokenType::KeywordMatch, TokenType::KeywordNew, TokenType::KeywordThis,
            TokenType::KeywordPublic, TokenType::KeywordPrivate, TokenType::KeywordProtected,
            TokenType::KeywordStatic, TokenType::KeywordReadonly, TokenType::KeywordIntersect,
            TokenType::KeywordLet,
        ];
        foreach ($keywordTypes as $kt) {
            if ($this->match($kt)) {
                return $this->parseCallOrIdent();
            }
        }

        throw new ParserException(
            "Unexpected token: {$this->peek()->type->value} ('{$this->peek()->value}')",
            $this->peek()->line, $this->peek()->column
        );
    }

    private function parseCallOrIdent(): \Cypher\Compiler\AST\ExprNode
    {
        $token = $this->previous();
        $expr = new IdentifierExpr($token->line, $token->column, $token->value);

        while (true) {
            $this->skipNewlines();

            if ($this->match(TokenType::Dot)) {
                if ($this->check(TokenType::Identifier)) {
                    $prop = $this->advance()->value;
                } elseif (!$this->isAtEnd() && $this->peek()->type !== TokenType::Newline && !in_array($this->peek()->type, [
                    TokenType::Equals, TokenType::LBrace, TokenType::RBrace, TokenType::LParen, TokenType::RParen,
                    TokenType::EOF,
                ], true)) {
                    $prop = $this->advance()->value;
                } else {
                    $prop = $this->consume(TokenType::Identifier, 'Expected property name')->value;
                }
                $expr = new PropertyAccessExpr($expr->getLine(), $expr->getColumn(), $expr, $prop);
                continue;
            }

            if ($this->match(TokenType::DoubleColon, TokenType::Colon)) {
                $callee = $expr instanceof IdentifierExpr
                    ? $expr->name
                    : $this->generateExpressionString($expr);

                if ($this->check(TokenType::Identifier)) {
                    $method = $this->advance()->value;
                } else {
                    $method = $this->advance()->value;
                }

                if ($this->match(TokenType::LParen)) {
                    $args = [];
                    while (!$this->check(TokenType::RParen) && !$this->isAtEnd()) {
                        $args[] = $this->parseExpression();
                        $this->match(TokenType::Comma);
                    }
                    $this->consume(TokenType::RParen, "Expected ')'");
                    $expr = new CallExpr($expr->getLine(), $expr->getColumn(), "{$callee}::{$method}", $args);
                } else {
                    $expr = new PropertyAccessExpr($expr->getLine(), $expr->getColumn(), $expr, $method);
                }
                continue;
            }

            if ($this->match(TokenType::LBracket)) {
                $index = $this->parseExpression();
                $this->consume(TokenType::RBracket, "Expected ']'");
                $expr = new IndexExpr($expr->getLine(), $expr->getColumn(), $expr, $index);
                continue;
            }

            if ($this->match(TokenType::LParen)) {
                $args = [];
                while (!$this->check(TokenType::RParen) && !$this->isAtEnd()) {
                    $args[] = $this->parseExpression();
                    $this->match(TokenType::Comma);
                }
                $this->consume(TokenType::RParen, "Expected ')'");
                $expr = new CallExpr($expr->getLine(), $expr->getColumn(),
                    $expr instanceof IdentifierExpr ? $expr->name : $expr,
                    $args
                );
                continue;
            }

            break;
        }

        return $expr;
    }

    private function generateExpressionString($expr): string
    {
        return match ($expr::class) {
            IdentifierExpr::class => $expr->name,
            PropertyAccessExpr::class => $this->generateExpressionString($expr->object) . '->' . $expr->property,
            default => '',
        };
    }

    private function parseMatchExpr(): MatchExpr
    {
        $token = $this->previous();
        $subject = $this->parseExpression();
        $this->skipNewlines();
        $useBraces = $this->match(TokenType::LBrace);
        $arms = [];
        $this->skipNewlines();

        while (!$this->isAtEnd()) {
            if ($useBraces && $this->check(TokenType::RBrace)) break;
            if (!$useBraces && $this->check(TokenType::KeywordEnd)) break;
            if ($this->check(TokenType::EOF)) break;

            $pattern = $this->parseExpression();
            $this->consume(TokenType::Arrow, "Expected '=>'");
            $value = $this->parseExpression();
            $arms[] = new MatchArm($pattern, $value);
            $this->skipNewlines();
        }

        if ($useBraces) {
            $this->consume(TokenType::RBrace, "Expected '}'");
        } else {
            $this->consume(TokenType::KeywordEnd, "Expected 'end'");
        }
        return new MatchExpr($token->line, $token->column, $subject, $arms);
    }

    private function parseInterpolatedString(int $line, int $col, string $raw): InterpolatedStringExpr
    {
        $parts = [];
        $remaining = $raw;
        $pos = 0;

        while (($start = strpos($remaining, '${', $pos)) !== false) {
            if ($start > $pos) {
                $parts[] = new StringPart(false, substr($remaining, $pos, $start - $pos));
            }
            $end = strpos($remaining, '}', $start + 2);
            if ($end === false) {
                $parts[] = new StringPart(false, substr($remaining, $start));
                break;
            }
            $varName = substr($remaining, $start + 2, $end - $start - 2);
            $parts[] = new StringPart(true, $varName, new IdentifierExpr($line, $col, $varName));
            $pos = $end + 1;
        }

        if ($pos < strlen($remaining)) {
            $parts[] = new StringPart(false, substr($remaining, $pos));
        }

        if (empty($parts)) {
            $parts[] = new StringPart(false, $raw);
        }

        return new InterpolatedStringExpr($line, $col, $parts);
    }

    private function skipNewlines(): void
    {
        while ($this->match(TokenType::Newline) || $this->match(TokenType::Semicolon));
    }

    private function match(TokenType ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->check($type)) {
                $this->advance();
                return true;
            }
        }
        return false;
    }

    private function advance(): Token
    {
        if ($this->isAtEnd()) {
            return $this->tokens[$this->len - 1];
        }
        $this->pos++;
        return $this->tokens[$this->pos - 1];
    }

    private function consume(TokenType $type, string $message): Token
    {
        if ($this->check($type)) {
            return $this->advance();
        }
        $tk = $this->peek();
        throw new ParserException(
            "$message. Got '{$tk->value}' ({$tk->type->value})",
            $tk->line, $tk->column
        );
    }

    private function peek(): Token
    {
        return $this->tokens[$this->pos];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->pos - 1];
    }

    private function isAtEnd(): bool
    {
        return $this->peek()->type === TokenType::EOF;
    }
}
