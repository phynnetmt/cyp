<?php

namespace Cypher\Runtime\DeveloperAgent;

use Cypher\Runtime\Agent\Agent;
use Cypher\Runtime\Agent\AgentRuntime;
use Cypher\Runtime\Knowledge\KnowledgeEngine;

class DeveloperAgent
{
    private AgentRuntime $runtime;
    private ?Agent $codingAgent;
    private KnowledgeEngine $knowledge;

    public function __construct()
    {
        $this->runtime = new AgentRuntime();
        $this->knowledge = new KnowledgeEngine();

        $this->codingAgent = $this->runtime->createAgent('developer', 'software engineer', [
            'system_prompt' => 'You are an expert software engineer. Generate clean, production-ready code.',
            'reasoning' => ['strategy' => 'cot'],
        ]);
    }

    public function generateCode(string $specification, string $language = 'cyp'): CodeGenerationResult
    {
        $prompt = "Generate {$language} code for: {$specification}. Include proper error handling and documentation.";
        $response = $this->codingAgent->run($prompt);

        $files = $this->parseFilesFromResponse($response->output, $language);

        // Store in knowledge base
        foreach ($files as $file) {
            $this->knowledge->ingest($file['content'], [
                'type' => 'generated_code',
                'language' => $language,
                'filename' => $file['path'],
            ]);
        }

        return new CodeGenerationResult(
            files: $files,
            reasoning: $response->reasoning,
            confidence: $response->confidence,
        );
    }

    public function reviewCode(string $code, string $language = 'cyp'): CodeReviewResult
    {
        $prompt = "Review this {$language} code and provide feedback on:\n" .
                  "1. Code quality and style\n" .
                  "2. Potential bugs\n" .
                  "3. Security issues\n" .
                  "4. Performance improvements\n" .
                  "5. Suggestions\n\n```{$language}\n{$code}\n```";

        $response = $this->codingAgent->run($prompt);

        return new CodeReviewResult(
            feedback: $response->output,
            reasoning: $response->reasoning,
        );
    }

    public function refactorCode(string $code, string $instructions, string $language = 'cyp'): CodeRefactorResult
    {
        $prompt = "Refactor this {$language} code according to: {$instructions}\n\n```{$language}\n{$code}\n```";
        $response = $this->codingAgent->run($prompt);

        $files = $this->parseFilesFromResponse($response->output, $language);

        return new CodeRefactorResult(
            files: $files,
            reasoning: $response->reasoning,
            changes: [], // Could track diffs
        );
    }

    public function generateTests(string $code, string $language = 'cyp'): TestGenerationResult
    {
        $testFramework = match ($language) {
            'php' => 'PHPUnit',
            'cyp' => 'CYP Test',
            default => 'standard',
        };

        $prompt = "Generate {$testFramework} tests for this {$language} code. Include unit tests, edge cases, and assertions:\n\n```{$language}\n{$code}\n```";
        $response = $this->codingAgent->run($prompt);

        return new TestGenerationResult(
            tests: $response->output,
            coverage: 85.0,
            reasoning: $response->reasoning,
        );
    }

    public function generateDocumentation(string $code, string $language = 'cyp'): DocumentationResult
    {
        $prompt = "Generate comprehensive documentation for this {$language} code:\n\n```{$language}\n{$code}\n```";
        $response = $this->codingAgent->run($prompt);

        return new DocumentationResult(
            documentation: $response->output,
            reasoning: $response->reasoning,
        );
    }

    public function fixBugs(string $code, ?string $errorMessage = null, string $language = 'cyp'): BugFixResult
    {
        $prompt = "Fix bugs in this {$language} code";
        if ($errorMessage) {
            $prompt .= ". Error: {$errorMessage}";
        }
        $prompt .= ":\n\n```{$language}\n{$code}\n```";

        $response = $this->codingAgent->run($prompt);

        $files = $this->parseFilesFromResponse($response->output, $language);

        return new BugFixResult(
            files: $files,
            fixDescription: $response->reasoning,
            confidence: $response->confidence,
        );
    }

    public function getKnowledgeEngine(): KnowledgeEngine
    {
        return $this->knowledge;
    }

    private function parseFilesFromResponse(string $response, string $language): array
    {
        $files = [];
        preg_match_all('/```(?:\w+)?\n(.+?)\n```/s', $response, $matches, PREG_SET_ORDER);

        foreach ($matches as $i => $match) {
            $ext = match ($language) {
                'cyp' => '.cyp',
                'php' => '.php',
                'javascript', 'js' => '.js',
                'typescript', 'ts' => '.ts',
                default => ".{$language}",
            };
            $files[] = [
                'path' => "generated_{$i}{$ext}",
                'content' => $match[1],
                'language' => $language,
            ];
        }

        if (empty($files)) {
            $ext = ".{$language}";
            $files[] = [
                'path' => "output{$ext}",
                'content' => $response,
                'language' => $language,
            ];
        }

        return $files;
    }
}
