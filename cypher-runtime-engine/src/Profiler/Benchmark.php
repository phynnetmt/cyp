<?php

namespace Cypher\RuntimeEngine\Profiler;

class Benchmark
{
    private array $results = [];

    public function run(string $name, callable $fn, int $iterations = 1000): array
    {
        $times = [];
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $t0 = microtime(true);
            $fn();
            $times[] = (microtime(true) - $t0) * 1000;
        }

        $endMemory = memory_get_usage();
        sort($times);

        $total = array_sum($times);
        $avg = $total / $iterations;
        $min = $times[0];
        $max = $times[$iterations - 1];
        $median = $times[(int)($iterations / 2)];
        $p95 = $times[(int)($iterations * 0.95)];
        $p99 = $times[(int)($iterations * 0.99)];

        $result = [
            'name' => $name,
            'iterations' => $iterations,
            'total_ms' => round($total, 3),
            'avg_ms' => round($avg, 3),
            'min_ms' => round($min, 3),
            'max_ms' => round($max, 3),
            'median_ms' => round($median, 3),
            'p95_ms' => round($p95, 3),
            'p99_ms' => round($p99, 3),
            'memory_bytes' => $endMemory - $startMemory,
            'ops_per_sec' => $total > 0 ? round($iterations / ($total / 1000), 2) : 0,
        ];

        $this->results[$name] = $result;
        return $result;
    }

    public function compare(array $benchmarks): array
    {
        $results = [];
        foreach ($benchmarks as $name => $fn) {
            $results[$name] = $this->run($name, $fn);
        }

        if (count($results) >= 2) {
            $names = array_keys($results);
            $baseline = $results[$names[0]]['avg_ms'];
            foreach ($results as $name => &$data) {
                $data['vs_baseline'] = $baseline > 0
                    ? round(($data['avg_ms'] / $baseline) * 100, 1) . '%'
                    : 'N/A';
            }
        }

        return $results;
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
