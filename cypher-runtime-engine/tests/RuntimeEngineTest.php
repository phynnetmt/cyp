<?php

namespace Cypher\RuntimeEngine\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\RuntimeEngine\Bytecode\Opcode;
use Cypher\RuntimeEngine\Bytecode\BytecodeProgram;
use Cypher\RuntimeEngine\Bytecode\BytecodeCompiler;
use Cypher\RuntimeEngine\VM\VirtualMachine;
use Cypher\RuntimeEngine\Memory\MemoryManager;
use Cypher\RuntimeEngine\Memory\GarbageCollector;
use Cypher\RuntimeEngine\Concurrency\CoroutineScheduler;
use Cypher\RuntimeEngine\Concurrency\WorkerPool;
use Cypher\RuntimeEngine\Http\HttpRuntime;
use Cypher\RuntimeEngine\Http\HttpResponse;
use Cypher\RuntimeEngine\AiRuntime\AiRuntime;
use Cypher\RuntimeEngine\Sandbox\SecuritySandbox;
use Cypher\RuntimeEngine\Profiler\Profiler;
use Cypher\RuntimeEngine\Profiler\Benchmark;

class RuntimeEngineTest extends TestCase
{
    // === Bytecode System ===
    public function testBytecodeCompilerSay(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [
            ['type' => 'say', 'expression' => ['type' => 'literal', 'value' => 'Hello']],
        ];
        $program = $compiler->compile($ast);
        $this->assertNotEmpty($program->bytecode);
        $this->assertNotEmpty($program->constants);
    }

    public function testBytecodeDisassemble(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [
            ['type' => 'say', 'expression' => ['type' => 'literal', 'value' => 'test']],
        ];
        $program = $compiler->compile($ast);
        $dis = $program->disassemble();
        $this->assertStringContainsString('PUSH_STRING', $dis);
        $this->assertStringContainsString('SAY', $dis);
    }

    public function testBytecodeSerialize(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [['type' => 'say', 'expression' => ['type' => 'literal', 'value' => 'x']]];
        $program = $compiler->compile($ast);
        $arr = $program->toArray();
        $restored = BytecodeProgram::fromArray($arr);
        $this->assertCount(count($program->bytecode), $restored->bytecode);
    }

    // === Virtual Machine ===
    public function testVMSayLiteral(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [['type' => 'say', 'expression' => ['type' => 'literal', 'value' => 'Hello World']]];
        $program = $compiler->compile($ast);

        $vm = new VirtualMachine();
        $vm->load($program);
        $result = $vm->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Hello World', $result->output);
    }

    public function testVMVariableDeclaration(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [
            ['type' => 'var_decl', 'name' => 'x', 'value' => ['type' => 'literal', 'value' => 42]],
            ['type' => 'say', 'expression' => ['type' => 'identifier', 'name' => 'x']],
        ];
        $program = $compiler->compile($ast);

        $vm = new VirtualMachine();
        $vm->load($program);
        $result = $vm->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42', $result->output);
    }

    public function testVMArithmetic(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [
            ['type' => 'var_decl', 'name' => 'r', 'value' => [
                'type' => 'binary', 'operator' => '+',
                'left' => ['type' => 'literal', 'value' => 2],
                'right' => ['type' => 'literal', 'value' => 3],
            ]],
            ['type' => 'say', 'expression' => ['type' => 'identifier', 'name' => 'r']],
        ];
        $program = $compiler->compile($ast);

        $vm = new VirtualMachine();
        $vm->load($program);
        $result = $vm->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('5', $result->output);
    }

    public function testVMIfElse(): void
    {
        $compiler = new BytecodeCompiler();
        $ast = [
            ['type' => 'var_decl', 'name' => 'x', 'value' => ['type' => 'literal', 'value' => true]],
            ['type' => 'if', 'condition' => ['type' => 'identifier', 'name' => 'x'],
             'then_body' => [['type' => 'say', 'expression' => ['type' => 'literal', 'value' => 'true_branch']]],
             'else_body' => [['type' => 'say', 'expression' => ['type' => 'literal', 'value' => 'false_branch']]],
            ],
        ];
        $program = $compiler->compile($ast);

        $vm = new VirtualMachine();
        $vm->load($program);
        $result = $vm->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('true_branch', $result->output);
    }

    // === Memory Manager ===
    public function testMemoryAllocate(): void
    {
        $mem = new MemoryManager();
        $id = $mem->allocate(128);
        $this->assertIsInt($id);
    }

    public function testMemoryWriteRead(): void
    {
        $mem = new MemoryManager();
        $id = $mem->allocate(64);
        $mem->write($id, 'test data');
        $this->assertSame('test data', $mem->read($id));
    }

    public function testMemoryReferenceCounting(): void
    {
        $mem = new MemoryManager();
        $id = $mem->allocate(32);
        $mem->ref($id);
        $mem->ref($id);
        $mem->deref($id);
        $mem->deref($id);
        $mem->deref($id);
        $stats = $mem->getStats();
        $this->assertEquals(0, $stats['live_objects']);
    }

    public function testGarbageCollector(): void
    {
        $mem = new MemoryManager();
        $id = $mem->allocate(128);
        $mem->write($id, 'gc test');
        $mem->deref($id);
        $gc = new GarbageCollector($mem);
        $result = $gc->collect();
        $this->assertGreaterThanOrEqual(0, $result->collected);
    }

    // === Concurrency ===
    public function testCoroutineSpawn(): void
    {
        $scheduler = new CoroutineScheduler();
        $id = $scheduler->spawn(fn() => 'task completed');
        $result = $scheduler->await($id);
        $this->assertSame('task completed', $result);
    }

    public function testWorkerPool(): void
    {
        $scheduler = new CoroutineScheduler();
        $pool = $scheduler->createWorkerPool(4, fn($job) => "processed: {$job}");
        $ids = $pool->submitBatch([1, 2, 3]);
        $this->assertCount(3, $ids);
    }

    // === HTTP Runtime ===
    public function testHttpRouting(): void
    {
        $http = new HttpRuntime();
        $http->get('/hello', fn() => ['message' => 'Hello World']);
        $http->post('/data', fn($body) => ['received' => $body]);

        $response = $http->handle('GET', '/hello');
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Hello World', $response->body);
    }

    public function testHttpRouteParams(): void
    {
        $http = new HttpRuntime();
        $http->get('/users/{id}', fn($params) => ['user_id' => $params['id']]);

        $response = $http->handle('GET', '/users/42');
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('42', $response->body);
    }

    public function testHttp404(): void
    {
        $http = new HttpRuntime();
        $response = $http->handle('GET', '/nonexistent');
        $this->assertEquals(404, $response->statusCode);
    }

    public function testHttpGroup(): void
    {
        $http = new HttpRuntime();
        $http->group(['prefix' => '/api'], function($router) {
            $router->get('/status', fn() => ['status' => 'ok']);
        });
        $response = $http->handle('GET', '/api/status');
        $this->assertEquals(200, $response->statusCode);
    }

    // === AI Runtime ===
    public function testAiAgentCreation(): void
    {
        $ai = new AiRuntime();
        $agent = $ai->createAgent('assistant', 'gpt4');
        $this->assertSame('assistant', $agent['name']);
        $this->assertSame('ready', $agent['status']);
    }

    public function testAiAgentExecution(): void
    {
        $ai = new AiRuntime();
        $agent = $ai->createAgent('worker', 'default');
        $result = $ai->runAgent($agent['id'], 'Process this');
        $this->assertSame('completed', $result['status']);
    }

    public function testAiEmbedding(): void
    {
        $ai = new AiRuntime();
        $emb = $ai->createEmbedding('test text', 64);
        $this->assertArrayHasKey('vector', $emb);
        $this->assertCount(64, $emb['vector']);
    }

    // === Security Sandbox ===
    public function testSandboxPermissions(): void
    {
        $sandbox = new SecuritySandbox();
        $this->assertTrue($sandbox->checkPermission('filesystem_read'));
        $this->assertFalse($sandbox->checkPermission('filesystem_write'));
        $this->assertFalse($sandbox->checkPermission('exec'));
    }

    public function testSandboxAllowDeny(): void
    {
        $sandbox = new SecuritySandbox(['denied' => ['dangerous_op']]);
        $this->assertFalse($sandbox->check('dangerous_op'));
        $this->assertTrue($sandbox->check('safe_op'));
    }

    public function testPackageValidation(): void
    {
        $sandbox = new SecuritySandbox();
        $result = $sandbox->validatePackage('safe-pkg', '1.0.0', 'safe code');
        $this->assertTrue($result->passed);

        $result2 = $sandbox->validatePackage('bad-pkg', '1.0.0', 'exec("rm -rf /")');
        $this->assertFalse($result2->passed);
    }

    // === Profiler ===
    public function testProfilerSampling(): void
    {
        $profiler = new Profiler();
        $profiler->start('test_op');
        usleep(1000);
        $result = $profiler->end('test_op');

        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertGreaterThan(0, $result['duration_ms']);
    }

    public function testProfilerReport(): void
    {
        $profiler = new Profiler();
        $profiler->start('op1');
        $profiler->end('op1');
        $profiler->start('op2');
        $profiler->end('op2');

        $report = $profiler->getReport();
        $this->assertGreaterThan(0, $report->totalTimeMs);
        $this->assertCount(2, $report->hotspots);
    }

    public function testBenchmark(): void
    {
        $bench = new Benchmark();
        $result = $bench->run('test', fn() => 1 + 1, 100);
        $this->assertArrayHasKey('ops_per_sec', $result);
        $this->assertGreaterThan(0, $result['ops_per_sec']);
    }

    public function testBenchmarkComparison(): void
    {
        $bench = new Benchmark();
        $results = $bench->compare([
            'fast' => fn() => 1 + 1,
            'slow' => fn() => usleep(10),
        ]);
        $this->assertArrayHasKey('fast', $results);
        $this->assertArrayHasKey('slow', $results);
    }
}
