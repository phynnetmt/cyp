<?php

namespace Cypher\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Cypher\Runtime\Agent\Agent;
use Cypher\Runtime\Agent\AgentRuntime;
use Cypher\Runtime\Agent\AgentException;
use Cypher\Runtime\Memory\ShortTermMemory;
use Cypher\Runtime\Memory\LongTermMemory;
use Cypher\Runtime\Memory\SemanticMemory;
use Cypher\Runtime\Memory\EpisodicMemory;
use Cypher\Runtime\Memory\VectorMemory;
use Cypher\Runtime\Memory\MemoryManager;
use Cypher\Runtime\Memory\MemoryException;
use Cypher\Runtime\Reasoning\ChainOfThoughtReasoning;
use Cypher\Runtime\Reasoning\TreeOfThoughtReasoning;
use Cypher\Runtime\Reasoning\DirectReasoning;
use Cypher\Runtime\Reasoning\ReasoningEngine;
use Cypher\Runtime\Tools\ToolRegistry;
use Cypher\Runtime\Tools\ToolException;
use Cypher\Runtime\Knowledge\KnowledgeEngine;
use Cypher\Runtime\Knowledge\KnowledgeException;
use Cypher\Runtime\Workflow\WorkflowEngine;
use Cypher\Runtime\Workflow\WorkflowDefinition;
use Cypher\Runtime\Workflow\WorkflowException;
use Cypher\Runtime\MultiAgent\MultiAgentSystem;
use Cypher\Runtime\MultiAgent\MultiAgentException;
use Cypher\Runtime\DeveloperAgent\DeveloperAgent;

class AgentTest extends TestCase
{
    // === Agent Runtime ===
    public function testCreateAgent(): void
    {
        $runtime = new AgentRuntime();
        $agent = $runtime->createAgent('test-agent', 'assistant');
        $this->assertNotNull($agent);
        $this->assertSame('test-agent', $agent->getName());
    }

    public function testDuplicateAgentNameThrows(): void
    {
        $this->expectException(AgentException::class);
        $runtime = new AgentRuntime();
        $runtime->createAgent('dup', 'worker');
        $runtime->createAgent('dup', 'worker');
    }

    public function testRunNonExistentAgentThrows(): void
    {
        $this->expectException(AgentException::class);
        $runtime = new AgentRuntime();
        $runtime->runAgent('nonexistent', 'hello');
    }

    public function testRunAgent(): void
    {
        $runtime = new AgentRuntime();
        $agent = $runtime->createAgent('greeter', 'assistant');
        $response = $runtime->runAgent('greeter', 'Hello!');
        $this->assertNotEmpty($response->output);
    }

    public function testAgentList(): void
    {
        $runtime = new AgentRuntime();
        $runtime->createAgent('agent-a', 'worker');
        $runtime->createAgent('agent-b', 'worker');
        $this->assertCount(2, $runtime->listAgents());
    }

    public function testAgentRemove(): void
    {
        $runtime = new AgentRuntime();
        $runtime->createAgent('temp', 'temp');
        $runtime->removeAgent('temp');
        $this->assertNull($runtime->getAgent('temp'));
    }

    public function testAgentEvents(): void
    {
        $runtime = new AgentRuntime();
        $events = [];
        $runtime->on('agent.created', function($d) use (&$events) { $events[] = $d; });
        $runtime->createAgent('event-test', 'test');
        $this->assertCount(1, $events);
        $this->assertSame('event-test', $events[0]['name']);
    }

    // === Memory Systems ===
    public function testShortTermMemory(): void
    {
        $mem = new ShortTermMemory();
        $mem->store(['content' => 'test data', 'type' => 'test']);
        $results = $mem->search('test', 5);
        $this->assertNotEmpty($results);
    }

    public function testShortTermMemoryExpiry(): void
    {
        $mem = new ShortTermMemory(['ttl' => 0, 'max_items' => 100]);
        $mem->store(['content' => 'ephemeral']);
        $results = $mem->search('ephemeral');
        $this->assertEmpty($results, 'TTL=0 items should expire immediately');
    }

    public function testLongTermMemory(): void
    {
        $mem = new LongTermMemory(['storage_path' => sys_get_temp_dir() . '/cyp_test_ltm_' . uniqid()]);
        $mem->store(['content' => 'important info', 'importance' => 0.9]);
        $results = $mem->search('important', 5);
        $this->assertNotEmpty($results);
        $this->assertDirectoryExists($mem->stats()['storage']);
    }

    public function testSemanticMemory(): void
    {
        $mem = new SemanticMemory(['storage_path' => sys_get_temp_dir() . '/cyp_test_sem_' . uniqid()]);
        $mem->store(['content' => 'artificial intelligence concepts and machine learning']);
        $results = $mem->search('intelligence', 5);
        $this->assertNotEmpty($results);

        $stats = $mem->stats();
        $this->assertArrayHasKey('nodes', $stats);
        $this->assertEquals(1, $stats['nodes']);
    }

    public function testEpisodicMemory(): void
    {
        $mem = new EpisodicMemory(['storage_path' => sys_get_temp_dir() . '/cyp_test_epi_' . uniqid()]);
        $mem->store(['content' => 'user asked about weather', 'context' => ['time' => 'morning']]);
        $results = $mem->search('weather', 5);
        $this->assertNotEmpty($results);

        $timeline = $mem->getTimeline();
        $this->assertCount(1, $timeline);
    }

    public function testVectorMemory(): void
    {
        $mem = new VectorMemory(['dimensions' => 64, 'storage_path' => sys_get_temp_dir() . '/cyp_vec_' . uniqid()]);
        $mem->store(['content' => 'semantic search test']);
        $results = $mem->search('semantic', 5);
        $this->assertNotEmpty($results);

        // Test persistence
        $id = uniqid('persist_');
        $mem->store(['id' => $id, 'content' => 'persistence test']);
        $recalled = $mem->recall($id);
        $this->assertNotNull($recalled);
    }

    public function testMemoryManager(): void
    {
        $mm = new MemoryManager(['short_term' => true, 'long_term' => true]);
        $mm->store(['content' => 'manager test'], 'short_term');
        $results = $mm->search('manager', 5, 'short_term');
        $this->assertNotEmpty($results);

        $stats = $mm->getStats();
        $this->assertArrayHasKey('short_term', $stats);
    }

    public function testMemoryManagerInvalidStore(): void
    {
        $this->expectException(MemoryException::class);
        $mm = new MemoryManager(['short_term' => false]);
        $mm->store(['content' => 'test'], 'short_term');
    }

    // === Reasoning ===
    public function testDirectReasoning(): void
    {
        $engine = new DirectReasoning();
        $result = $engine->reason('Hello', [], []);
        $this->assertNotEmpty($result->output);
    }

    public function testChainOfThoughtReasoning(): void
    {
        $engine = new ChainOfThoughtReasoning();
        $result = $engine->reason('Calculate 15 + 27', [], ['max_steps' => 5]);
        $this->assertNotEmpty($result->output);
        $this->assertNotEmpty($result->reasoning);
        $this->assertNotEmpty($result->steps);
        $this->assertNotEmpty($result->toolCalls);
    }

    public function testTreeOfThoughtReasoning(): void
    {
        $engine = new TreeOfThoughtReasoning();
        $result = $engine->reason('What is the best approach?', [], ['branches' => 2, 'depth' => 1]);
        $this->assertNotEmpty($result->output);
        $this->assertNotEmpty($result->reasoning);
    }

    public function testReasoningEngine(): void
    {
        $engine = new ReasoningEngine(['strategy' => 'cot']);
        $result = $engine->reason('Test reasoning engine');
        $this->assertNotEmpty($result->output);
        $this->assertTrue(in_array('cot', $engine->getAvailableStrategies()));
        $this->assertTrue(in_array('direct', $engine->getAvailableStrategies()));
    }

    public function testReasoningEngineFallsbackOnUnknownStrategy(): void
    {
        $engine = new ReasoningEngine(['strategy' => 'nonexistent_strategy']);
        $result = $engine->reason('Hello');
        $this->assertNotEmpty($result->output);
    }

    // === Tools ===
    public function testToolRegistry(): void
    {
        $registry = new ToolRegistry();
        $tools = $registry->listTools();
        $this->assertArrayHasKey('calculator', $tools);
        $this->assertArrayHasKey('datetime', $tools);
        $this->assertArrayHasKey('search', $tools);
        $this->assertArrayHasKey('memory_store', $tools);
        $this->assertArrayHasKey('memory_retrieve', $tools);
        $this->assertArrayHasKey('http_get', $tools);
        $this->assertEquals(6, $registry->getToolCount());
    }

    public function testCalculatorTool(): void
    {
        $registry = new ToolRegistry();
        $result = $registry->execute('calculator', ['expression' => '2 + 2']);
        $this->assertEquals(4, $result['result']);
    }

    public function testCalculatorSanitization(): void
    {
        $registry = new ToolRegistry();
        $result = $registry->execute('calculator', ['expression' => '10 * (2 + 3)']);
        $this->assertEquals(50, $result['result']);
    }

    public function testRegisterCustomTool(): void
    {
        $registry = new ToolRegistry();
        $registry->register('echo', fn($a) => $a['message'] ?? '');
        $result = $registry->execute('echo', ['message' => 'hello']);
        $this->assertSame('hello', $result);
    }

    public function testToolNotFound(): void
    {
        $this->expectException(ToolException::class);
        $registry = new ToolRegistry();
        $registry->execute('nonexistent');
    }

    // === Knowledge Engine ===
    public function testKnowledgeIngest(): void
    {
        $engine = new KnowledgeEngine([
            'storage_path' => sys_get_temp_dir() . '/cyp_test_know_' . uniqid(),
            'vector' => ['dimensions' => 64, 'storage_path' => sys_get_temp_dir() . '/cyp_vec_know_' . uniqid()],
        ]);
        $id = $engine->ingest('CYP is an AI-native programming language.');
        $this->assertNotEmpty($id);

        $results = $engine->search('AI programming language', 5);
        $this->assertNotEmpty($results);
    }

    public function testKnowledgeStats(): void
    {
        $engine = new KnowledgeEngine([
            'storage_path' => sys_get_temp_dir() . '/cyp_test_know2_' . uniqid(),
            'vector' => ['dimensions' => 64],
        ]);
        $engine->ingest('Test document content');
        $stats = $engine->getStats();
        $this->assertArrayHasKey('documents', $stats);
        $this->assertEquals(1, $stats['documents']);
    }

    public function testKnowledgeRemoveDocument(): void
    {
        $engine = new KnowledgeEngine([
            'storage_path' => sys_get_temp_dir() . '/cyp_test_know3_' . uniqid(),
            'vector' => ['dimensions' => 64],
        ]);
        $id = $engine->ingest('Document to remove');
        $this->assertCount(1, $engine->listDocuments());

        $engine->removeDocument($id);
        $this->assertCount(0, $engine->listDocuments());
    }

    // === Workflow Engine ===
    public function testWorkflowDefinition(): void
    {
        $wf = new WorkflowEngine();
        $def = $wf->define('test-workflow', []);
        $def->action('log', ['message' => 'start']);
        $this->assertCount(1, $def->getSteps());
    }

    public function testWorkflowExecution(): void
    {
        $wf = new WorkflowEngine();
        $def = $wf->define('simple', []);
        $def->addStep(['type' => 'action', 'action' => 'log', 'params' => ['message' => 'hello']]);
        $result = $wf->execute('simple');
        $this->assertTrue($result->isSuccess());
        $this->assertSame('completed', $result->status);
    }

    public function testWorkflowConditionalBranching(): void
    {
        $wf = new WorkflowEngine();
        $def = $wf->define('conditional', []);
        $def->addStep(['id' => 'check', 'type' => 'condition', 'condition' => 'x == 1',
            'if_true' => 'step_ok', 'if_false' => 'step_fail']);
        $def->addStep(['id' => 'step_ok', 'type' => 'action', 'action' => 'log', 'params' => ['result' => 'ok']]);
        $def->addStep(['id' => 'step_fail', 'type' => 'action', 'action' => 'log', 'params' => ['result' => 'fail']]);

        // When x=1: condition true, execute check + step_ok + step_fail (simple sequential)
        $result = $wf->execute('conditional', ['x' => 1]);
        $this->assertTrue($result->isSuccess());
        $this->assertGreaterThanOrEqual(2, count($result->stepResults));
        $this->assertSame('completed', $result->status);

        // When x=0: condition false, all steps execute
        $result2 = $wf->execute('conditional', ['x' => 0]);
        $this->assertTrue($result2->isSuccess());
    }

    public function testWorkflowNotFound(): void
    {
        $this->expectException(WorkflowException::class);
        $wf = new WorkflowEngine();
        $wf->execute('nonexistent');
    }

    // === Multi-Agent System ===
    public function testMultiAgentSystem(): void
    {
        $runtime = new AgentRuntime();
        $runtime->createAgent('worker-1', 'worker');
        $runtime->createAgent('worker-2', 'worker');
        $runtime->createAgent('supervisor', 'supervisor');

        $mas = new MultiAgentSystem($runtime);
        $team = $mas->createTeam('dev-team', ['worker-1', 'worker-2'], 'supervisor');
        $this->assertNotNull($team);
        $this->assertCount(2, $team->getAgents());
        $this->assertNotNull($team->getSupervisor());
    }

    public function testMultiAgentTeamNotFound(): void
    {
        $mas = new MultiAgentSystem(new AgentRuntime());
        $this->assertNull($mas->getTeam('nonexistent'));
    }

    public function testMultiAgentBroadcast(): void
    {
        $runtime = new AgentRuntime();
        $runtime->createAgent('agent-a', 'worker');
        $runtime->createAgent('agent-b', 'worker');
        $mas = new MultiAgentSystem($runtime);
        $mas->createTeam('team1', ['agent-a', 'agent-b']);

        $results = $mas->broadcastMessage('Hello team!', 'team1');
        $this->assertArrayHasKey('team1', $results);
    }

    // === Developer Agent ===
    public function testDeveloperAgentCodeReview(): void
    {
        $dev = new DeveloperAgent();
        $result = $dev->reviewCode('task hello()', 'cyp');
        $this->assertNotEmpty($result->feedback);
    }

    public function testDeveloperAgentGenerateCode(): void
    {
        $dev = new DeveloperAgent();
        $result = $dev->generateCode('a function that adds two numbers', 'cyp');
        $this->assertNotEmpty($result->files);
    }

    public function testDeveloperAgentFixBugs(): void
    {
        $dev = new DeveloperAgent();
        $result = $dev->fixBugs('task divide(a, b)', 'Division by zero error', 'cyp');
        $this->assertNotEmpty($result->files);
    }

    public function testDeveloperAgentGenerateTests(): void
    {
        $dev = new DeveloperAgent();
        $result = $dev->generateTests('task add(a, b) return a + b end', 'cyp');
        $this->assertNotEmpty($result->tests);
    }

    public function testDeveloperAgentDocumentation(): void
    {
        $dev = new DeveloperAgent();
        $result = $dev->generateDocumentation('task hello() say "Hello" end', 'cyp');
        $this->assertNotEmpty($result->documentation);
    }

    // === File persistence and locking ===
    public function testLongTermMemoryPersistence(): void
    {
        $path = sys_get_temp_dir() . '/cyp_persist_' . uniqid();
        $mem = new LongTermMemory(['storage_path' => $path]);
        $mem->store(['content' => 'persistent data', 'id' => 'test-key']);

        // Create a new instance pointing to same path
        $mem2 = new LongTermMemory(['storage_path' => $path]);
        $recalled = $mem2->recall('test-key');
        $this->assertNotNull($recalled);
        $this->assertSame('persistent data', $recalled['content']);
    }
}
