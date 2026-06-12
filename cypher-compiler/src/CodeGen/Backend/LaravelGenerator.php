<?php

namespace Cypher\Compiler\CodeGen\Backend;

use Cypher\Compiler\AST\{
    ModuleNode, VarDeclStmt, AssignStmt, SayStmt, IfStmt, WhileStmt,
    RepeatStmt, ForStmt, ReturnStmt, TaskDeclStmt, FuncDeclStmt,
    ModelDeclStmt, PageDeclStmt, ApiDeclStmt, ComponentDeclStmt,
    ImportStmt, ExportStmt, TryCatchStmt, ThrowStmt, ClassDeclStmt,
    AgentDeclStmt, ExpressionStmt, ParamDecl, ModelField, ModelRelationship,
    LiteralExpr, IdentifierExpr, BinaryExpr, UnaryExpr, CallExpr,
    PropertyAccessExpr, IndexExpr, ArrayExpr, RecordExpr, FieldExpr,
    MatchExpr, MatchArm, LambdaExpr, EmbedExpr, TernaryExpr,
    InterpolatedStringExpr, StringPart, Node, ExprNode, StmtNode,
};

class LaravelGenerator
{
    private int $indentLevel = 0;
    private array $generatedFiles = [];
    private array $config;
    private array $routes = [];
    private array $models = [];
    private int $migrationCounter = 0;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function generate(ModuleNode $module): array
    {
        $this->generatedFiles = [];
        $this->routes = [];
        $this->models = [];

        $hasStructuredStmts = false;

        // Process all statements to collect models and APIs
        foreach ($module->statements as $stmt) {
            $result = $this->generateStatement($stmt);
            if ($result !== '') $hasStructuredStmts = true;
        }

        // Generate fallback script for bare statements (say, var, etc.)
        if (!empty($module->statements)) {
            $bareStmts = array_filter($module->statements, fn($s) => !($s instanceof ModelDeclStmt || $s instanceof ApiDeclStmt || $s instanceof PageDeclStmt || $s instanceof TaskDeclStmt));
            if (!empty($bareStmts)) {
                $this->generateFallbackScript($bareStmts);
            }
        }

        // Generate routes file from collected routes
        $this->generateRoutesFile();

        // Generate base controller
        $this->generateBaseController();

        return $this->generatedFiles;
    }

    private function generateStatement($stmt): string
    {
        return match ($stmt::class) {
            ModelDeclStmt::class => $this->generateModel($stmt),
            ApiDeclStmt::class => $this->generateApi($stmt),
            PageDeclStmt::class => $this->generatePage($stmt),
            TaskDeclStmt::class => $this->generateTask($stmt),
            default => '', // Will be handled by generateFallbackScript
        };
    }

    private function generateModel(ModelDeclStmt $stmt): string
    {
        $table = $this->tableName($stmt->name);
        $modelName = $stmt->name;
        $this->models[$modelName] = $stmt;

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Models;\n\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Model;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Relations\\HasOne;\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany;\n\n";

        $extends = $stmt->options['extends'] ?? 'Model';
        $code .= "class {$modelName} extends {$extends}\n";
        $code .= "{\n";
        $this->indentLevel = 1;

        $code .= $this->indent() . "use HasFactory;\n\n";

        $code .= $this->indent() . "protected \$table = '{$table}';\n\n";

        // Fillable fields (exclude id from fillable)
        $fillable = array_map(fn($f) => $f->name, array_filter($stmt->fields, fn($f) => $f->name !== 'id'));
        if (!empty($fillable)) {
            $fillableStr = implode("', '", $fillable);
            $code .= $this->indent() . "protected \$fillable = ['{$fillableStr}'];\n\n";
        }

        // Hidden fields
        $hidden = array_filter($stmt->fields, fn($f) => in_array($f->name, ['password', 'remember_token']));
        if (!empty($hidden)) {
            $hiddenStr = implode("', '", array_map(fn($f) => $f->name, $hidden));
            $code .= $this->indent() . "protected \$hidden = ['{$hiddenStr}'];\n\n";
        }

        // Casts
        $casts = [];
        foreach ($stmt->fields as $field) {
            $cast = $this->castType($field->type);
            if ($cast) {
                $casts[$field->name] = $cast;
            }
        }
        if (!empty($casts)) {
            $code .= $this->indent() . "protected function casts(): array\n";
            $code .= $this->indent() . "{\n";
            $this->indentLevel++;
            $code .= $this->indent() . "return [\n";
            $this->indentLevel++;
            foreach ($casts as $name => $type) {
                $code .= $this->indent() . "'{$name}' => '{$type}',\n";
            }
            $this->indentLevel--;
            $code .= $this->indent() . "];\n";
            $this->indentLevel--;
            $code .= $this->indent() . "}\n\n";
        }

        // Relationships
        foreach ($stmt->relationships as $rel) {
            $code .= $this->generateModelRelationship($rel);
        }

        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Models/{$modelName}.php"] = $code;

        // Generate migration
        $this->generateMigration($stmt);

        // Generate factory
        $this->generateFactory($stmt);

        // Generate seeder
        $this->generateSeeder($stmt);

        // Generate resource controller if referenced by API
        $this->generateResourceController($stmt);

        return "// Model {$modelName} generated\n";
    }

    private function generateMigration(ModelDeclStmt $stmt): void
    {
        $table = $this->tableName($stmt->name);
        $this->migrationCounter++;
        $timestamp = date('Y_m_d_His') . sprintf('%03d', $this->migrationCounter);
        $className = 'Create' . ucfirst($this->camelCase($table)) . 'Table';

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $code .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $code .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $code .= "return new class extends Migration\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "public function up(): void\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "Schema::create('{$table}', function (Blueprint \$table) {\n";
        $this->indentLevel = 3;
        $code .= $this->indent() . "\$table->id();\n";

        foreach ($stmt->fields as $field) {
            $colType = $this->columnType($field->type);
            $modifiers = $this->columnModifiers($field);
            $code .= $this->indent() . "\$table->{$colType}('{$field->name}'){$modifiers};\n";
        }

        // Foreign keys from relationships
        foreach ($stmt->relationships as $rel) {
            if (in_array($rel->type, ['belongsTo', 'BelongsTo', 'hasOne', 'HasOne'])) {
                $code .= $this->indent() . "\$table->foreignId('{$rel->foreignKey}')->constrained()->cascadeOnDelete();\n";
            }
        }

        $code .= $this->indent() . "\$table->timestamps();\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "});\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n\n";
        $code .= $this->indent() . "public function down(): void\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "Schema::dropIfExists('{$table}');\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "};\n";

        $this->generatedFiles["database/migrations/{$timestamp}_create_{$table}_table.php"] = $code;
    }

    private function generateFactory(ModelDeclStmt $stmt): void
    {
        $modelName = $stmt->name;

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace Database\\Factories;\n\n";
        $code .= "use App\\Models\\{$modelName};\n";
        $code .= "use Illuminate\\Database\\Eloquent\\Factories\\Factory;\n\n";
        $code .= "class {$modelName}Factory extends Factory\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "protected \$model = {$modelName}::class;\n\n";
        $code .= $this->indent() . "public function definition(): array\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "return [\n";
        $this->indentLevel = 3;
        foreach ($stmt->fields as $field) {
            $code .= $this->indent() . "'{$field->name}' => " . $this->factoryValue($field) . ",\n";
        }
        $this->indentLevel = 2;
        $code .= $this->indent() . "];\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["database/factories/{$modelName}Factory.php"] = $code;
    }

    private function generateSeeder(ModelDeclStmt $stmt): void
    {
        $modelName = $stmt->name;

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace Database\\Seeders;\n\n";
        $code .= "use App\\Models\\{$modelName};\n";
        $code .= "use Illuminate\\Database\\Seeder;\n\n";
        $code .= "class {$modelName}Seeder extends Seeder\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "public function run(): void\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "{$modelName}::factory()->count(10)->create();\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["database/seeders/{$modelName}Seeder.php"] = $code;
    }

    private function generateModelRelationship(ModelRelationship $rel): string
    {
        $method = $this->relationshipMethod($rel->type);
        $code = $this->indent() . "public function {$rel->name}(): {$method}\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel++;
        $code .= $this->indent() . "return \$this->{$method}(\\App\\Models\\{$rel->target}::class, '{$rel->foreignKey}');\n";
        $this->indentLevel--;
        $code .= $this->indent() . "}\n\n";
        return $code;
    }

    private function generateResourceController(ModelDeclStmt $stmt): void
    {
        $modelName = $stmt->name;
        $plural = $this->pluralize($this->camelCase($modelName));
        $controllerName = "{$modelName}Controller";

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Controllers;\n\n";
        $code .= "use App\\Models\\{$modelName};\n";
        $code .= "use App\\Http\\Requests\\Store{$modelName}Request;\n";
        $code .= "use App\\Http\\Requests\\Update{$modelName}Request;\n";
        $code .= "use App\\Http\\Resources\\{$modelName}Resource;\n";
        $code .= "use Illuminate\\Http\\JsonResponse;\n";
        $code .= "use Illuminate\\Http\\Request;\n\n";

        $code .= "class {$controllerName} extends Controller\n";
        $code .= "{\n";
        $this->indentLevel = 1;

        // index
        $code .= $this->indent() . "public function index(): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "\${$plural} = {$modelName}::all();\n";
        $code .= $this->indent() . "return response()->json({$modelName}Resource::collection(\${$plural}));\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n\n";

        // store
        $code .= $this->indent() . "public function store(Store{$modelName}Request \$request): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "\${$plural} = {$modelName}::create(\$request->validated());\n";
        $code .= $this->indent() . "return response()->json(new {$modelName}Resource(\${$plural}), 201);\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n\n";

        // show
        $code .= $this->indent() . "public function show({$modelName} \${$plural}): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "return response()->json(new {$modelName}Resource(\${$plural}));\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n\n";

        // update
        $code .= $this->indent() . "public function update(Update{$modelName}Request \$request, {$modelName} \${$plural}): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "\${$plural}->update(\$request->validated());\n";
        $code .= $this->indent() . "return response()->json(new {$modelName}Resource(\${$plural}));\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n\n";

        // destroy
        $code .= $this->indent() . "public function destroy({$modelName} \${$plural}): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "\${$plural}->delete();\n";
        $code .= $this->indent() . "return response()->json(null, 204);\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";

        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Http/Controllers/{$controllerName}.php"] = $code;

        // Generate Form Request
        $this->generateFormRequest($modelName, 'Store', $stmt->fields);
        $this->generateFormRequest($modelName, 'Update', $stmt->fields);
        $this->generateResource($modelName);

        // Add resource route
        $this->routes[] = ['type' => 'resource', 'name' => $this->kebabCase($plural), 'controller' => $controllerName];
    }

    private function generateFormRequest(string $model, string $action, array $fields = []): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Requests;\n\n";
        $code .= "use Illuminate\\Foundation\\Http\\FormRequest;\n\n";
        $code .= "class {$action}{$model}Request extends FormRequest\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "public function authorize(): bool\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "return true;\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n\n";
        $code .= $this->indent() . "public function rules(): array\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "return [\n";
        $this->indentLevel = 3;
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $rules = $this->validationRules($field, $action === 'Update');
                if (!empty($rules)) {
                    $code .= $this->indent() . "'{$field->name}' => [{$rules}],\n";
                }
            }
        } else {
            $code .= $this->indent() . "// Define validation rules\n";
        }
        $this->indentLevel = 2;
        $code .= $this->indent() . "];\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Http/Requests/{$action}{$model}Request.php"] = $code;
    }

    private function validationRules(ModelField $field, bool $isUpdate = false): string
    {
        $rules = [];
        $type = strtolower($field->type);

        // Required rule
        if (!in_array('nullable', $field->attributes)) {
            $rules[] = $isUpdate ? "'sometimes'" : "'required'";
        } else {
            $rules[] = "'nullable'";
        }

        // Type-based rules
        $rules[] = match ($type) {
            'int', 'integer', 'bigint' => "'integer'",
            'float', 'double', 'decimal' => "'numeric'",
            'string', 'varchar', 'text' => "'string'",
            'bool', 'boolean' => "'boolean'",
            'email' => "'email'",
            'password' => "'string', 'min:8'",
            'date' => "'date'",
            'datetime', 'timestamp' => "'date'",
            'json' => "'json'",
            'array' => "'array'",
            'uuid' => "'uuid'",
            default => "'string'",
        };

        // Max length for string types
        if (in_array($type, ['string', 'varchar'])) {
            $rules[] = "'max:255'";
        }

        // Unique rule
        if (in_array('unique', $field->attributes)) {
            $table = $this->tableName($this->modelNameFromField($field));
            $rules[] = $isUpdate ? "'unique:{$table},'.\$this->route('{$table}')" : "'unique:{$table}'";
        }

        return implode(', ', $rules);
    }

    private function modelNameFromField(ModelField $field): string
    {
        return ucfirst(str_replace('_id', '', $field->name));
    }

    private function generateResource(string $model): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Resources;\n\n";
        $code .= "use Illuminate\\Http\\Resources\\Json\\JsonResource;\n\n";
        $code .= "class {$model}Resource extends JsonResource\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "public function toArray(\$request): array\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        $code .= $this->indent() . "return parent::toArray(\$request);\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Http/Resources/{$model}Resource.php"] = $code;
    }

    private function generateApi($stmt): string
    {
        $routeName = $this->routeControllerName($stmt->path);
        $method = strtolower($stmt->method);

        $this->routes[] = [
            'type' => 'api',
            'method' => $method,
            'path' => $stmt->path,
            'controller' => "Api\\{$routeName}Controller",
        ];

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Controllers\\Api;\n\n";
        $code .= "use App\\Http\\Controllers\\Controller;\n";
        $code .= "use Illuminate\\Http\\JsonResponse;\n";
        $code .= "use Illuminate\\Http\\Request;\n\n";

        $code .= "class {$routeName}Controller extends Controller\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "public function __invoke(Request \$request): JsonResponse\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;

        foreach ($stmt->body as $s) {
            $code .= $this->generateBackendStatement($s);
        }

        $code .= $this->indent() . "return response()->json(['status' => 'ok']);\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Http/Controllers/Api/{$routeName}Controller.php"] = $code;
        return "// API {$stmt->method} {$stmt->path} generated\n";
    }

    private function generatePage($stmt): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Controllers;\n\n";
        $code .= "use Illuminate\\View\\View;\n\n";
        $code .= "class {$stmt->name}Controller extends Controller\n";
        $code .= "{\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "public function __invoke(): View\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;

        foreach ($stmt->body as $s) {
            $code .= $this->generateBackendStatement($s);
        }

        $viewName = $this->kebabCase($stmt->name);
        $code .= $this->indent() . "return view('{$viewName}');\n";
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Http/Controllers/{$stmt->name}Controller.php"] = $code;
        $this->routes[] = [
            'type' => 'page',
            'method' => 'get',
            'path' => '/' . $this->kebabCase($stmt->name),
            'controller' => $stmt->name . 'Controller',
        ];

        // Generate Blade view
        $this->generateBladeView($stmt);

        return "// Page {$stmt->name} generated\n";
    }

    private function generateBladeView($stmt): void
    {
        $viewName = $this->kebabCase($stmt->name);
        $code = "<x-app-layout>\n";
        $code .= "    <div class=\"py-12\">\n";
        $code .= "        <div class=\"max-w-7xl mx-auto sm:px-6 lg:px-8\">\n";
        $code .= "            <div class=\"bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg\">\n";
        $code .= "                <div class=\"p-6 text-gray-900 dark:text-gray-100\">\n";
        $code .= "                    <h1 class=\"text-2xl font-semibold\">{$stmt->name}</h1>\n";
        $code .= "                </div>\n";
        $code .= "            </div>\n";
        $code .= "        </div>\n";
        $code .= "    </div>\n";
        $code .= "</x-app-layout>\n";

        $this->generatedFiles["resources/views/{$viewName}.blade.php"] = $code;
    }

    private function generateTask($stmt): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Actions;\n\n";
        $code .= "class {$stmt->name}Action\n";
        $code .= "{\n";
        $this->indentLevel = 1;

        $params = $this->formatParams($stmt->params);
        $returnType = $stmt->returnType ? ': ' . $stmt->returnType : '';
        $code .= $this->indent() . "public function execute({$params}){$returnType}\n";
        $code .= $this->indent() . "{\n";
        $this->indentLevel = 2;
        foreach ($stmt->body as $s) {
            $code .= $this->generateBackendStatement($s);
        }
        $this->indentLevel = 1;
        $code .= $this->indent() . "}\n";
        $this->indentLevel = 0;
        $code .= "}\n";

        $this->generatedFiles["app/Actions/{$stmt->name}Action.php"] = $code;
        return "// Task {$stmt->name} generated\n";
    }

    private function generateBackendStatement($stmt): string
    {
        return match ($stmt::class) {
            VarDeclStmt::class => $this->indent() . "\${$stmt->name} = {$this->generateExpr($stmt->initializer)};\n",
            AssignStmt::class => $this->indent() . "{$this->generateExpr($stmt->target)} = {$this->generateExpr($stmt->value)};\n",
            SayStmt::class => $this->indent() . "echo {$this->generateExpr($stmt->expression)};\n",
            ReturnStmt::class => $stmt->value
                ? $this->indent() . "return {$this->generateExpr($stmt->value)};\n"
                : $this->indent() . "return;\n",
            ExpressionStmt::class => $this->indent() . "{$this->generateExpr($stmt->expression)};\n",
            default => '',
        };
    }

    private function generateExpr($expr): string
    {
        if ($expr instanceof ExprNode) {
            return $this->generateExprNode($expr);
        }
        return '/* expr */';
    }

    private function generateExprNode(ExprNode $expr): string
    {
        return match ($expr::class) {
            LiteralExpr::class => match ($expr->literalType) {
                'string' => "'" . str_replace("'", "\\'", $expr->value) . "'",
                'int', 'float' => (string)$expr->value,
                'bool' => $expr->value ? 'true' : 'false',
                'null' => 'null',
                default => 'null',
            },
            IdentifierExpr::class => "\${$expr->name}",
            BinaryExpr::class => '(' . $this->generateExpr($expr->left) . ' ' . $expr->operator . ' ' . $this->generateExpr($expr->right) . ')',
            UnaryExpr::class => $expr->operator . $this->generateExpr($expr->operand),
            CallExpr::class => $this->generateCallExpr($expr),
            PropertyAccessExpr::class => $this->generateExpr($expr->object) . '->' . $expr->property,
            IndexExpr::class => $this->generateExpr($expr->target) . '[' . $this->generateExpr($expr->index) . ']',
            ArrayExpr::class => '[' . implode(', ', array_map(fn($e) => $this->generateExpr($e), $expr->elements)) . ']',
            RecordExpr::class => $this->generateRecord($expr),
            InterpolatedStringExpr::class => $this->generateInterpolated($expr),
            MatchExpr::class => '/* match */',
            TernaryExpr::class => '(' . $this->generateExpr($expr->condition) . ' ? ' . $this->generateExpr($expr->thenExpr) . ' : ' . $this->generateExpr($expr->elseExpr) . ')',
            default => '/* expr */',
        };
    }

    private function generateCallExpr(CallExpr $expr): string
    {
        $callee = $expr->callee;
        if ($callee instanceof ExprNode) {
            $callee = $this->generateExpr($callee);
        }
        return $callee . '(' . implode(', ', array_map(fn($a) => $this->generateExpr($a), $expr->arguments)) . ')';
    }

    private function generateRecord($expr): string
    {
        $parts = [];
        foreach ($expr->fields as $f) {
            $parts[] = "'{$f->name}' => {$this->generateExpr($f->value)}";
        }
        return '[' . implode(', ', $parts) . ']';
    }

    private function generateInterpolated($expr): string
    {
        $parts = [];
        foreach ($expr->parts as $p) {
            $parts[] = $p->isExpr ? $this->generateExpr($p->expression) : "'{$p->value}'";
        }
        return implode(' . ', $parts);
    }

    private function generateRoutesFile(): void
    {
        // API routes (always generated, at least for auth)
        $apiCode = "<?php\n\ndeclare(strict_types=1);\n\n";
        $apiCode .= "use Illuminate\\Support\\Facades\\Route;\n\n";

        // Include auth routes
        $apiCode .= "require __DIR__.'/auth.php';\n\n";

        foreach ($this->routes as $route) {
            if ($route['type'] === 'resource') {
                $apiCode .= "Route::resource('{$route['name']}', \\App\\Http\\Controllers\\{$route['controller']}::class);\n";
            } elseif ($route['type'] === 'api') {
                $controller = str_replace('\\', '\\\\', $route['controller']);
                $apiCode .= "Route::{$route['method']}('{$route['path']}', [{$controller}::class, '__invoke']);\n";
            }
        }

        $this->generatedFiles['routes/api.php'] = $apiCode;

        // Web routes
        $webCode = "<?php\n\ndeclare(strict_types=1);\n\n";
        $webCode .= "use Illuminate\\Support\\Facades\\Route;\n\n";

        foreach ($this->routes as $route) {
            if ($route['type'] === 'page') {
                $controller = "\\App\\Http\\Controllers\\{$route['controller']}";
                $webCode .= "Route::{$route['method']}('{$route['path']}', [{$controller}::class, '__invoke']);\n";
            }
        }

        $this->generatedFiles['routes/web.php'] = $webCode;
    }

    private function generateBaseController(): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "namespace App\\Http\\Controllers;\n\n";
        $code .= "abstract class Controller\n";
        $code .= "{\n";
        $code .= "    // Base controller\n";
        $code .= "}\n";

        $this->generatedFiles['app/Http/Controllers/Controller.php'] = $code;
    }

    private function generateFallbackScript(array $statements): void
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";

        foreach ($statements as $stmt) {
            $code .= $this->generateBackendStatement($stmt);
        }

        $this->generatedFiles['app/App.php'] = $code;
    }

    private function formatParams(array $params): string
    {
        $parts = [];
        foreach ($params as $p) {
            $type = $p->typeHint ? "{$p->typeHint} " : '';
            $default = $p->defaultValue ? ' = ' . $this->generateExpr($p->defaultValue) : '';
            $parts[] = "{$type}\${$p->name}{$default}";
        }
        return implode(', ', $parts);
    }

    private function tableName(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
    }

    private function camelCase(string $name): string
    {
        return lcfirst($name);
    }

    private function kebabCase(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    private function pluralize(string $name): string
    {
        return $name . 's';
    }

    private function routeControllerName(string $path): string
    {
        $clean = trim(str_replace(['/', '{', '}', '-'], ' ', $path));
        $parts = array_filter(explode(' ', $clean));
        $parts = array_map(fn($p) => ucfirst($p), $parts);
        return implode('', $parts) ?: 'Index';
    }

    private function relationshipMethod(string $type): string
    {
        return match (strtolower($type)) {
            'hasmany' => 'hasMany',
            'belongsto' => 'belongsTo',
            'hasonly' => 'hasOne',
            'belongstomany' => 'belongsToMany',
            default => 'hasMany',
        };
    }

    private function columnType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'bigint' => 'integer',
            'string', 'varchar', 'text' => 'string',
            'float', 'double', 'decimal' => 'float',
            'bool', 'boolean' => 'boolean',
            'datetime', 'timestamp' => 'timestamp',
            'date' => 'date',
            'json' => 'json',
            'uuid' => 'uuid',
            default => 'string',
        };
    }

    private function columnModifiers(ModelField $field): string
    {
        $mods = [];
        foreach ($field->attributes as $attr) {
            if ($attr === 'unique') $mods[] = '->unique()';
            if ($attr === 'nullable') $mods[] = '->nullable()';
        }
        return implode('', $mods);
    }

    private function castType(string $type): ?string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'float',
            'bool', 'boolean' => 'boolean',
            'array', 'json' => 'array',
            'datetime' => 'datetime',
            default => null,
        };
    }

    private function factoryValue(ModelField $field): string
    {
        return match (strtolower($field->type)) {
            'string', 'varchar', 'text' => "fake()->name()",
            'int', 'integer' => "fake()->numberBetween(1, 1000)",
            'float', 'double' => "fake()->randomFloat(2, 1, 1000)",
            'bool', 'boolean' => "fake()->boolean()",
            'email' => "fake()->unique()->safeEmail()",
            'password' => "bcrypt('password')",
            default => "fake()->word()",
        };
    }

    private function indent(): string
    {
        return str_repeat('    ', $this->indentLevel);
    }
}
