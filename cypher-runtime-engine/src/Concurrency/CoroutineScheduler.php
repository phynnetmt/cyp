<?php

namespace Cypher\RuntimeEngine\Concurrency;

class CoroutineScheduler
{
    private array $tasks = [];
    private int $nextId = 0;
    private int $currentTask = 0;

    public function spawn(callable $fn): int
    {
        $id = $this->nextId++;
        $this->tasks[$id] = [
            'id' => $id,
            'fn' => $fn,
            'status' => 'ready',
            'result' => null,
        ];
        return $id;
    }

    public function run(): void
    {
        while (!empty($this->tasks)) {
            foreach ($this->tasks as $id => $task) {
                if ($task['status'] === 'ready') {
                    $this->currentTask = $id;
                    try {
                        $result = ($task['fn'])();
                        $this->tasks[$id]['result'] = $result;
                        $this->tasks[$id]['status'] = 'completed';
                    } catch (\Throwable $e) {
                        $this->tasks[$id]['result'] = $e;
                        $this->tasks[$id]['status'] = 'failed';
                    }
                }
            }

            $this->cleanup();
        }
    }

    public function await(int $taskId): mixed
    {
        if (!isset($this->tasks[$taskId])) {
            throw new ConcurrencyException("Task not found: {$taskId}");
        }

        while ($this->tasks[$taskId]['status'] === 'ready') {
            $this->tick();
        }

        if ($this->tasks[$taskId]['status'] === 'failed') {
            throw new ConcurrencyException("Task {$taskId} failed");
        }

        return $this->tasks[$taskId]['result'];
    }

    public function tick(): void
    {
        foreach ($this->tasks as $id => $task) {
            if ($task['status'] === 'ready') {
                try {
                    $result = ($task['fn'])();
                    $this->tasks[$id]['result'] = $result;
                    $this->tasks[$id]['status'] = 'completed';
                } catch (\Throwable $e) {
                    $this->tasks[$id]['result'] = $e;
                    $this->tasks[$id]['status'] = 'failed';
                }
                return;
            }
        }
        $this->cleanup();
    }

    public function getStats(): array
    {
        $statuses = array_column($this->tasks, 'status');
        return [
            'total' => count($this->tasks),
            'completed' => count(array_filter($statuses, fn($s) => $s === 'completed')),
            'failed' => count(array_filter($statuses, fn($s) => $s === 'failed')),
            'ready' => count(array_filter($statuses, fn($s) => $s === 'ready')),
        ];
    }

    public function createWorkerPool(int $size, callable $worker): WorkerPool
    {
        return new WorkerPool($size, $worker, $this);
    }

    private function cleanup(): void
    {
        $this->tasks = array_filter($this->tasks, fn($t) => $t['status'] === 'ready');
    }
}
