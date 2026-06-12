<?php

namespace Cypher\Runtime\Reasoning;

class DirectReasoning implements ReasoningStrategy
{
    public function reason(string $input, array $context, array $config): ReasoningResult
    {
        $conversation = $context;
        $conversation[] = ['role' => 'user', 'content' => $input];

        $systemPrompt = '';
        foreach ($conversation as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
            }
        }

        $output = $this->generateResponse($input, $systemPrompt);

        return new ReasoningResult(
            output: $output,
            reasoning: 'Direct response',
            confidence: 0.7,
        );
    }

    private function generateResponse(string $input, string $systemPrompt): string
    {
        $input = strtolower($input);

        if (str_contains($input, 'hello') || str_contains($input, 'hi')) {
            return 'Hello! How can I assist you today?';
        }
        if (str_contains($input, 'help')) {
            return 'I can help you with tasks, research, analysis, and more. What would you like to do?';
        }

        return "I understand your request: \"{$input}\". I am processing it now.";
    }
}
