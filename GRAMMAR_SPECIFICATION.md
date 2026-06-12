# CYP Grammar Specification v0.1.0

## Formal Grammar (EBNF)

This document defines the complete formal grammar for the CYP programming language using Extended Backus-Naur Form (EBNF).

### Notation

| Symbol | Meaning |
|--------|---------|
| `::=` | Definition |
| `\|` | Alternation |
| `[ ... ]` | Optional (zero or one) |
| `{ ... }` | Repetition (zero or more) |
| `"..."` | Terminal string |
| `'...'` | Terminal character |
| `(* ... *)` | Comment |
| `.` | End of production |

## 1. Program Structure

```
Program          ::= { Statement } .
Statement        ::= VarDecl
                   | Assignment
                   | SayStmt
                   | IfStmt
                   | WhileStmt
                   | RepeatStmt
                   | ForStmt
                   | ReturnStmt
                   | TaskDecl
                   | FuncDecl
                   | ModelDecl
                   | PageDecl
                   | ApiDecl
                   | ComponentDecl
                   | ClassDecl
                   | AgentDecl
                   | ImportStmt
                   | ExportStmt
                   | TryCatchStmt
                   | ThrowStmt
                   | EmbedStmt
                   | MatchStmt
                   | ExpressionStmt
                   | Comment
                   .
```

## 2. Literals and Identifiers

```
Identifier       ::= Letter { Letter | Digit | "_" } .
Letter           ::= "A" | "B" | ... | "Z" | "a" | "b" | ... | "z" .
Digit            ::= "0" | "1" | ... | "9" .

StringLiteral    ::= '"' { Character | Interpolation } '"' .
Interpolation    ::= '{' Expression '}' .
Character        ::= (* any Unicode character except " and \ *)
                   | EscapeSequence .
EscapeSequence   ::= '\\' ( '"' | '\\' | 'n' | 't' | 'r' | '{' ) .

NumberLiteral    ::= IntegerLiteral | FloatLiteral .
IntegerLiteral   ::= Digit { Digit } .
FloatLiteral     ::= Digit { Digit } '.' Digit { Digit } .

BoolLiteral      ::= "true" | "false" .
NullLiteral      ::= "null" .

Literal          ::= StringLiteral | NumberLiteral | BoolLiteral | NullLiteral .
```

## 3. Types

```
Type             ::= PrimitiveType | ModelType .
PrimitiveType    ::= "int" | "float" | "string" | "bool" | "text"
                   | "datetime" | "json" | "uuid" | "email"
                   | "password" | "vector" | "void" .
ModelType        ::= Identifier .
```

## 4. Variables

```
VarDecl          ::= Identifier '=' Expression Newline .
Assignment       ::= Expression '=' Expression Newline .
```

## 5. Statements

### 5.1 Output

```
SayStmt          ::= "say" Expression Newline .
```

### 5.2 Conditionals

```
IfStmt           ::= "if" Expression Newline
                     { Statement }
                     { "elif" Expression Newline { Statement } }
                     [ "else" Newline { Statement } ]
                     "end" Newline .

IfBlock          ::= "if" Expression Newline { Statement } .
ElifBlock        ::= "elif" Expression Newline { Statement } .
ElseBlock        ::= "else" Newline { Statement } .
```

### 5.3 Loops

```
ForStmt          ::= "for" Identifier "in" Expression Newline
                     { Statement }
                     "end" Newline .

RepeatStmt       ::= "repeat" Expression Newline
                     { Statement }
                     "end" Newline .

WhileStmt        ::= "while" Expression Newline
                     { Statement }
                     "end" Newline .
```

### 5.4 Return

```
ReturnStmt       ::= "return" [ Expression ] Newline .
```

## 6. Functions

```
TaskDecl         ::= [ "public" | "private" ] "task" Identifier '(' [ParamList] ')' [ ':' Type ] Newline
                     { Statement }
                     "end" Newline .

FuncDecl         ::= "func" Identifier '(' [ParamList] ')' "=>" Expression .

ParamList        ::= Param { ',' Param } .
Param            ::= Identifier [ ':' Type ] [ '=' Literal ] .
```

## 7. Models

```
ModelDecl        ::= "model" Identifier Newline
                     { ModelField }
                     { ModelRelationship }
                     "end" Newline .

ModelField       ::= Identifier ':' Type { Attribute } Newline .
Attribute        ::= "unique" | "nullable" | "default" .
ModelRelationship ::= Identifier RelationshipType Identifier Newline .
RelationshipType ::= "belongsTo" | "hasMany" | "hasOne" | "belongsToMany" .
```

## 8. Pages

```
PageDecl         ::= "page" Identifier [ OptionList ] Newline
                     { Statement }
                     "end" Newline .

OptionList       ::= ':' Identifier { ',' Identifier } .
```

## 9. API Routes

```
ApiDecl          ::= "api" HttpMethod StringLiteral Newline
                     { Statement }
                     "end" Newline .

HttpMethod       ::= "GET" | "POST" | "PUT" | "PATCH" | "DELETE" .
```

## 10. Components

```
ComponentDecl    ::= "component" Identifier '(' [PropList] ')' Newline
                     { Statement }
                     "end" Newline .

PropList         ::= Prop { ',' Prop } .
Prop             ::= Identifier [ ':' Type ] .
```

## 11. Classes

```
ClassDecl        ::= "class" Identifier
                     [ "extends" Identifier ]
                     [ "implements" IdentifierList ]
                     Newline
                     { Statement }
                     "end" Newline .

IdentifierList   ::= Identifier { ',' Identifier } .
```

## 12. AI Agents

```
AgentDecl        ::= "agent" Identifier ':' Identifier Newline
                     { AgentBody }
                     "end" Newline .

AgentBody        ::= PromptStmt | TaskDecl .
PromptStmt       ::= "prompt" StringLiteral Newline .
```

## 13. Modules

```
ImportStmt       ::= "import" IdentifierList "from" StringLiteral Newline .
ExportStmt       ::= "export" Identifier Newline .
```

## 14. Error Handling

```
TryCatchStmt     ::= "try" Newline { Statement }
                     "catch" '|' Identifier '|' Newline { Statement }
                     [ "finally" Newline { Statement } ]
                     "end" Newline .

ThrowStmt        ::= "throw" Expression Newline .
```

## 15. Embedded Code

```
EmbedStmt        ::= "embed" Language StringLiteral Newline .
Language         ::= "php" | "js" | "python" | "rust" .
```

## 16. Match

```
MatchStmt        ::= "match" Expression Newline
                     { MatchArm }
                     "end" Newline .

MatchArm         ::= Pattern "=>" Expression Newline .
Pattern          ::= Literal | Identifier .
```

## 17. Expressions

```
Expression       ::= LogicalOr .

LogicalOr        ::= LogicalAnd { ( "or" | "||" ) LogicalAnd } .
LogicalAnd       ::= Equality { ( "and" | "&&" ) Equality } .
Equality         ::= Comparison [ ( "==" | "!=" ) Comparison ] .
Comparison       ::= Term [ ( "<" | ">" | "<=" | ">=" ) Term ] .
Term             ::= Factor { ( '+' | '-' ) Factor } .
Factor           ::= Unary { ( '*' | '/' | '%' ) Unary } .
Unary            ::= ( '!' | '-' | "not" ) Unary | Primary .
Primary          ::= Literal
                   | Identifier
                   | "(" Expression ")"
                   | "[" [ ExpressionList ] "]"
                   | "{" FieldList "}"
                   | "match" Expression { MatchArm } "end"
                   | "func" '(' [ParamList] ')' "=>" Expression
                   | "embed" Language StringLiteral
                   .

ExpressionList   ::= Expression { ',' Expression } .
FieldList        ::= Field { ',' Field } .
Field            ::= Identifier ':' Expression .

CallExpr         ::= Primary '(' [ExpressionList] ')' .
PropertyAccess   ::= Primary '.' Identifier .
IndexExpr        ::= Primary '[' Expression ']' .
```

## 18. Comments

```
Comment          ::= '#' { Character } Newline .
```

## 19. Whitespace

Whitespace (spaces, tabs) is used to separate tokens but is otherwise ignored. Newlines are significant and act as statement terminators.

```
Newline          ::= '\n' | '\r\n' .
Whitespace       ::= ' ' | '\t' .
```

## 20. Keywords (Reserved)

The following identifiers are reserved keywords and cannot be used as variable or function names:

```
agent       and         api         async       await
catch       class       component   elif        else
embed       end         enum        export      extends
false       finally     for         from        func
if          implements  import      in          interface
let         match       model       new         not
null        or          page        private     prompt
protected   public      readonly    record      repeat
return      say         static      task        throw
true        try         type        union       var
while
```

## 21. Operator Precedence (Summary)

| Level | Operators | Associativity |
|-------|-----------|---------------|
| 1 | `=` | Right |
| 2 | `or`, `||` | Left |
| 3 | `and`, `&&` | Left |
| 4 | `==`, `!=` | Left |
| 5 | `<`, `>`, `<=`, `>=` | Left |
| 6 | `+`, `-` | Left |
| 7 | `*`, `/`, `%` | Left |
| 8 | `!`, `-` (unary), `not` | Right |
| 9 | `.`, `()`, `[]` | Left |
