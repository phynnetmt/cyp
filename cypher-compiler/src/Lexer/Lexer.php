<?php

namespace Cypher\Compiler\Lexer;

class Lexer
{
    private string $source;
    private int $pos = 0;
    private int $line = 1;
    private int $column = 1;
    private int $length;

    private const KEYWORDS = [
        'var' => TokenType::KeywordVar,
        'let' => TokenType::KeywordLet,
        'task' => TokenType::KeywordTask,
        'func' => TokenType::KeywordFunc,
        'if' => TokenType::KeywordIf,
        'else' => TokenType::KeywordElse,
        'elif' => TokenType::KeywordElif,
        'end' => TokenType::KeywordEnd,
        'repeat' => TokenType::KeywordRepeat,
        'for' => TokenType::KeywordFor,
        'in' => TokenType::KeywordIn,
        'while' => TokenType::KeywordWhile,
        'return' => TokenType::KeywordReturn,
        'model' => TokenType::KeywordModel,
        'page' => TokenType::KeywordPage,
        'api' => TokenType::KeywordApi,
        'component' => TokenType::KeywordComponent,
        'say' => TokenType::KeywordSay,
        'true' => TokenType::KeywordTrue,
        'false' => TokenType::KeywordFalse,
        'null' => TokenType::KeywordNull,
        'and' => TokenType::KeywordAnd,
        'or' => TokenType::KeywordOr,
        'not' => TokenType::KeywordNot,
        'import' => TokenType::KeywordImport,
        'export' => TokenType::KeywordExport,
        'from' => TokenType::KeywordFrom,
        'class' => TokenType::KeywordClass,
        'new' => TokenType::KeywordNew,
        'this' => TokenType::KeywordThis,
        'extends' => TokenType::KeywordExtends,
        'implements' => TokenType::KeywordImplements,
        'interface' => TokenType::KeywordInterface,
        'enum' => TokenType::KeywordEnum,
        'match' => TokenType::KeywordMatch,
        'try' => TokenType::KeywordTry,
        'catch' => TokenType::KeywordCatch,
        'finally' => TokenType::KeywordFinally,
        'throw' => TokenType::KeywordThrow,
        'async' => TokenType::KeywordAsync,
        'await' => TokenType::KeywordAwait,
        'agent' => TokenType::KeywordAgent,
        'prompt' => TokenType::KeywordPrompt,
        'embed' => TokenType::KeywordEmbed,
        'public' => TokenType::KeywordPublic,
        'private' => TokenType::KeywordPrivate,
        'protected' => TokenType::KeywordProtected,
        'static' => TokenType::KeywordStatic,
        'readonly' => TokenType::KeywordReadonly,
        'type' => TokenType::KeywordType,
        'record' => TokenType::KeywordRecord,
        'union' => TokenType::KeywordUnion,
        'intersect' => TokenType::KeywordIntersect,
    ];

    private const TWO_CHAR_TOKENS = [
        '==' => TokenType::EqualsEquals,
        '!=' => TokenType::NotEquals,
        '<=' => TokenType::LessEquals,
        '>=' => TokenType::GreaterEquals,
        '&&' => TokenType::And,
        '||' => TokenType::Or,
        '=>' => TokenType::Arrow,
        '::' => TokenType::DoubleColon,
    ];

    public function __construct(string $source)
    {
        $this->source = $source;
        $this->length = strlen($source);
    }

    public function tokenize(): array
    {
        $tokens = [];

        while ($this->pos < $this->length) {
            $ch = $this->source[$this->pos];

            if ($ch === "\n") {
                $tokens[] = new Token(TokenType::Newline, "\n", $this->line, $this->column);
                $this->line++;
                $this->column = 1;
                $this->pos++;
                continue;
            }

            if ($ch === "\r") {
                $this->pos++;
                if ($this->pos < $this->length && $this->source[$this->pos] === "\n") {
                    $this->pos++;
                }
                $tokens[] = new Token(TokenType::Newline, "\n", $this->line, $this->column);
                $this->line++;
                $this->column = 1;
                continue;
            }

            if ($ch === ' ' || $ch === "\t") {
                $this->pos++;
                $this->column++;
                continue;
            }

            if ($ch === '#') {
                $tokens[] = $this->readComment();
                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $tokens[] = $this->readIdentifierOrKeyword();
                continue;
            }

            if (ctype_digit($ch)) {
                $tokens[] = $this->readNumber();
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $tokens[] = $this->readString($ch);
                continue;
            }

            $twoChar = ($this->pos + 1 < $this->length)
                ? $this->source[$this->pos] . $this->source[$this->pos + 1]
                : '';

            if (isset(self::TWO_CHAR_TOKENS[$twoChar])) {
                $tokens[] = new Token(self::TWO_CHAR_TOKENS[$twoChar], $twoChar, $this->line, $this->column);
                $this->pos += 2;
                $this->column += 2;
                continue;
            }

            switch ($ch) {
                case '=':
                    $tokens[] = new Token(TokenType::Equals, '=', $this->line, $this->column);
                    break;
                case ':':
                    $tokens[] = new Token(TokenType::Colon, ':', $this->line, $this->column);
                    break;
                case ';':
                    $tokens[] = new Token(TokenType::Semicolon, ';', $this->line, $this->column);
                    break;
                case ',':
                    $tokens[] = new Token(TokenType::Comma, ',', $this->line, $this->column);
                    break;
                case '.':
                    $tokens[] = new Token(TokenType::Dot, '.', $this->line, $this->column);
                    break;
                case '|':
                    $tokens[] = new Token(TokenType::Pipe, '|', $this->line, $this->column);
                    break;
                case '(':
                    $tokens[] = new Token(TokenType::LParen, '(', $this->line, $this->column);
                    break;
                case ')':
                    $tokens[] = new Token(TokenType::RParen, ')', $this->line, $this->column);
                    break;
                case '{':
                    $tokens[] = new Token(TokenType::LBrace, '{', $this->line, $this->column);
                    break;
                case '}':
                    $tokens[] = new Token(TokenType::RBrace, '}', $this->line, $this->column);
                    break;
                case '[':
                    $tokens[] = new Token(TokenType::LBracket, '[', $this->line, $this->column);
                    break;
                case ']':
                    $tokens[] = new Token(TokenType::RBracket, ']', $this->line, $this->column);
                    break;
                case '<':
                    $tokens[] = new Token(TokenType::LessThan, '<', $this->line, $this->column);
                    break;
                case '>':
                    $tokens[] = new Token(TokenType::GreaterThan, '>', $this->line, $this->column);
                    break;
                case '+':
                    $tokens[] = new Token(TokenType::Plus, '+', $this->line, $this->column);
                    break;
                case '-':
                    $tokens[] = new Token(TokenType::Minus, '-', $this->line, $this->column);
                    break;
                case '*':
                    $tokens[] = new Token(TokenType::Star, '*', $this->line, $this->column);
                    break;
                case '/':
                    $tokens[] = new Token(TokenType::Slash, '/', $this->line, $this->column);
                    break;
                case '%':
                    $tokens[] = new Token(TokenType::Percent, '%', $this->line, $this->column);
                    break;
                case '!':
                    $tokens[] = new Token(TokenType::Not, '!', $this->line, $this->column);
                    break;
                case '&':
                    $tokens[] = new Token(TokenType::Ampersand, '&', $this->line, $this->column);
                    break;
                case '^':
                    $tokens[] = new Token(TokenType::Caret, '^', $this->line, $this->column);
                    break;
                case '~':
                    $tokens[] = new Token(TokenType::Tilde, '~', $this->line, $this->column);
                    break;
                case '?':
                    $tokens[] = new Token(TokenType::QuestionMark, '?', $this->line, $this->column);
                    break;
                default:
                    throw new LexerException("Unexpected character: '$ch'", $this->line, $this->column);
            }

            $this->pos++;
            $this->column++;
        }

        $tokens[] = new Token(TokenType::EOF, '', $this->line, $this->column);

        return $this->filterTokens($tokens);
    }

    private function readIdentifierOrKeyword(): Token
    {
        $start = $this->pos;
        $startCol = $this->column;

        while ($this->pos < $this->length && (ctype_alnum($this->source[$this->pos]) || $this->source[$this->pos] === '_')) {
            $this->pos++;
            $this->column++;
        }

        $value = substr($this->source, $start, $this->pos - $start);

        if (isset(self::KEYWORDS[$value])) {
            return new Token(self::KEYWORDS[$value], $value, $this->line, $startCol);
        }

        return new Token(TokenType::Identifier, $value, $this->line, $startCol);
    }

    private function readNumber(): Token
    {
        $start = $this->pos;
        $startCol = $this->column;
        $isFloat = false;

        while ($this->pos < $this->length && ctype_digit($this->source[$this->pos])) {
            $this->pos++;
            $this->column++;
        }

        if ($this->pos < $this->length && $this->source[$this->pos] === '.') {
            $isFloat = true;
            $this->pos++;
            $this->column++;
            while ($this->pos < $this->length && ctype_digit($this->source[$this->pos])) {
                $this->pos++;
                $this->column++;
            }
        }

        $value = substr($this->source, $start, $this->pos - $start);
        return new Token(TokenType::Number, $value, $this->line, $startCol);
    }

    private function readString(string $quote): Token
    {
        $start = $this->pos;
        $startCol = $this->column;
        $this->pos++;
        $this->column++;

        $value = '';

        while ($this->pos < $this->length) {
            $ch = $this->source[$this->pos];

            if ($ch === '\\') {
                $this->pos++;
                $this->column++;
                if ($this->pos >= $this->length) {
                    throw new LexerException("Unterminated string escape", $this->line, $this->column);
                }
                $value .= match ($this->source[$this->pos]) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    '\\' => '\\',
                    '"' => '"',
                    "'" => "'",
                    default => throw new LexerException("Invalid escape sequence", $this->line, $this->column),
                };
                $this->pos++;
                $this->column++;
                continue;
            }

            if ($ch === $quote) {
                $raw = substr($this->source, $start, $this->pos - $start + 1);
                $this->pos++;
                $this->column++;
                return new Token(TokenType::String, $value, $this->line, $startCol);
            }

            if ($ch === "\n") {
                throw new LexerException("Unterminated string", $this->line, $this->column);
            }

            $value .= $ch;
            $this->pos++;
            $this->column++;
        }

        throw new LexerException("Unterminated string", $this->line, $this->column);
    }

    private function readComment(): Token
    {
        $start = $this->pos;
        $startCol = $this->column;
        $this->pos++;
        $this->column++;

        $value = '';

        while ($this->pos < $this->length && $this->source[$this->pos] !== "\n") {
            $value .= $this->source[$this->pos];
            $this->pos++;
            $this->column++;
        }

        return new Token(TokenType::Comment, trim($value), $this->line, $startCol);
    }

    private function filterTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, fn(Token $t) => $t->type !== TokenType::Comment));
    }
}
