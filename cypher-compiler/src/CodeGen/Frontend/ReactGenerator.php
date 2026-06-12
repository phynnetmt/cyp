<?php

namespace Cypher\Compiler\CodeGen\Frontend;

use Cypher\Compiler\AST\{
    ModuleNode, ModelDeclStmt, PageDeclStmt, ComponentDeclStmt,
    VarDeclStmt, SayStmt, ExpressionStmt,
    LiteralExpr, IdentifierExpr,
};

class ReactGenerator
{
    private array $generatedFiles = [];
    private array $config;
    private array $components = [];
    private int $indentLevel = 0;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function generate(ModuleNode $module): array
    {
        $this->generatedFiles = [];

        // Generate base project files
        $this->generateProjectFiles();

        // Generate API client
        $this->generateAPIclient();

        // Generate app shell and router (with auth integration)
        $this->generateAppShell();

        foreach ($module->statements as $stmt) {
            match ($stmt::class) {
                PageDeclStmt::class => $this->generatePage($stmt),
                ComponentDeclStmt::class => $this->generateComponent($stmt),
                ModelDeclStmt::class => $this->generateModelService($stmt),
                default => null,
            };
        }

        // Generate route aggregator
        $this->generateRouteAggregator();

        return $this->generatedFiles;
    }

    private function generateProjectFiles(): void
    {
        // package.json
        $this->generatedFiles['frontend/package.json'] = json_encode([
            'name' => 'cyp-frontend',
            'version' => '0.1.0',
            'private' => true,
            'type' => 'module',
            'scripts' => [
                'dev' => 'vite',
                'build' => 'tsc && vite build',
                'preview' => 'vite preview',
            ],
            'dependencies' => [
                'react' => '^18.3.0',
                'react-dom' => '^18.3.0',
                'react-router-dom' => '^6.26.0',
                'axios' => '^1.7.0',
                '@headlessui/react' => '^2.1.0',
                '@heroicons/react' => '^2.1.0',
            ],
            'devDependencies' => [
                '@types/react' => '^18.3.0',
                '@types/react-dom' => '^18.3.0',
                '@vitejs/plugin-react' => '^4.3.0',
                'autoprefixer' => '^10.4.0',
                'postcss' => '^8.4.0',
                'tailwindcss' => '^3.4.0',
                'typescript' => '^5.5.0',
                'vite' => '^5.4.0',
            ],
        ], JSON_PRETTY_PRINT);

        // vite.config.ts
        $this->generatedFiles['frontend/vite.config.ts'] = <<<'TS'
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    server: {
        port: 3000,
        proxy: {
            '/api': 'http://localhost:8000',
        },
    },
});
TS;

        // tailwind.config.js
        $this->generatedFiles['frontend/tailwind.config.js'] = <<<'JS'
/** @type {import('tailwindcss').Config} */
export default {
    content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
        },
    },
    plugins: [],
};
JS;

        // postcss.config.js
        $this->generatedFiles['frontend/postcss.config.js'] = <<<'JS'
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
JS;

        // tsconfig.json
        $this->generatedFiles['frontend/tsconfig.json'] = json_encode([
            'compilerOptions' => [
                'target' => 'ES2020',
                'useDefineForClassFields' => true,
                'lib' => ['ES2020', 'DOM', 'DOM.Iterable'],
                'module' => 'ESNext',
                'skipLibCheck' => true,
                'moduleResolution' => 'bundler',
                'allowImportingTsExtensions' => true,
                'isolatedModules' => true,
                'moduleDetection' => 'force',
                'noEmit' => true,
                'jsx' => 'react-jsx',
                'strict' => true,
                'noUnusedLocals' => false,
                'noUnusedParameters' => false,
                'noFallthroughCasesInSwitch' => true,
            ],
            'include' => ['src'],
        ], JSON_PRETTY_PRINT);

        // index.html
        $this->generatedFiles['frontend/index.html'] = <<<'HTML'
<!DOCTYPE html>
<html lang="en" class="dark">
    <head>
        <meta charset="UTF-8" />
        <link rel="icon" type="image/svg+xml" href="/vite.svg" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>CYP Application</title>
    </head>
    <body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        <div id="root"></div>
        <script type="module" src="/src/main.tsx"></script>
    </body>
</html>
HTML;

        // src/index.css (Tailwind base)
        $this->generatedFiles['frontend/src/index.css'] = <<<'CSS'
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
    body {
        @apply antialiased;
    }
}
CSS;

        // src/main.tsx
        $this->generatedFiles['frontend/src/main.tsx'] = <<<'TSX'
import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import './index.css';

ReactDOM.createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <BrowserRouter>
            <App />
        </BrowserRouter>
    </React.StrictMode>
);
TSX;

        // src/vite-env.d.ts
        $this->generatedFiles['frontend/src/vite-env.d.ts'] = <<<'TS'
/// <reference types="vite/client" />
TS;
    }

    private function generateAppShell(): void
    {
        $this->generatedFiles['frontend/src/App.tsx'] = <<<'TSX'
import React from 'react';
import { Routes, Route } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import Layout from './components/Layout';
import { routes as appRoutes } from './routes';

const App: React.FC = () => {
    return (
        <AuthProvider>
            <Layout>
                <Routes>
                    {appRoutes.map((route, i) => (
                        <Route key={i} path={route.path} element={<route.element />} />
                    ))}
                </Routes>
            </Layout>
        </AuthProvider>
    );
};

export default App;
TSX;

        // Layout component
        $this->generatedFiles['frontend/src/components/Layout.tsx'] = <<<'TSX'
import React from 'react';

interface LayoutProps {
    children: React.ReactNode;
}

const Layout: React.FC<LayoutProps> = ({ children }) => {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
            <nav className="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        <div className="flex items-center">
                            <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                                CYP App
                            </h1>
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

    private function generatePage($stmt): void
    {
        $pageName = ucfirst($stmt->name);

        $this->components[] = $pageName;

        $code = "import React from 'react';\n";

        // Use imports for components referenced in the page body
        $usedComps = [];
        foreach ($stmt->body as $s) {
            if ($s instanceof ExpressionStmt && $s->expression instanceof CallExpr) {
                $usedComps[] = $s->expression->callee;
            }
        }

        $code .= "\nconst {$pageName}: React.FC = () => {\n";
        $code .= "    return (\n";
        $code .= '        <div className="p-6">' . "\n";
        $code .= '            <h1 className="text-2xl font-bold mb-4">' . $pageName . "</h1>\n";

        foreach ($stmt->body as $s) {
            $code .= $this->generateJSXStatement($s);
        }

        $code .= "        </div>\n";
        $code .= "    );\n";
        $code .= "};\n\n";
        $code .= "export default {$pageName};\n";

        $this->generatedFiles["frontend/src/pages/{$pageName}.tsx"] = $code;

        // Add route to App.tsx
        // This would be better with a router generator, but for now append
        $routePath = '/' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $stmt->name));
        $routeImport = "import {$pageName} from './pages/{$pageName}';\n";

        $this->generatedFiles['frontend/src/routes.ts'] = $this->generateRouteDefinition($pageName, $routePath);
    }

    private function generateComponent($stmt): void
    {
        $compName = ucfirst($stmt->name);

        $props = [];
        foreach ($stmt->props as $p) {
            $tsType = $p->typeHint ? $this->tsType($p->typeHint) : 'any';
            $props[] = "    {$p->name}: {$tsType};";
        }

        $propsStr = !empty($props)
            ? "interface {$compName}Props {\n" . implode("\n", $props) . "\n}\n\n"
            : '';

        $code = "import React from 'react';\n\n";
        $code .= $propsStr;
        $code .= "const {$compName}: React.FC<{$compName}Props> = ({ " . implode(', ', array_map(fn($p) => $p->name, $stmt->props)) . " }) => {\n";
        $code .= "    return (\n";
        $code .= '        <div className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">' . "\n";
        $code .= "            <h3 className=\"text-lg font-semibold\">{$compName}</h3>\n";
        $code .= "        </div>\n";
        $code .= "    );\n";
        $code .= "};\n\n";
        $code .= "export default {$compName};\n";

        $this->generatedFiles["frontend/src/components/{$compName}.tsx"] = $code;
    }

    private function generateModelService($stmt): void
    {
        $modelName = $stmt->name;
        $endpoint = '/' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $modelName)) . 's';

        $code = "import api from '../lib/api';\n\n";
        $code .= "export interface {$modelName}Data {\n";
        foreach ($stmt->fields as $field) {
            $tsType = $this->tsType($field->type);
            $code .= "    {$field->name}: {$tsType};\n";
        }
        $code .= "}\n\n";

        $code .= "export const get{$modelName}s = async (): Promise<{$modelName}Data[]> => {\n";
        $code .= "    const { data } = await api.get('{$endpoint}');\n";
        $code .= "    return data;\n";
        $code .= "};\n\n";

        $code .= "export const get{$modelName} = async (id: string): Promise<{$modelName}Data> => {\n";
        $code .= "    const { data } = await api.get(`{$endpoint}/\${id}`);\n";
        $code .= "    return data;\n";
        $code .= "};\n\n";

        $code .= "export const create{$modelName} = async (payload: Partial<{$modelName}Data>): Promise<{$modelName}Data> => {\n";
        $code .= "    const { data } = await api.post('{$endpoint}', payload);\n";
        $code .= "    return data;\n";
        $code .= "};\n\n";

        $code .= "export const update{$modelName} = async (id: string, payload: Partial<{$modelName}Data>): Promise<{$modelName}Data> => {\n";
        $code .= "    const { data } = await api.put(`{$endpoint}/\${id}`, payload);\n";
        $code .= "    return data;\n";
        $code .= "};\n\n";

        $code .= "export const delete{$modelName} = async (id: string): Promise<void> => {\n";
        $code .= "    await api.delete(`{$endpoint}/\${id}`);\n";
        $code .= "};\n";

        $this->generatedFiles["frontend/src/services/{$modelName}Service.ts"] = $code;
    }

    private function generateAPIclient(): void
    {
        // Check if already generated
        if (isset($this->generatedFiles['frontend/src/lib/api.ts'])) return;

        $this->generatedFiles['frontend/src/lib/api.ts'] = <<<'TS'
import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;
TS;

        $this->generatedFiles['frontend/src/lib/index.ts'] = "export { default as api } from './api';\n";
    }

    private function generateRouteAggregator(): void
    {
        $this->generatedFiles['frontend/src/routes.ts'] = <<<'TS'
import { RouteProps } from 'react-router-dom';

export interface AppRoute {
    path: string;
    element: React.ComponentType;
}

export const routes: AppRoute[] = [];
TS;
    }

    private function generateRouteDefinition(string $page, string $path): string
    {
        return "import {$page} from './pages/{$page}';\n\nexport const routes = [\n    { path: '{$path}', element: {$page} },\n];\n";
    }

    private function generateJSXStatement($stmt): string
    {
        return match ($stmt::class) {
            SayStmt::class => '            <p>' . $this->jsxValue($stmt->expression) . "</p>\n",
            VarDeclStmt::class => '',
            default => '',
        };
    }

    private function jsxValue($expr): string
    {
        if ($expr instanceof LiteralExpr && $expr->literalType === 'string') {
            return htmlspecialchars($expr->value, ENT_QUOTES, 'UTF-8');
        }
        return '{/* expression */}';
    }

    private function tsType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'number', 'float', 'double' => 'number',
            'string', 'text', 'varchar' => 'string',
            'bool', 'boolean' => 'boolean',
            'array', 'json' => 'any[]',
            'object' => 'Record<string, any>',
            default => 'string',
        };
    }

    private function indent(): string
    {
        return str_repeat('    ', $this->indentLevel);
    }
}
