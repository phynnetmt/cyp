<?php

namespace Cypher\Runtime\MultiAgent;

use Cypher\Runtime\Agent\Agent;
use Cypher\Runtime\Agent\AgentRuntime;
use Cypher\Runtime\Memory\MemoryManager;

class MultiAgentSystem
{
    private AgentRuntime $runtime;
    private array $teams = [];
    private array $supervisors = [];
    private MemoryManager $sharedMemory;

    public function __construct(?AgentRuntime $runtime = null)
    {
        $this->runtime = $runtime ?? new AgentRuntime();
        $this->sharedMemory = new MemoryManager(['short_term' => true, 'long_term' => true]);
    }

    public function createTeam(string $name, array $agentNames, ?string $supervisorName = null): AgentTeam
    {
        $agents = [];
        foreach ($agentNames as $agentName) {
            $agent = $this->runtime->getAgent($agentName);
            if ($agent) {
                $agents[$agentName] = $agent;
            }
        }

        $supervisor = $supervisorName ? $this->runtime->getAgent($supervisorName) : null;

        $team = new AgentTeam($name, $agents, $supervisor, $this->sharedMemory);
        $this->teams[$name] = $team;

        if ($supervisor) {
            $this->supervisors[$supervisor->getName()] = $name;
        }

        return $team;
    }

    public function getTeam(string $name): ?AgentTeam
    {
        return $this->teams[$name] ?? null;
    }

    public function delegateTask(string $teamName, string $task, array $context = []): array
    {
        $team = $this->getTeam($teamName);
        if (!$team) {
            throw new MultiAgentException("Team '{$teamName}' not found");
        }

        return $team->executeTask($task, $context);
    }

    public function broadcastMessage(string $message, ?string $teamName = null): array
    {
        $results = [];
        $teams = $teamName ? [$teamName => $this->getTeam($teamName)] : $this->teams;

        foreach ($teams as $name => $team) {
            if ($team) {
                $results[$name] = $team->broadcast($message);
            }
        }

        return $results;
    }

    public function getSharedMemory(): MemoryManager
    {
        return $this->sharedMemory;
    }

    public function getTeams(): array
    {
        return $this->teams;
    }

    public function getRuntime(): AgentRuntime
    {
        return $this->runtime;
    }
}
