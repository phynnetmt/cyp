<?php

namespace Cypher\Runtime\Reasoning;

class ChainOfThoughtReasoning implements ReasoningStrategy
{
    public function reason(string $input, array $context, array $config): ReasoningResult
    {
        $maxSteps = $config['max_steps'] ?? 5;
        $steps = [];
        $confidence = 1.0;
        $toolCalls = [];

        // Step 1: Parse and understand
        $steps[] = [
            'step' => 1,
            'thought' => "Understanding the request: analyzing input for key components",
            'action' => 'analyze',
        ];

        // Step 2: Decompose
        $subProblems = $this->decompose($input);
        $steps[] = [
            'step' => 2,
            'thought' => "Breaking down into sub-problems: " . implode(', ', array_slice($subProblems, 0, 3)),
            'action' => 'decompose',
            'sub_problems' => $subProblems,
        ];
        $confidence *= 0.9;

        // Step 3: Reason through each sub-problem
        $reasoningChain = [];
        foreach (array_slice($subProblems, 0, $maxSteps - 2) as $i => $problem) {
            $solution = $this->solveSubProblem($problem);
            $reasoningChain[] = $solution;
            $steps[] = [
                'step' => 3 + $i,
                'thought' => "Solving: {$problem} -> {$solution}",
                'action' => 'solve',
            ];

            if ($solution['needs_tool']) {
                $toolCalls[] = [
                    'tool' => $solution['tool'] ?? 'default',
                    'arguments' => $solution['args'] ?? [],
                ];
            }
        }

        // Final step: Synthesize
        $output = $this->synthesize($input, $reasoningChain);
        $steps[] = [
            'step' => count($steps) + 1,
            'thought' => "Synthesizing final response from reasoning chain",
            'action' => 'synthesize',
        ];

        $reasoningText = implode("\n", array_map(fn($s) => "[Step {$s['step']}] {$s['thought']}", $steps));

        return new ReasoningResult(
            output: $output,
            reasoning: $reasoningText,
            toolCalls: $toolCalls,
            confidence: $confidence,
            steps: $steps,
        );
    }

    private function decompose(string $input): array
    {
        $input = strtolower($input);
        $problems = [];

        if (str_contains($input, 'search') || str_contains($input, 'find')) {
            $problems[] = 'search for information';
        }
        if (str_contains($input, 'calculate') || str_contains($input, 'compute') || preg_match('/\d+\s*[\+\-\*\/]/', $input)) {
            $problems[] = 'perform calculation';
        }
        if (str_contains($input, 'write') || str_contains($input, 'create') || str_contains($input, 'generate')) {
            $problems[] = 'generate content';
        }
        if (str_contains($input, 'analyze') || str_contains($input, 'review') || str_contains($input, 'evaluate')) {
            $problems[] = 'analyze and evaluate';
        }
        if (str_contains($input, 'compare') || str_contains($input, 'contrast')) {
            $problems[] = 'comparison analysis';
        }
        if (str_contains($input, 'summarize') || str_contains($input, 'summarise')) {
            $problems[] = 'summarization';
        }

        if (empty($problems)) {
            $problems[] = 'understand and respond to query';
        }

        return $problems;
    }

    private function solveSubProblem(string $problem): array
    {
        $solutions = [
            'search for information' => [
                'result' => 'Gathered relevant information from available sources',
                'needs_tool' => true,
                'tool' => 'search',
                'args' => ['query' => $problem],
            ],
            'perform calculation' => [
                'result' => 'Computed the mathematical result',
                'needs_tool' => true,
                'tool' => 'calculator',
                'args' => ['expression' => $problem],
            ],
            'generate content' => [
                'result' => 'Drafted content based on requirements',
                'needs_tool' => false,
            ],
            'analyze and evaluate' => [
                'result' => 'Completed analysis with findings',
                'needs_tool' => false,
            ],
        ];

        return $solutions[$problem] ?? [
            'result' => "Processed sub-problem: {$problem}",
            'needs_tool' => false,
        ];
    }

    private function synthesize(string $input, array $chain): string
    {
        if (empty($chain)) {
            return "Based on my analysis, here is my response to: {$input}";
        }

        $results = array_map(fn($c) => $c['result'], $chain);

        return implode(' ', $results);
    }
}
