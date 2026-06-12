<?php

namespace Cypher\Runtime\Agent;

class AgentRuntime
{
    private array $agents = [];
    private array $agentConfigs = [];
    private array $eventHandlers = [];

    public function __construct(private array $config = []) {}

    public function createAgent(string $name, string $role = 'assistant', array $options = []): Agent
    {
        if (isset($this->agents[$name])) {
            throw new AgentException("Agent '{$name}' already exists");
        }

        $config = array_merge([
            'role' => $role,
            'system_prompt' => $options['system_prompt'] ?? null,
            'memory' => $options['memory'] ?? [],
            'reasoning' => $options['reasoning'] ?? [],
        ], $options);

        $agent = new Agent($name, $config);
        $agent->initialize();

        $this->agents[$name] = $agent;
        $this->agentConfigs[$name] = $config;

        $this->emit('agent.created', ['name' => $name, 'role' => $role]);

        return $agent;
    }

    public function getAgent(string $name): ?Agent
    {
        return $this->agents[$name] ?? null;
    }

    public function removeAgent(string $name): void
    {
        if (isset($this->agents[$name])) {
            $this->agents[$name]->shutdown();
            unset($this->agents[$name]);
            unset($this->agentConfigs[$name]);
            $this->emit('agent.removed', ['name' => $name]);
        }
    }

    public function listAgents(): array
    {
        $result = [];
        foreach ($this->agents as $name => $agent) {
            $result[$name] = [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
                'state' => $agent->getState(),
            ];
        }
        return $result;
    }

    public function runAgent(string $name, string $input, array $context = []): AgentResponse
    {
        $agent = $this->getAgent($name);
        if (!$agent) {
            throw new AgentException("Agent '{$name}' not found");
        }

        $this->emit('agent.before_run', ['name' => $name, 'input' => $input]);
        $response = $agent->run($input, $context);
        $this->emit('agent.after_run', ['name' => $name, 'response' => $response->toArray()]);

        return $response;
    }

    public function getAgentCount(): int
    {
        return count($this->agents);
    }

    public function shutdownAll(): void
    {
        foreach ($this->agents as $name => $agent) {
            $agent->shutdown();
        }
        $this->agents = [];
        $this->agentConfigs = [];
        $this->emit('agents.shutdown', []);
    }

    public function on(string $event, callable $handler): void
    {
        $this->eventHandlers[$event][] = $handler;
    }

    private function emit(string $event, array $data): void
    {
        $handlers = $this->eventHandlers[$event] ?? [];
        foreach ($handlers as $handler) {
            $handler($data);
        }
    }
}
