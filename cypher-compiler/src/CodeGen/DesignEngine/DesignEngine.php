<?php

namespace Cypher\Compiler\CodeGen\DesignEngine;

use Cypher\Compiler\AST\{
    ModuleNode, PageDeclStmt, ComponentDeclStmt, ModelDeclStmt,
    VarDeclStmt, SayStmt, LiteralExpr, IdentifierExpr,
};

class DesignEngine
{
    private array $config;
    private array $generatedDesigns = [];

    private const DESIGN_SYSTEMS = [
        'modern' => [
            'colors' => ['primary' => '#3b82f6', 'secondary' => '#8b5cf6', 'accent' => '#f59e0b'],
            'fonts' => ['sans' => 'Inter, system-ui, sans-serif', 'mono' => 'JetBrains Mono, monospace'],
            'borderRadius' => '0.5rem',
            'shadow' => '0 1px 3px rgba(0,0,0,0.1)',
        ],
        'minimal' => [
            'colors' => ['primary' => '#000000', 'secondary' => '#ffffff', 'accent' => '#3b82f6'],
            'fonts' => ['sans' => 'system-ui, sans-serif', 'mono' => 'monospace'],
            'borderRadius' => '0',
            'shadow' => 'none',
        ],
        'playful' => [
            'colors' => ['primary' => '#ec4899', 'secondary' => '#fbbf24', 'accent' => '#34d399'],
            'fonts' => ['sans' => '"Comic Neue", system-ui, sans-serif', 'mono' => '"Fira Code", monospace'],
            'borderRadius' => '1rem',
            'shadow' => '0 4px 6px rgba(0,0,0,0.1)',
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function generateDesignSystem(ModuleNode $module): array
    {
        $this->generatedDesigns = [];

        $theme = $this->config['design_theme'] ?? 'modern';
        $system = self::DESIGN_SYSTEMS[$theme] ?? self::DESIGN_SYSTEMS['modern'];

        $this->generateTailwindConfig($system);
        $this->generateGlobalStyles($system);
        $this->generateLayoutComponents($module, $system);
        $this->generateResponsiveMeta();
        $this->generateAccessibilityConfig();

        return $this->generatedDesigns;
    }

    private function generateTailwindConfig(array $system): void
    {
        $colors = $system['colors'];
        $borderRadius = $system['borderRadius'];

        $this->generatedDesigns['frontend/tailwind.config.js'] = <<<JS
/** @type {import('tailwindcss').Config} */
export default {
    content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '{$this->hexToRgbShade($colors['primary'], 50)}',
                    100: '{$this->hexToRgbShade($colors['primary'], 100)}',
                    200: '{$this->hexToRgbShade($colors['primary'], 200)}',
                    300: '{$this->hexToRgbShade($colors['primary'], 300)}',
                    400: '{$this->hexToRgbShade($colors['primary'], 400)}',
                    500: '{$colors['primary']}',
                    600: '{$this->hexToRgbShade($colors['primary'], 600)}',
                    700: '{$this->hexToRgbShade($colors['primary'], 700)}',
                    800: '{$this->hexToRgbShade($colors['primary'], 800)}',
                    900: '{$this->hexToRgbShade($colors['primary'], 900)}',
                },
                secondary: {
                    500: '{$colors['secondary']}',
                },
                accent: {
                    500: '{$colors['accent']}',
                },
            },
            fontFamily: {
                sans: [{$this->phpArray($system['fonts']['sans'])}],
                mono: [{$this->phpArray($system['fonts']['mono'])}],
            },
            borderRadius: {
                DEFAULT: '{$borderRadius}',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
    ],
};
JS;
    }

    private function generateGlobalStyles(array $system): void
    {
        $this->generatedDesigns['frontend/src/index.css'] = <<<CSS
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
    * {
        @apply border-gray-200 dark:border-gray-700;
    }

    body {
        @apply bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased;
        font-family: {$system['fonts']['sans']};
    }

    h1, h2, h3, h4, h5, h6 {
        @apply font-semibold tracking-tight;
    }

    code, pre {
        font-family: {$system['fonts']['mono']};
    }
}

@layer components {
    .btn-primary {
        @apply inline-flex items-center px-4 py-2 rounded-lg font-medium text-white
               bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2
               focus:ring-primary-500 focus:ring-offset-2 transition-colors duration-150;
    }

    .btn-secondary {
        @apply inline-flex items-center px-4 py-2 rounded-lg font-medium text-gray-700
               bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700
               border border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-2
               focus:ring-primary-500 focus:ring-offset-2 transition-colors duration-150;
    }

    .card {
        @apply bg-white dark:bg-gray-800 rounded-lg shadow-sm border
               border-gray-200 dark:border-gray-700 p-6;
    }

    .input {
        @apply block w-full rounded-lg border border-gray-300 dark:border-gray-600
               bg-white dark:bg-gray-800 px-3 py-2 text-sm placeholder-gray-400
               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
               transition-colors duration-150;
    }

    .badge {
        @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium;
    }
}
CSS;
    }

    private function generateLayoutComponents(ModuleNode $module, array $system): void
    {
        $pages = [];
        $models = [];
        foreach ($module->statements as $stmt) {
            if ($stmt instanceof PageDeclStmt) $pages[] = $stmt->name;
            if ($stmt instanceof ModelDeclStmt) $models[] = $stmt->name;
        }

        $navItems = '';
        foreach ($pages as $page) {
            $route = '/' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $page));
            $navItems .= "                            <a href=\"{$route}\" className=\"text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 px-3 py-2 text-sm font-medium\">{$page}</a>\n";
        }

        $this->generatedDesigns['frontend/src/components/Layout.tsx'] = <<<TSX
import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

interface LayoutProps {
    children: React.ReactNode;
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
    const { isAuthenticated, user, logout } = useAuth();

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
            <nav className="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex items-center space-x-8">
                            <Link to="/" className="text-xl font-bold text-primary-600 dark:text-primary-400">
                                CYP App
                            </Link>
                            <div className="hidden sm:flex space-x-4">
{$navItems}
                            </div>
                        </div>
                        <div className="flex items-center space-x-4">
                            {isAuthenticated ? (
                                <>
                                    <span className="text-sm text-gray-600 dark:text-gray-300">
                                        {user?.name}
                                    </span>
                                    <button onClick={logout}
                                        className="btn-secondary text-sm">
                                        Logout
                                    </button>
                                </>
                            ) : (
                                <>
                                    <Link to="/login" className="text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600">
                                        Sign In
                                    </Link>
                                    <Link to="/register" className="btn-primary text-sm">
                                        Sign Up
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </nav>
            <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                {children}
            </main>
        </div>
    );
};

export default Layout;
TSX;
    }

    private function generateResponsiveMeta(): void
    {
        // Responsive meta is added to index.html via ReactGenerator
    }

    private function generateAccessibilityConfig(): void
    {
        // Accessibility is built into the generated components via semantic HTML and ARIA attributes
    }

    private function hexToRgbShade(string $hex, int $level): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $factor = match (true) {
            $level <= 50 => 0.95 + ($level / 1000),
            $level <= 400 => 1.0 - (($level - 50) / 700),
            $level >= 600 => 1.0 - (($level - 500) / 500),
            default => 1.0,
        };

        $r = max(0, min(255, (int)round($r * $factor)));
        $g = max(0, min(255, (int)round($g * $factor)));
        $b = max(0, min(255, (int)round($b * $factor)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function phpArray(string $value): string
    {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }
}
