<?php

namespace Cypher\Compiler\CodeGen\Auth;

use Cypher\Compiler\AST\ModuleNode;

class AuthGenerator
{
    private array $generatedFiles = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function generate(ModuleNode $module): array
    {
        $this->generatedFiles = [];

        // Check if any model has auth-related fields
        $hasAuth = false;
        foreach ($module->statements as $stmt) {
            if ($stmt instanceof \Cypher\Compiler\AST\ModelDeclStmt) {
                $fieldNames = array_map(fn($f) => $f->name, $stmt->fields);
                if (in_array('email', $fieldNames) && in_array('password', $fieldNames)) {
                    $hasAuth = true;
                    break;
                }
            }
        }

        if (!$hasAuth) {
            // Generate minimal auth anyway
        }

        $this->generateAuthControllers();
        $this->generateAuthRequests();
        $this->generateAuthRoutes();
        $this->generateAuthFrontend();
        $this->generateJWTConfig();

        return $this->generatedFiles;
    }

    private function generateAuthControllers(): void
    {
        // RegisterController
        $this->generatedFiles['app/Http/Controllers/Auth/RegisterController.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
}
PHP;

        // LoginController
        $this->generatedFiles['app/Http/Controllers/Auth/LoginController.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
PHP;

        // LogoutController
        $this->generatedFiles['app/Http/Controllers/Auth/LogoutController.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
PHP;

        // MeController (current user)
        $this->generatedFiles['app/Http/Controllers/Auth/MeController.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }
}
PHP;

        // PasswordResetController
        $this->generatedFiles['app/Http/Controllers/Auth/PasswordResetController.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => $status,
            'message' => __($status),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = bcrypt($password);
                $user->save();
            }
        );

        return response()->json([
            'status' => $status,
            'message' => __($status),
        ]);
    }
}
PHP;
    }

    private function generateAuthRequests(): void
    {
        // RegisterRequest
        $this->generatedFiles['app/Http/Requests/Auth/RegisterRequest.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
PHP;

        // LoginRequest
        $this->generatedFiles['app/Http/Requests/Auth/LoginRequest.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
PHP;
    }

    private function generateAuthRoutes(): void
    {
        $this->generatedFiles['routes/auth.php'] = <<<'PHP'
<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class);
    Route::post('/logout', LogoutController::class);
});
PHP;
    }

    private function generateAuthFrontend(): void
    {
        // AuthProvider
        $this->generatedFiles['frontend/src/contexts/AuthContext.tsx'] = <<<'TSX'
import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../lib/api';

interface User {
    id: string;
    name: string;
    email: string;
}

interface AuthContextType {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    login: (email: string, password: string) => Promise<void>;
    register: (name: string, email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [user, setUser] = useState<User | null>(null);
    const [token, setToken] = useState<string | null>(localStorage.getItem('auth_token'));
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        if (token) {
            api.get('/me')
                .then(({ data }) => setUser(data))
                .catch(() => { localStorage.removeItem('auth_token'); setToken(null); })
                .finally(() => setIsLoading(false));
        } else {
            setIsLoading(false);
        }
    }, [token]);

    const login = useCallback(async (email: string, password: string) => {
        const { data } = await api.post('/login', { email, password });
        localStorage.setItem('auth_token', data.token);
        setToken(data.token);
        setUser(data.user);
    }, []);

    const register = useCallback(async (name: string, email: string, password: string) => {
        const { data } = await api.post('/register', { name, email, password });
        localStorage.setItem('auth_token', data.token);
        setToken(data.token);
        setUser(data.user);
    }, []);

    const logout = useCallback(async () => {
        await api.post('/logout');
        localStorage.removeItem('auth_token');
        setToken(null);
        setUser(null);
    }, []);

    return (
        <AuthContext.Provider value={{ user, token, isAuthenticated: !!user, isLoading, login, register, logout }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth must be used within AuthProvider');
    return ctx;
};
TSX;

        // Login page
        $this->generatedFiles['frontend/src/pages/Login.tsx'] = <<<'TSX'
import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';

const Login: React.FC = () => {
    const { login } = useAuth();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await login(email, password);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Login failed');
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
            <div className="max-w-md w-full p-8 bg-white dark:bg-gray-800 rounded-lg shadow">
                <h2 className="text-2xl font-bold mb-6 text-center">Sign In</h2>
                {error && <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">{error}</div>}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">Email</label>
                        <input type="email" value={email} onChange={e => setEmail(e.target.value)}
                            className="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Password</label>
                        <input type="password" value={password} onChange={e => setPassword(e.target.value)}
                            className="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required />
                    </div>
                    <button type="submit"
                        className="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Sign In
                    </button>
                </form>
            </div>
        </div>
    );
};

export default Login;
TSX;

        // Register page
        $this->generatedFiles['frontend/src/pages/Register.tsx'] = <<<'TSX'
import React, { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';

const Register: React.FC = () => {
    const { register } = useAuth();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await register(name, email, password);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Registration failed');
        }
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
            <div className="max-w-md w-full p-8 bg-white dark:bg-gray-800 rounded-lg shadow">
                <h2 className="text-2xl font-bold mb-6 text-center">Create Account</h2>
                {error && <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">{error}</div>}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium mb-1">Name</label>
                        <input type="text" value={name} onChange={e => setName(e.target.value)}
                            className="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Email</label>
                        <input type="email" value={email} onChange={e => setEmail(e.target.value)}
                            className="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Password</label>
                        <input type="password" value={password} onChange={e => setPassword(e.target.value)}
                            className="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600"
                            required />
                    </div>
                    <button type="submit"
                        className="w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Create Account
                    </button>
                </form>
            </div>
        </div>
    );
};

export default Register;
TSX;
    }

    private function generateJWTConfig(): void
    {
        $this->generatedFiles['config/sanctum.php'] = <<<'PHP'
<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:3000')),
    'guard' => ['web'],
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
    ],
];
PHP;
    }
}
