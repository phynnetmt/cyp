# CYP Language Specification v0.1.0

## 1. Introduction

CYP (Cypher) is an AI-native, full-stack programming language designed for self-hosting. This specification defines the language syntax, semantics, and execution model for the bootstrap compiler.

### 1.1 Design Goals

- **Self-hosting**: The compiler, runtime, and tools must eventually be written in CYP
- **Simplicity**: Minimal syntax, maximal expressiveness
- **Full-stack**: Single language for frontend, backend, database, and AI
- **AI-native**: Agents, memory, and reasoning as first-class primitives

### 1.2 Notation

Syntax definitions use a modified BNF notation:
- `::=` defines a production
- `|` alternation
- `[]` optional
- `{}` repetition (zero or more)
- Literals in `"double quotes"`
- Identifiers in `<angle brackets>`

## 2. Lexical Structure

### 2.1 Comments

```
# This is a line comment
```

Comments begin with `#` and extend to the end of the line.

### 2.2 Identifiers

```
<identifier> ::= <letter> { <letter> | <digit> | "_" }
<letter>     ::= "a"..."z" | "A"..."Z"
<digit>      ::= "0"..."9"
```

Identifiers are case-sensitive. Reserved keywords cannot be used as identifiers.

### 2.3 Keywords

```
agent      and        api        async      await
catch      class      component  elif       else
embed      end        enum       export     extends
false      finally    for        from       func
if         implements import     in         interface
let        match      model      new        not
null       or         page       private    prompt
protected  public     readonly   record     repeat
return     say        static     task       throw
true       try        type       union      var
while
```

### 2.4 Literals

```
<literal>      ::= <string-literal> | <number-literal>
                 | <bool-literal> | <null-literal>
<string-literal> ::= '"' { <character> | <interpolation> } '"'
<interpolation>  ::= "{" <expression> "}"
<number-literal> ::= <digit> { <digit> }
                   | <digit> { <digit> } "." <digit> { <digit> }
<bool-literal>  ::= "true" | "false"
<null-literal>  ::= "null"
```

### 2.5 Operators

```
Arithmetic:     +  -  *  /  %
Comparison:     ==  !=  <  >  <=  >=
Logical:        &&  ||  !  and  or  not
Assignment:     =
Other:          =>  ::  .  ..  ?  :  |  ;
```

## 3. Variables and Declarations

### 3.1 Variable Declaration

```
<var-decl> ::= <identifier> "=" <expression>
```

Variables are implicitly declared on first assignment. Type is inferred from the value.

```
name = "Cypher"     # string
count = 42          # int
price = 19.99       # float
active = true       # bool
```

### 3.2 Reassignment

```
<assignment> ::= <identifier> "=" <expression>
```

Variables are mutable by default.

```
name = "Cypher"
name = "CYP 2.0"    # reassignment
```

## 4. Functions (Tasks)

### 4.1 Function Declaration

```
<task-decl>    ::= "task" <identifier> "(" [<params>] ")" [":" <type>] <body> "end"
<params>       ::= <param> { "," <param> }
<param>        ::= <identifier> [":" <type>]
<body>         ::= { <statement> }
```

### 4.2 Examples

```
task add(a: int, b: int): int
    return a + b
end

task greet(name)
    say "Hello, {name}!"
end
```

### 4.3 Lambda / Arrow Functions

```
<lambda> ::= "func" <identifier> "(" [<params>] ")" "=>" <expression>
```

```
func double(x) => x * 2
func add(a, b) => a + b
```

## 5. Built-in Functions

### 5.1 Output

```
say <expression>
```

Prints the value to standard output. Supports string interpolation.

```
say "Hello, World!"
say "Value is {x}"
```

## 6. Control Flow

### 6.1 If / Elif / Else

```
<if-stmt> ::= "if" <expression> <body>
              { "elif" <expression> <body> }
              [ "else" <body> ]
              "end"
```

```
if x > 0
    say "positive"
elif x == 0
    say "zero"
else
    say "negative"
end
```

### 6.2 For Loop

```
<for-stmt> ::= "for" <identifier> "in" <expression> <body> "end"
```

```
items = ["apple", "banana", "cherry"]
for item in items
    say "Fruit: {item}"
end
```

### 6.3 Repeat Loop

```
<repeat-stmt> ::= "repeat" <expression> <body> "end"
```

```
repeat 5
    say "Hello"
end
```

### 6.4 While Loop

```
<while-stmt> ::= "while" <expression> <body> "end"
```

```
while count > 0
    say count
    count = count - 1
end
```

### 6.5 Return

```
<return-stmt> ::= "return" [<expression>]
```

```
task add(a, b)
    return a + b
end
```

## 7. Match Expressions

```
<match-expr> ::= "match" <expression> <match-arms> "end"
<match-arm>  ::= <literal> "=>" <expression>
```

```
status = 200
result = match status
    200 => "OK"
    404 => "Not Found"
    500 => "Server Error"
end
```

## 8. Data Structures

### 8.1 Arrays

```
<array-literal> ::= "[" [<expression> { "," <expression> }] "]"
<index-expr>    ::= <expression> "[" <expression> "]"
```

```
items = [1, 2, 3, 4, 5]
first = items[0]
```

### 8.2 Records (Objects)

```
<record-literal> ::= "{" <field> { "," <field> } "}"
<field>          ::= <identifier> ":" <expression>
<property-access> ::= <expression> "." <identifier>
```

```
user = {name: "Nero", age: 30}
say user.name
```

## 9. Models

```
<model-decl> ::= "model" <identifier>
                 { <field-decl> }
                 { <relationship-decl> }
                 "end"
<field-decl> ::= <identifier> ":" <type> [<attributes>]
<attributes> ::= { "unique" | "nullable" }
<relationship-decl> ::= <identifier> <rel-type> <identifier>
<rel-type>   ::= "belongsTo" | "hasMany" | "hasOne" | "belongsToMany"
```

```
model User
    id: int
    name: string
    email: string unique
    password: string
    posts hasMany Post
end
```

## 10. Pages

```
<page-decl> ::= "page" <identifier> [<options>] <body> "end"
```

```
page home
    title = "Welcome"
    say "<h1>{title}</h1>"
end
```

## 11. API Routes

```
<api-decl> ::= "api" <http-method> <string-literal> <body> "end"
<http-method> ::= "GET" | "POST" | "PUT" | "PATCH" | "DELETE"
```

```
api GET "/api/users"
    return User::all()
end
```

## 12. Components

```
<component-decl> ::= "component" <identifier> "(" [<props>] ")" <body> "end"
<props>          ::= <identifier> { "," <identifier> }
```

```
component Card(title: string, body: string)
    say "<div class='card'><h2>{title}</h2><p>{body}</p></div>"
end
```

## 13. AI Agents

```
<agent-decl> ::= "agent" <identifier> ":" <model> <body> "end"
<model>      ::= <identifier>
```

```
agent Assistant: gpt4
    prompt "You are a helpful assistant"
    task answer(question)
        response = ask(question)
        return response
    end
end
```

## 14. Type System

### 14.1 Primitive Types

| Type    | Description     | Example       |
|---------|-----------------|---------------|
| int     | Integer number  | 42            |
| float   | Floating-point  | 3.14          |
| string  | Text            | "hello"       |
| bool    | Boolean         | true          |
| null    | Null value      | null          |

### 14.2 Composite Types

| Type      | Description       | Example                 |
|-----------|-------------------|-------------------------|
| array     | Ordered list      | [1, 2, 3]              |
| record    | Key-value object  | {name: "Nero"}         |
| function  | Callable          | func double(x) => x*2  |

### 14.3 Model Field Types

| Type     | Database Equivalent | Usage            |
|----------|--------------------|------------------|
| int      | BIGINT             | Numeric IDs      |
| float    | DOUBLE PRECISION   | Prices, metrics  |
| string   | VARCHAR(255)       | Names, labels    |
| text     | TEXT               | Long content     |
| bool     | BOOLEAN            | Flags            |
| datetime | TIMESTAMP          | Timestamps       |
| json     | JSONB              | Structured data  |
| uuid     | UUID               | Universal IDs    |
| email    | VARCHAR(255)       | Email addresses  |
| password | VARCHAR(255)       | Hashed passwords |
| vector   | vector(1536)       | AI embeddings    |

## 15. Modules and Imports

```
<import-stmt> ::= "import" <identifier> "from" <string-literal>
<export-stmt> ::= "export" <identifier>
```

```
import { parseUser } from "./utils"
import http from "cypher-std/http"

export task formatDate(date)
    return date.format("YYYY-MM-DD")
end
```

## 16. Error Handling

```
<try-stmt>  ::= "try" <body>
                "catch" "|" <identifier> "|" <body>
                [ "finally" <body> ]
                "end"
<throw-stmt> ::= "throw" <expression>
```

```
try
    result = riskyOperation()
    say "Result: {result}"
catch |e|
    say "Error: {e}"
finally
    cleanup()
end
```

## 17. Classes

```
<class-decl> ::= "class" <identifier>
                 [ "extends" <identifier> ]
                 [ "implements" <identifier> { "," <identifier> } ]
                 <body>
                 "end"
```

## 18. Embedded Code

```
<embed-stmt> ::= "embed" <language> <string-literal>
<language>   ::= "php" | "js" | "python"
```

```
embed php "echo 'Hello from embedded PHP';"
```

## 19. Comments

```
# This is a line comment
<comment> ::= "#" { <character> }
```

## 20. Precedence and Associativity

| Precedence | Operator(s)          | Associativity |
|------------|----------------------|---------------|
| 1 (lowest) | `=`                  | Right         |
| 2          | `or`, `and`          | Left          |
| 3          | `==`, `!=`, `<`, `>`, `<=`, `>=` | Left |
| 4          | `+`, `-`             | Left          |
| 5          | `*`, `/`, `%`        | Left          |
| 6          | `!`, `-` (unary)     | Right         |
| 7 (highest)| `.`, `()`, `[]`      | Left          |
