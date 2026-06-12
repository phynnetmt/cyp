<?php

namespace Cypher\RuntimeEngine\Profiler;

class Profiler
{
    private array $samples = [];
    private array $counters = [];
    private float $startTime;
    private ?float $pauseTime = null;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function start(string $label): void
    {
        $this->samples[$label] = ['start' => microtime(true), 'memory' => memory_get_usage()];
    }

    public function end(string $label): array
    {
        $sample = $this->samples[$label] ?? null;
        if (!$sample) return ['error' => "No sample started for: {$label}"];

        $duration = (microtime(true) - $sample['start']) * 1000;
        $memoryDelta = memory_get_usage() - $sample['memory'];

        $result = [
            'label' => $label,
            'duration_ms' => round($duration, 3),
            'memory_delta' => $memoryDelta,
        ];

        $this->counters[$label] = [
            'duration_ms' => ($this->counters[$label]['duration_ms'] ?? 0) + $duration,
            'calls' => ($this->counters[$label]['calls'] ?? 0) + 1,
            'max_duration' => max($this->counters[$label]['max_duration'] ?? 0, $duration),
            'total_memory' => ($this->counters[$label]['total_memory'] ?? 0) + $memoryDelta,
        ];

        return $result;
    }

    public function pause(): void
    {
        $this->pauseTime = microtime(true);
    }

    public function resume(): void
    {
        if ($this->pauseTime !== null) {
            $paused = microtime(true) - $this->pauseTime;
            $this->startTime += $paused;
            $this->pauseTime = null;
        }
    }

    public function getReport(): ProfilerReport
    {
        $totalTime = (microtime(true) - $this->startTime) * 1000;

        $sortedCounters = $this->counters;
        uasort($sortedCounters, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);

        $hotspots = [];
        foreach ($sortedCounters as $label => $data) {
            $hotspots[] = [
                'label' => $label,
                'total_ms' => round($data['duration_ms'], 2),
                'calls' => $data['calls'],
                'avg_ms' => round($data['duration_ms'] / max(1, $data['calls']), 3),
                'max_ms' => round($data['max_duration'], 3),
                'pct' => $totalTime > 0 ? round(($data['duration_ms'] / $totalTime) * 100, 1) : 0,
            ];
        }

        return new ProfilerReport(
            totalTimeMs: round($totalTime, 2),
            hotspots: $hotspots,
            sampleCount: count($this->samples),
        );
    }

    public function reset(): void
    {
        $this->samples = [];
        $this->counters = [];
        $this->startTime = microtime(true);
    }
}
