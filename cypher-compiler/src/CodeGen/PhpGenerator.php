<?php

namespace Cypher\Compiler\CodeGen;

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

class PhpGenerator
{
    private int $indentLevel = 0;
    private array $generatedFiles = [];
    private array $config;
    private string $appName = 'App';

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function generate(ModuleNode $module): array
    {
        $this->generatedFiles = [];
        $output = '';

        $output .= "<?php\n\n";
        $output .= "declare(strict_types=1);\n\n";
        $output .= "namespace App;\n\n";

        foreach ($module->statements as $stmt) {
            $output .= $this->generateStatement($stmt);
        }

        $this->generatedFiles['app/App.php'] = $output;

        $routes = $this->generateRoutes();
        $this->generatedFiles['routes/api.php'] = $routes;

        return $this->generatedFiles;
    }

    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }

    private function generateStatement($stmt): string
    {
        return match ($stmt::class) {
            VarDeclStmt::class => $this->generateVarDecl($stmt),
            AssignStmt::class => $this->generateAssign($stmt),
            SayStmt::class => $this->generateSay($stmt),
            IfStmt::class => $this->generateIf($stmt),
            WhileStmt::class => $this->generateWhile($stmt),
            RepeatStmt::class => $this->generateRepeat($stmt),
            ForStmt::class => $this->generateFor($stmt),
            ReturnStmt::class => $this->generateReturn($stmt),
            TaskDeclStmt::class => $this->generateTask($stmt),
            FuncDeclStmt::class => $this->generateFunc($stmt),
            ModelDeclStmt::class => $this->generateModel($stmt),
            PageDeclStmt::class => $this->generatePage($stmt),
            ApiDeclStmt::class => $this->generateApi($stmt),
            ComponentDeclStmt::class => $this->generateComponent($stmt),
            ImportStmt::class => $this->generateImport($stmt),
            ExportStmt::class => '',
            TryCatchStmt::class => $this->generateTryCatch($stmt),
            ThrowStmt::class => $this->generateThrow($stmt),
            ClassDeclStmt::class => $this->generateClass($stmt),
            AgentDeclStmt::class => $this->generateAgent($stmt),
            ExpressionStmt::class => $this->generateExpressionStmt($stmt),
            default => "// Unknown statement\n",
        };
    }

    private function generateVarDecl(VarDeclStmt $stmt): string
    {
        $modifier = $stmt->isMutable ? '' : 'readonly ';
        $type = $stmt->typeHint ? $this->phpType($stmt->typeHint) . ' ' : '';
        $value = $this->generateExpression($stmt->initializer);
        return $this->indent() . "\${$stmt->name} = {$value};\n";
    }

    private function generateAssign(AssignStmt $stmt): string
    {
        $target = $this->generateExpression($stmt->target);
        $value = $this->generateExpression($stmt->value);
        return $this->indent() . "{$target} = {$value};\n";
    }

    private function generateSay(SayStmt $stmt): string
    {
        $value = $this->generateExpression($stmt->expression);
        return $this->indent() . "echo {$value};\n";
    }

    private function generateIf(IfStmt $stmt): string
    {
        $cond = $this->generateExpression($stmt->condition);
        $code = $this->indent() . "if ({$cond}) {\n";
        $this->indentLevel++;
        foreach ($stmt->thenBody as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;

        if ($stmt->elseIf) {
            $code .= $this->indent() . "} " . ltrim($this->generateStatement($stmt->elseIf));
            return $code;
        }

        if ($stmt->elseBody) {
            $code .= $this->indent() . "} else {\n";
            $this->indentLevel++;
            foreach ($stmt->elseBody as $s) $code .= $this->generateStatement($s);
            $this->indentLevel--;
        }

        $code .= $this->indent() . "}\n";
        return $code;
    }

    private function generateWhile(WhileStmt $stmt): string
    {
        $cond = $this->generateExpression($stmt->condition);
        $code = $this->indent() . "while ({$cond}) {\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;
        $code .= $this->indent() . "}\n";
        return $code;
    }

    private function generateRepeat(RepeatStmt $stmt): string
    {
        $count = $this->generateExpression($stmt->count);
        $code = $this->indent() . "for (\$__i = 0; \$__i < {$count}; \$__i++) {\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;
        $code .= $this->indent() . "}\n";
        return $code;
    }

    private function generateFor(ForStmt $stmt): string
    {
        $iterable = $this->generateExpression($stmt->iterable);
        $code = $this->indent() . "foreach ({$iterable} as \${$stmt->variable}) {\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;
        $code .= $this->indent() . "}\n";
        return $code;
    }

    private function generateReturn(ReturnStmt $stmt): string
    {
        if ($stmt->value) {
            $value = $this->generateExpression($stmt->value);
            return $this->indent() . "return {$value};\n";
        }
        return $this->indent() . "return;\n";
    }

    private function generateTask(TaskDeclStmt $stmt): string
    {
        $params = $this->generateParams($stmt->params);
        $returnType = $stmt->returnType ? ': ' . $this->phpType($stmt->returnType) : '';
        $code = $this->indent() . "function {$stmt->name}({$params}){$returnType}\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;
        $code .= $this->indent() . "}\n\n";
        return $code;
    }

    private function generateFunc(FuncDeclStmt $stmt): string
    {
        $params = $this->generateParams($stmt->params);
        $returnType = $stmt->returnType ? ': ' . $this->phpType($stmt->returnType) : '';
        $code = $this->indent() . "function {$stmt->name}({$params}){$returnType}\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;
        $code .= $this->indent() . "}\n\n";
        return $code;
    }

    private function generateParams(array $params): string
    {
        $parts = [];
        foreach ($params as $param) {
            $type = $param->typeHint ? $this->phpType($param->typeHint) . ' ' : '';
            $default = $param->defaultValue ? ' = ' . $this->generateExpression($param->defaultValue) : '';
            $parts[] = "{$type}\${$param->name}{$default}";
        }
        return implode(', ', $parts);
    }

    private function generateModel(ModelDeclStmt $stmt): string
    {
        $table = $stmt->options['table'] ?? strtolower($stmt->name) . 's';
        $extends = $stmt->options['extends'] ?? 'Model';

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Models;\n\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Model;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n\n";
        $code .= "class {$stmt->name} extends {$extends}\n";
        $code .= "{\n";
        $this->indentLevel++;

        $code .= $this->indent() . "protected \$table = '{$table}';\n\n";

        if (!empty($stmt->fields)) {
            $fillable = array_map(fn($f) => "'{$f->name}'", $stmt->fields);
            $code .= $this->indent() . "protected \$fillable = [" . implode(', ', $fillable) . "];\n\n";
        }

        foreach ($stmt->fields as $field) {
            $casts = $this->castType($field->type);
            if ($casts !== null) {
                $code .= $this->indent() . "// {$field->name}: {$field->type}\n";
            }
        }

        if (!empty($stmt->relationships)) {
            $code .= "\n";
            foreach ($stmt->relationships as $rel) {
                $code .= $this->generateRelationship($rel);
            }
        }

        $this->indentLevel--;
        $code .= "}\n";

        $fileKey = "app/Models/{$stmt->name}.php";
        $this->generatedFiles[$fileKey] = $code;
        return "// Model {$stmt->name} generated in {$fileKey}\n";
    }

    private function generateRelationship(ModelRelationship $rel): string
    {
        $method = match ($rel->type) {
            'hasMany', 'has_many' => 'hasMany',
            'belongsTo', 'belongs_to' => 'belongsTo',
            'hasOne', 'has_one' => 'hasOne',
            'belongsToMany', 'belongs_to_many' => 'belongsToMany',
            default => 'hasMany',
        };

        $code = $this->indent() . "public function {$rel->name}(): {$method}\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        $code .= $this->indent() . "return \$this->{$method}({$rel->target}::class, '{$rel->foreignKey}');\n";
        $this->indentLevel--;
        $code .= $this->indent() . "}\n\n";
        return $code;
    }

    private function generatePage(PageDeclStmt $stmt): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Controllers;\n\n";
        $code .= "use Illuminate\\View\\View;\n\n";
        $code .= "class {$stmt->name}Controller extends Controller\n";
        $code .= "{\n";
        $this->indentLevel++;
        $code .= $this->indent() . "public function __invoke(): View\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) {
            $code .= $this->generateStatement($s);
        }
        $code .= $this->indent() . "return view('{$stmt->name}');\n";
        $this->indentLevel--;
        $code .= $this->indent() . "}\n";
        $this->indentLevel--;
        $code .= "}\n";

        $fileKey = "app/Http/Controllers/{$stmt->name}Controller.php";
        $this->generatedFiles[$fileKey] = $code;
        return "// Page {$stmt->name} generated in {$fileKey}\n";
    }

    private function generateApi(ApiDeclStmt $stmt): string
    {
        $route = $this->apiRoute($stmt->method, $stmt->path);
        $handler = [];

        foreach ($stmt->body as $s) {
            $handler[] = $this->generateStatement($s);
        }

        $handlerCode = implode('', $handler);

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Controllers\\Api;\n\n";
        $code .= "use Illuminate\\Http\\JsonResponse;\n";
        $code .= "use Illuminate\\Http\\Request;\n\n";

        $routeName = $this->routeName($stmt->path);
        $code .= "class {$routeName}Controller extends Controller\n";
        $code .= "{\n";
        $this->indentLevel++;
        $code .= $this->indent() . "public function __invoke(Request \$request): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        $code .= $handlerCode;
        $code .= $this->indent() . "return response()->json(['status' => 'ok']);\n";
        $this->indentLevel--;
        $code .= $this->indent() . "}\n";
        $this->indentLevel--;
        $code .= "}\n";

        $fileKey = "app/Http/Controllers/Api/{$routeName}Controller.php";
        $this->generatedFiles[$fileKey] = $code;
        $this->generatedFiles['_routes'][] = [
            'method' => $stmt->method,
            'path' => $stmt->path,
            'controller' => "Api\\{$routeName}Controller",
        ];
        return "// API {$stmt->method} {$stmt->path} generated\n";
    }

    private function generateComponent(ComponentDeclStmt $stmt): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\View\\Components;\n\n";
        $code .= "use Illuminate\\View\\Component;\n";
        $code .= "use Illuminate\\View\\View;\n\n";
        $code .= "class {$stmt->name} extends Component\n";
        $code .= "{\n";
        $this->indentLevel++;

        foreach ($stmt->props as $prop) {
            $type = $prop->typeHint ? $this->phpType($prop->typeHint) . ' ' : '';
            $code .= $this->indent() . "public {$type}\${$prop->name};\n";
        }

        $code .= "\n";
        $code .= $this->indent() . "public function __construct(";
        $ctorParams = [];
        foreach ($stmt->props as $prop) {
            $type = $prop->typeHint ? $this->phpType($prop->typeHint) . ' ' : '';
            $ctorParams[] = "{$type}\${$prop->name}";
        }
        $code .= implode(', ', $ctorParams);
        $code .= ")\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        foreach ($stmt->props as $prop) {
            $code .= $this->indent() . "\$this->{$prop->name} = \${$prop->name};\n";
        }
        $this->indentLevel--;
        $code .= $this->indent() . "}\n\n";
        $code .= $this->indent() . "public function render(): View\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        $code .= $this->indent() . "return view('components.{$stmt->name}');\n";
        $this->indentLevel--;
        $code .= $this->indent() . "}\n";

        $this->indentLevel--;
        $code .= "}\n";

        $fileKey = "app/View/Components/{$stmt->name}.php";
        $this->generatedFiles[$fileKey] = $code;
        return "// Component {$stmt->name} generated\n";
    }

    private function generateImport(ImportStmt $stmt): string
    {
        $names = implode(', ', $stmt->names);
        if ($stmt->source) {
            return $this->indent() . "// import {$names} from '{$stmt->source}'\n";
        }
        return $this->indent() . "// import {$names}\n";
    }

    private function generateTryCatch(TryCatchStmt $stmt): string
    {
        $code = $this->indent() . "try {\n";
        $this->indentLevel++;
        foreach ($stmt->tryBody as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;

        if ($stmt->catchVar) {
            $code .= $this->indent() . "} catch (\\Exception \${$stmt->catchVar}) {\n";
            $this->indentLevel++;
            foreach ($stmt->catchBody as $s) $code .= $this->generateStatement($s);
            $this->indentLevel--;
        } else {
            $code .= $this->indent() . "} catch (\\Exception \$e) {\n";
        }

        if ($stmt->finallyBody) {
            $code .= $this->indent() . "} finally {\n";
            $this->indentLevel++;
            foreach ($stmt->finallyBody as $s) $code .= $this->generateStatement($s);
            $this->indentLevel--;
        }

        $code .= $this->indent() . "}\n";
        return $code;
    }

    private function generateThrow(ThrowStmt $stmt): string
    {
        $expr = $this->generateExpression($stmt->expression);
        return $this->indent() . "throw {$expr};\n";
    }

    private function generateClass(ClassDeclStmt $stmt): string
    {
        $extends = $stmt->extends ? " extends {$stmt->extends}" : '';
        $implements = !empty($stmt->implements) ? ' implements ' . implode(', ', $stmt->implements) : '';
        $code = $this->indent() . "class {$stmt->name}{$extends}{$implements}\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        $this->indentLevel--;
        $code .= $this->indent() . "}\n\n";
        return $code;
    }

    private function generateAgent(AgentDeclStmt $stmt): string
    {
        $code = $this->indent() . "// Agent: {$stmt->name}\n";
        if ($stmt->model) {
            $code .= $this->indent() . "// Model: {$stmt->model}\n";
        }
        if ($stmt->systemPrompt) {
            $escaped = str_replace("'", "\\'", $stmt->systemPrompt);
            $code .= $this->indent() . "\$__prompt = '{$escaped}';\n";
        }
        foreach ($stmt->body as $s) $code .= $this->generateStatement($s);
        return $code;
    }

    private function generateExpressionStmt(ExpressionStmt $stmt): string
    {
        $expr = $this->generateExpression($stmt->expression);
        return $this->indent() . "{$expr};\n";
    }

    private function generateExpression($expr): string
    {
        return match ($expr::class) {
            LiteralExpr::class => $this->generateLiteral($expr),
            IdentifierExpr::class => $this->generateIdentifier($expr),
            BinaryExpr::class => $this->generateBinary($expr),
            UnaryExpr::class => $this->generateUnary($expr),
            CallExpr::class => $this->generateCall($expr),
            PropertyAccessExpr::class => $this->generatePropertyAccess($expr),
            IndexExpr::class => $this->generateIndex($expr),
            ArrayExpr::class => $this->generateArray($expr),
            RecordExpr::class => $this->generateRecord($expr),
            MatchExpr::class => $this->generateMatch($expr),
            TernaryExpr::class => $this->generateTernary($expr),
            InterpolatedStringExpr::class => $this->generateInterpolatedString($expr),
            default => '/* unknown expression */',
        };
    }

    private function generateLiteral(LiteralExpr $expr): string
    {
        return match ($expr->literalType) {
            'string' => "'" . str_replace("'", "\\'", $expr->value) . "'",
            'int', 'float' => (string)$expr->value,
            'bool' => $expr->value ? 'true' : 'false',
            'null' => 'null',
            default => 'null',
        };
    }

    private function generateInterpolatedString(InterpolatedStringExpr $expr): string
    {
        $parts = [];
        foreach ($expr->parts as $part) {
            if ($part->isExpr) {
                $parts[] = $this->generateExpression($part->expression);
            } else {
                $escaped = str_replace("'", "\\'", $part->value);
                $parts[] = "'{$escaped}'";
            }
        }
        return implode(' . ', $parts);
    }

    private function generateIdentifier(IdentifierExpr $expr): string
    {
        return "\${$expr->name}";
    }

    private function generateBinary(BinaryExpr $expr): string
    {
        $left = $this->generateExpression($expr->left);
        $right = $this->generateExpression($expr->right);
        $op = match ($expr->operator) {
            'and' => '&&',
            'or' => '||',
            'not' => '!',
            default => $expr->operator,
        };
        return "({$left} {$op} {$right})";
    }

    private function generateUnary(UnaryExpr $expr): string
    {
        $operand = $this->generateExpression($expr->operand);
        $op = match ($expr->operator) {
            'not' => '!',
            default => $expr->operator,
        };
        return "{$op}{$operand}";
    }

    private function generateCall(CallExpr $expr): string
    {
        $args = array_map(fn($a) => $this->generateExpression($a), $expr->arguments);
        return "{$expr->callee}(" . implode(', ', $args) . ")";
    }

    private function generatePropertyAccess(PropertyAccessExpr $expr): string
    {
        $obj = $this->generateExpression($expr->object);
        return "{$obj}->{$expr->property}";
    }

    private function generateIndex(IndexExpr $expr): string
    {
        $target = $this->generateExpression($expr->target);
        $index = $this->generateExpression($expr->index);
        return "{$target}[{$index}]";
    }

    private function generateArray(ArrayExpr $expr): string
    {
        $elements = array_map(fn($e) => $this->generateExpression($e), $expr->elements);
        return '[' . implode(', ', $elements) . ']';
    }

    private function generateRecord(RecordExpr $expr): string
    {
        $parts = [];
        foreach ($expr->fields as $field) {
            $val = $this->generateExpression($field->value);
            $parts[] = "'{$field->name}' => {$val}";
        }
        return '[' . implode(', ', $parts) . ']';
    }

    private function generateMatch(MatchExpr $expr): string
    {
        $subject = $this->generateExpression($expr->subject);
        $code = "match ({$subject}) {\n";
        $this->indentLevel++;
        foreach ($expr->arms as $arm) {
            $pattern = $this->generateExpression($arm->pattern);
            $value = $this->generateExpression($arm->value);
            $code .= $this->indent() . "{$pattern} => {$value},\n";
        }
        $this->indentLevel--;
        return $code . $this->indent() . "}";
    }

    private function generateTernary(TernaryExpr $expr): string
    {
        $cond = $this->generateExpression($expr->condition);
        $then = $this->generateExpression($expr->thenExpr);
        $else = $this->generateExpression($expr->elseExpr);
        return "({$cond} ? {$then} : {$else})";
    }

    private function generateRoutes(): string
    {
        $routes = $this->generatedFiles['_routes'] ?? [];

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "use Illuminate\\Support\\Facades\\Route;\n\n";

        foreach ($routes as $route) {
            $method = strtolower($route['method']);
            $controller = str_replace('\\', '\\\\', $route['controller']);
            $code .= "Route::{$method}('{$route['path']}', [{$controller}::class, '__invoke']);\n";
        }

        return $code;
    }

    private function apiRoute(string $method, string $path): string
    {
        return strtolower($method) . "::{$path}";
    }

    private function routeName(string $path): string
    {
        $name = trim(str_replace(['/', '-', '.'], ' ', $path));
        $name = str_replace(['{', '}'], '', $name);
        $parts = array_filter(explode(' ', $name));
        $parts = array_map(fn($p) => ucfirst($p), $parts);
        return implode('', $parts) ?: 'Index';
    }

    private function phpType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'int',
            'float', 'double' => 'float',
            'str', 'string' => 'string',
            'bool', 'boolean' => 'bool',
            'array' => 'array',
            'void' => 'void',
            'mixed' => 'mixed',
            'null' => 'null',
            default => $type,
        };
    }

    private function castType(string $type): ?string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'float',
            'bool', 'boolean' => 'boolean',
            'array', 'json' => 'array',
            'datetime', 'date' => 'datetime',
            default => null,
        };
    }

    private function indent(): string
    {
        return str_repeat('    ', $this->indentLevel);
    }
}
