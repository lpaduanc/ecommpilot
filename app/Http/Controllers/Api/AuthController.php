<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Sua conta está desativada. Entre em contato com o suporte.'],
            ]);
        }

        $user->recordLogin();

        ActivityLog::log('user.login', $user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
        ]);

        ActivityLog::log('user.register', $user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Conta criada com sucesso.',
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('activeStore');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        $user->update($validated);

        ActivityLog::log('user.profile_updated', $user);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'user' => new UserResource($user),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'A senha atual é obrigatória.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'A nova senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'As senhas não conferem.',
        ]);

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        ActivityLog::log('user.password_changed', $user);

        return response()->json([
            'message' => 'Senha atualizada com sucesso.',
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // SECURITY: Send reset link silently to prevent email enumeration
        // Always return success message regardless of whether email exists
        Password::sendResetLink($request->only('email'));

        // Log for internal monitoring (not exposed to user)
        Log::info('Password reset attempted', [
            'email' => $request->email,
            'ip' => $request->ip(),
        ]);

        // Return generic message to prevent email enumeration attack
        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link de redefinição.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update([
                    'password' => $password,
                    'must_change_password' => false,
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Não foi possível redefinir a senha.'],
            ]);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        ActivityLog::log('user.logout', $request->user());

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Logout de todos os dispositivos do usuário.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete all tokens for the user
        $user->tokens()->delete();

        ActivityLog::log('user.logout_all', $user);

        return response()->json([
            'message' => 'Logout realizado em todos os dispositivos.',
        ]);
    }

    public function getNotificationSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->notification_settings ?? [
            'email_analysis' => true,
            'stock_alerts' => true,
            'new_orders' => false,
            'system_updates' => true,
        ];

        return response()->json($settings);
    }

    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email_analysis' => ['boolean'],
            'stock_alerts' => ['boolean'],
            'new_orders' => ['boolean'],
            'system_updates' => ['boolean'],
        ]);

        $user->update(['notification_settings' => $validated]);

        ActivityLog::log('user.notification_settings_updated', $user);

        return response()->json([
            'message' => 'Preferências de notificação atualizadas.',
            'settings' => $validated,
        ]);
    }
}
