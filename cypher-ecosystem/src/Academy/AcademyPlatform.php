<?php

namespace Cypher\Ecosystem\Academy;

class AcademyPlatform
{
    private array $courses = [];
    private array $enrollments = [];
    private array $labs = [];
    private array $sandboxSessions = [];
    private string $dataDir;

    public function __construct(array $config = [])
    {
        $this->dataDir = $config['data_dir'] ?? (getcwd() . '/.cyp-ecosystem/academy');
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0700, true);
        }
        $this->load();
    }

    public function createCourse(string $title, string $description, string $level, array $modules, array $metadata = []): array
    {
        $id = uniqid('course_', true);
        $course = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'level' => $level,
            'modules' => $modules,
            'metadata' => $metadata,
            'status' => 'published',
            'enrolled_count' => 0,
            'rating' => 0.0,
            'created_at' => date('c'),
        ];
        $this->courses[$id] = $course;
        $this->save();
        return $course;
    }

    public function enroll(string $courseId, string $userId): string
    {
        if (!isset($this->courses[$courseId])) {
            throw new AcademyException("Course not found: {$courseId}");
        }

        $id = uniqid('enroll_', true);
        $course = $this->courses[$courseId];

        $this->enrollments[$id] = [
            'id' => $id,
            'course_id' => $courseId,
            'user_id' => $userId,
            'status' => 'active',
            'progress' => 0.0,
            'completed_modules' => [],
            'started_at' => date('c'),
            'completed_at' => null,
        ];

        $this->courses[$courseId]['enrolled_count']++;
        $this->save();

        return $id;
    }

    public function completeModule(string $enrollmentId, string $moduleId, float $score): array
    {
        $enrollment = $this->enrollments[$enrollmentId] ?? null;
        if (!$enrollment) {
            throw new AcademyException("Enrollment not found");
        }

        $course = $this->courses[$enrollment['course_id']] ?? null;
        if (!$course) {
            throw new AcademyException("Course not found");
        }

        $enrollment['completed_modules'][] = [
            'module_id' => $moduleId,
            'score' => $score,
            'completed_at' => date('c'),
        ];

        $totalModules = count($course['modules']);
        $completedModules = count($enrollment['completed_modules']);
        $enrollment['progress'] = $totalModules > 0 ? $completedModules / $totalModules : 0;

        if ($enrollment['progress'] >= 1.0) {
            $enrollment['status'] = 'completed';
            $enrollment['completed_at'] = date('c');
        }

        $this->enrollments[$enrollmentId] = $enrollment;
        $this->save();

        return $enrollment;
    }

    public function createLab(string $title, string $description, string $difficulty, array $steps): array
    {
        $id = uniqid('lab_', true);
        $lab = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'difficulty' => $difficulty,
            'steps' => $steps,
            'status' => 'published',
            'completions' => 0,
            'created_at' => date('c'),
        ];
        $this->labs[$id] = $lab;
        $this->save();
        return $lab;
    }

    public function completeLab(string $labId, string $userId): void
    {
        if (isset($this->labs[$labId])) {
            $this->labs[$labId]['completions']++;
            $this->save();
        }
    }

    public function createSandbox(string $userId, string $template = 'blank'): string
    {
        $id = uniqid('sandbox_', true);
        $this->sandboxSessions[$id] = [
            'id' => $id,
            'user_id' => $userId,
            'template' => $template,
            'status' => 'active',
            'created_at' => date('c'),
            'expires_at' => date('c', time() + 7200),
        ];
        $this->save();
        return $id;
    }

    public function listCourses(string $level = ''): array
    {
        if ($level) {
            return array_values(array_filter($this->courses, fn($c) => $c['level'] === $level));
        }
        return array_values($this->courses);
    }

    public function getCourse(string $id): ?array
    {
        return $this->courses[$id] ?? null;
    }

    public function getUserEnrollments(string $userId): array
    {
        return array_values(array_filter($this->enrollments, fn($e) => $e['user_id'] === $userId));
    }

    public function getStats(): array
    {
        return [
            'courses' => count($this->courses),
            'enrollments' => count($this->enrollments),
            'labs' => count($this->labs),
            'lab_completions' => array_sum(array_column($this->labs, 'completions')),
            'active_sandboxes' => count(array_filter($this->sandboxSessions, fn($s) => $s['status'] === 'active')),
        ];
    }

    public function getLearningPath(string $level = 'beginner'): array
    {
        $paths = [
            'beginner' => [
                ['type' => 'course', 'id' => 'introduction-to-cyp'],
                ['type' => 'lab', 'id' => 'first-cyp-program'],
                ['type' => 'course', 'id' => 'cyp-basics'],
            ],
            'intermediate' => [
                ['type' => 'course', 'id' => 'full-stack-with-cyp'],
                ['type' => 'lab', 'id' => 'building-apis'],
                ['type' => 'course', 'id' => 'ai-agents-in-cyp'],
            ],
            'advanced' => [
                ['type' => 'course', 'id' => 'enterprise-cyp'],
                ['type' => 'lab', 'id' => 'multi-agent-systems'],
                ['type' => 'course', 'id' => 'cyp-architecture'],
            ],
        ];

        return $paths[$level] ?? $paths['beginner'];
    }

    private function load(): void
    {
        $file = $this->dataDir . '/academy.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $this->courses = $data['courses'] ?? [];
                $this->enrollments = $data['enrollments'] ?? [];
                $this->labs = $data['labs'] ?? [];
                $this->sandboxSessions = $data['sandboxes'] ?? [];
            }
        }
    }

    private function save(): void
    {
        file_put_contents(
            $this->dataDir . '/academy.json',
            json_encode([
                'courses' => $this->courses,
                'enrollments' => $this->enrollments,
                'labs' => $this->labs,
                'sandboxes' => $this->sandboxSessions,
            ], JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
