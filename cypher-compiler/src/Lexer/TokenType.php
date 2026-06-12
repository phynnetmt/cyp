<?php

namespace Cypher\Compiler\Lexer;

enum TokenType: string
{
    case Identifier = 'IDENTIFIER';
    case Number = 'NUMBER';
    case String = 'STRING';
    case Boolean = 'BOOLEAN';

    case Equals = '=';
    case QuestionMark = '?';
    case Colon = ':';
    case DoubleColon = '::';
    case Semicolon = ';';
    case Comma = ',';
    case Dot = '.';
    case Arrow = '=>';
    case Pipe = '|';
    case LParen = '(';
    case RParen = ')';
    case LBrace = '{';
    case RBrace = '}';
    case LBracket = '[';
    case RBracket = ']';
    case LessThan = '<';
    case GreaterThan = '>';
    case Plus = '+';
    case Minus = '-';
    case Star = '*';
    case Slash = '/';
    case Percent = '%';
    case Not = '!';
    case Ampersand = '&';
    case Caret = '^';
    case Tilde = '~';

    case EqualsEquals = '==';
    case NotEquals = '!=';
    case LessEquals = '<=';
    case GreaterEquals = '>=';
    case And = '&&';
    case Or = '||';

    case KeywordVar = 'VAR';
    case KeywordLet = 'LET';
    case KeywordTask = 'TASK';
    case KeywordFunc = 'FUNC';
    case KeywordIf = 'IF';
    case KeywordElse = 'ELSE';
    case KeywordElif = 'ELIF';
    case KeywordEnd = 'END';
    case KeywordRepeat = 'REPEAT';
    case KeywordFor = 'FOR';
    case KeywordIn = 'IN';
    case KeywordWhile = 'WHILE';
    case KeywordReturn = 'RETURN';
    case KeywordModel = 'MODEL';
    case KeywordPage = 'PAGE';
    case KeywordApi = 'API';
    case KeywordComponent = 'COMPONENT';
    case KeywordSay = 'SAY';
    case KeywordTrue = 'TRUE';
    case KeywordFalse = 'FALSE';
    case KeywordNull = 'NULL';
    case KeywordAnd = 'AND';
    case KeywordOr = 'OR';
    case KeywordNot = 'NOT';
    case KeywordImport = 'IMPORT';
    case KeywordExport = 'EXPORT';
    case KeywordFrom = 'FROM';
    case KeywordClass = 'CLASS';
    case KeywordNew = 'NEW';
    case KeywordThis = 'THIS';
    case KeywordExtends = 'EXTENDS';
    case KeywordImplements = 'IMPLEMENTS';
    case KeywordInterface = 'INTERFACE';
    case KeywordEnum = 'ENUM';
    case KeywordMatch = 'MATCH';
    case KeywordTry = 'TRY';
    case KeywordCatch = 'CATCH';
    case KeywordFinally = 'FINALLY';
    case KeywordThrow = 'THROW';
    case KeywordAsync = 'ASYNC';
    case KeywordAwait = 'AWAIT';
    case KeywordAgent = 'AGENT';
    case KeywordPrompt = 'PROMPT';
    case KeywordEmbed = 'EMBED';
    case KeywordPublic = 'PUBLIC';
    case KeywordPrivate = 'PRIVATE';
    case KeywordProtected = 'PROTECTED';
    case KeywordStatic = 'STATIC';
    case KeywordReadonly = 'READONLY';
    case KeywordType = 'TYPE';
    case KeywordRecord = 'RECORD';
    case KeywordUnion = 'UNION';
    case KeywordIntersect = 'INTERSECT';

    case Newline = 'NEWLINE';
    case Comment = 'COMMENT';
    case EOF = 'EOF';

    public function isKeyword(): bool
    {
        return str_starts_with($this->value, 'KEYWORD_');
    }
}
