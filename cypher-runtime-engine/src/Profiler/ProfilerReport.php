<?php

namespace Cypher\RuntimeEngine\Profiler;

class ProfilerReport
{
    public function __construct(
        public readonly float $totalTimeMs,
        public readonly array $hotspots = [],
        public readonly int $sampleCount = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'total_time_ms' => $this->totalTimeMs,
            'hotspots' => $this->hotspots,
            'sample_count' => $this->sampleCount,
        ];
    }

    public function toString(): string
    {
        $output = "=== Profiler Report ===\n";
        $output .= "Total Time: {$this->totalTimeMs}ms\n";
        $output .= "Samples: {$this->sampleCount}\n\n";
        $output .= "Hotspots:\n";
        $output .= str_pad("Function", 30) . str_pad("Total", 12) . str_pad("Calls", 8) . str_pad("Avg", 12) . str_pad("Max", 12) . "%\n";
        $output .= str_repeat("-", 80) . "\n";

        foreach ($this->hotspots as $h) {
            $output .= str_pad($h['label'], 30) . str_pad("{$h['total_ms']}ms", 12) .
                       str_pad((string)$h['calls'], 8) . str_pad("{$h['avg_ms']}ms", 12) .
                       str_pad("{$h['max_ms']}ms", 12) . "{$h['pct']}%\n";
        }

        return $output;
    }
}
