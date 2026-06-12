<?php

namespace Cypher\RuntimeEngine\Concurrency;

class WorkerPool
{
    private int $size;
    private $worker;
    private CoroutineScheduler $scheduler;
    private array $results = [];
    private array $jobs = [];

    public function __construct(int $size, callable $worker, CoroutineScheduler $scheduler)
    {
        $this->size = $size;
        $this->worker = $worker;
        $this->scheduler = $scheduler;
    }

    public function submit(mixed $job): int
    {
        $id = $this->scheduler->spawn(function() use ($job) {
            return ($this->worker)($job);
        });
        $this->jobs[$id] = $job;
        return $id;
    }

    public function submitBatch(array $jobs): array
    {
        $ids = [];
        foreach ($jobs as $job) {
            $ids[] = $this->submit($job);
        }
        return $ids;
    }

    public function awaitAll(): array
    {
        $this->scheduler->run();
        $results = [];
        foreach ($this->jobs as $id => $job) {
            try {
                $results[$id] = $this->scheduler->await($id);
            } catch (\Throwable $e) {
                $results[$id] = $e;
            }
        }
        return $results;
    }

    public function getStats(): array
    {
        return [
            'size' => $this->size,
            'submitted' => count($this->jobs),
            'completed' => count(array_filter($this->results, fn($r) => !($r instanceof \Throwable))),
        ];
    }
}
