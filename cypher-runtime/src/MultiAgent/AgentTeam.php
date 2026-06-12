<?php

namespace Cypher\Runtime\MultiAgent;

use Cypher\Runtime\Agent\Agent;
use Cypher\Runtime\Memory\MemoryManager;

class AgentTeam
{
    private string $name;
    private array $agents;
    private ?Agent $supervisor;
    private MemoryManager $sharedMemory;

    public function __construct(string $name, array $agents, ?Agent $supervisor, MemoryManager $sharedMemory)
    {
        $this->name = $name;
        $this->agents = $agents;
        $this->supervisor = $supervisor;
        $this->sharedMemory = $sharedMemory;
    }

    public function executeTask(string $task, array $context = []): array
    {
        $results = [];

        if ($this->supervisor) {
            // Supervisor plans task delegation
            $plan = $this->supervisor->run("Plan the execution of: {$task}. Available agents: " . implode(', ', array_keys($this->agents)));
            $results['plan'] = $plan->output;

            // Store plan in shared memory
            $this->sharedMemory->store([
                'type' => 'task_plan',
                'task' => $task,
                'plan' => $plan->output,
                'timestamp' => time(),
            ]);
        }

        // Execute with agents
        $agentResults = [];
        foreach ($this->agents as $name => $agent) {
            $sharedContext = $this->getSharedContext();
            $response = $agent->run($task, $sharedContext);
            $agentResults[$name] = $response->toArray();

            $this->sharedMemory->store([
                'type' => 'agent_response',
                'agent' => $name,
                'task' => $task,
                'response' => $response->output,
                'timestamp' => time(),
            ]);
        }

        $results['agent_responses'] = $agentResults;

        if ($this->supervisor) {
            $consensus = $this->supervisor->run("Review agent responses and provide a consensus for: {$task}");
            $results['consensus'] = $consensus->output;
        }

        return $results;
    }

    public function broadcast(string $message): array
    {
        $results = [];
        foreach ($this->agents as $name => $agent) {
            $response = $agent->run("[BROADCAST] {$message}");
            $results[$name] = $response->output;
        }
        return $results;
    }

    public function addAgent(Agent $agent): void
    {
        $this->agents[$agent->getName()] = $agent;
    }

    public function removeAgent(string $name): void
    {
        unset($this->agents[$name]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAgents(): array
    {
        return $this->agents;
    }

    public function getSupervisor(): ?Agent
    {
        return $this->supervisor;
    }

    private function getSharedContext(): array
    {
        $recentMemory = $this->sharedMemory->search('team task collaboration', 10);
        return [
            'team' => $this->name,
            'shared_memory' => $recentMemory,
            'agent_count' => count($this->agents),
        ];
    }
}
