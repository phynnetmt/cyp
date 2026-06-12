<?php

namespace Cypher\Runtime\Reasoning;

class TreeOfThoughtReasoning implements ReasoningStrategy
{
    public function reason(string $input, array $context, array $config): ReasoningResult
    {
        $branches = $config['branches'] ?? 3;
        $depth = $config['depth'] ?? 2;
        $steps = [];
        $allToolCalls = [];

        // Generate multiple thought branches
        $thoughts = $this->generateThoughts($input, $branches);
        $steps[] = [
            'step' => 1,
            'thought' => "Generated {$branches} initial thought branches",
            'branches' => $thoughts,
        ];

        // Evaluate each branch
        $evaluations = [];
        foreach ($thoughts as $i => $thought) {
            $score = $this->evaluateThought($thought, $input);
            $evaluations[] = ['thought' => $thought, 'score' => $score];
            $steps[] = [
                'step' => 2 + $i,
                'thought' => "Branch {$i}: '{$thought}' -> score: {$score}",
            ];
        }

        // Select best branches and expand
        usort($evaluations, fn($a, $b) => $b['score'] <=> $a['score']);
        $bestThoughts = array_slice($evaluations, 0, max(1, (int)($branches / 2)));

        $expanded = [];
        foreach ($bestThoughts as $eval) {
            $children = $this->expandThought($eval['thought'], $depth);
            $expanded = array_merge($expanded, $children);
            $steps[] = [
                'step' => count($steps) + 1,
                'thought' => "Expanded '{$eval['thought']}' into " . count($children) . " sub-thoughts",
            ];
        }

        // Synthesize final answer from best path
        $bestPath = $bestThoughts[0]['thought'] ?? $thoughts[0];
        $output = $this->synthesizeFromPath($input, $bestPath, $expanded);

        $reasoningText = implode("\n", array_map(fn($s) => "[Step {$s['step']}] {$s['thought']}", $steps));

        return new ReasoningResult(
            output: $output,
            reasoning: $reasoningText,
            toolCalls: $allToolCalls,
            confidence: $bestThoughts[0]['score'] ?? 0.5,
            steps: $steps,
        );
    }

    private function generateThoughts(string $input, int $count): array
    {
        $templates = [
            "Consider the approach of breaking this down into smaller parts",
            "Think about this from first principles",
            "Consider alternative perspectives on this problem",
            "Think about what similar problems have taught us",
            "Consider the opposite approach to gain insight",
        ];

        $thoughts = [];
        for ($i = 0; $i < $count; $i++) {
            $template = $templates[$i % count($templates)];
            $thoughts[] = "{$template}: {$input}";
        }

        return $thoughts;
    }

    private function evaluateThought(string $thought, string $input): float
    {
        $relevance = 0.5;
        $specificity = 0.3;
        $actionability = 0.2;

        $inputWords = str_word_count(strtolower($input), 1);
        $thoughtWords = str_word_count(strtolower($thought), 1);
        $overlap = array_intersect($inputWords, $thoughtWords);

        $relevanceScore = count($overlap) / max(count($inputWords), 1);
        $specificityScore = min(1, count($thoughtWords) / 20);
        $actionabilityScore = (str_contains($thought, 'break') || str_contains($thought, 'approach') || str_contains($thought, 'first principles')) ? 0.8 : 0.5;

        return $relevance * $relevanceScore + $specificity * $specificityScore + $actionability * $actionabilityScore;
    }

    private function expandThought(string $thought, int $depth): array
    {
        $expansions = [];
        for ($i = 0; $i < $depth; $i++) {
            $expansions[] = "{$thought} -> sub-question {$i}: What specific aspect should we examine?";
        }
        return $expansions;
    }

    private function synthesizeFromPath(string $input, string $bestPath, array $expanded): string
    {
        return "Through tree-of-thought reasoning, exploring '{$bestPath}' and its " . count($expanded) . " sub-paths, I conclude: " .
               "The optimal approach involves " . (str_contains($bestPath, 'break') ? 'systematic decomposition followed by targeted analysis.' : 'holistic evaluation and synthesis of multiple perspectives.');
    }
}
