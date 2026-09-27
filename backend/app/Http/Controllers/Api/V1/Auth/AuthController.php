<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        /** @var User|null $user */
        $user = User::query()->with('organization')->where('email', strtolower($data['email']))->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            Log::channel('security')->notice('Failed login', ['email' => $data['email'], 'ip' => $request->ip()]);

            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        if (! $user->isActive() || ($user->organization_id !== null && ! $user->organization?->isActive())) {
            Log::channel('security')->notice('Login blocked: inactive account or organization', ['user_id' => $user->id]);

            throw ValidationException::withMessages(['email' => ['Your account is disabled. Contact your administrator.']]);
        }

        $expiresAt = ($data['remember'] ?? false) ? now()->addDays(30) : now()->addHours((int) config('sanctum.session_hours', 12));
        $token = $user->createToken(
            mb_substr($data['device_name'] ?? (string) $request->userAgent() ?: 'web', 0, 100),
            ['*'],
            $expiresAt,
        );

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->audit->log('auth.login', $user, [], ['ip' => $request->ip()], $user->organization_id, $user);

        return $this->ok([
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601ZuluString(),
            ...$this->mePayload($user),
        ], 'Logged in.');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();
        $this->audit->log('auth.logout', $user, [], [], $user->organization_id, $user);

        return $this->ok(null, 'Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->mePayload($request->user()));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink(['email' => strtolower($data['email'])]);

        if ($status === Password::RESET_THROTTLED) {
            return $this->error('Please wait before retrying.', 429);
        }

        // Same answer whether or not the address exists (no account enumeration).
        return $this->ok(null, 'If the address exists, a reset link has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => strtolower($data['email']), 'token' => $data['token'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
                $this->audit->log('auth.password_reset', $user, [], [], $user->organization_id, $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $this->ok(null, 'Password has been reset. You can sign in now.');
    }

    /** @return array<string, mixed> */
    private function mePayload(User $user): array
    {
        $user->loadMissing(['roles', 'organization', 'employee']);

        return [
            'user' => UserResource::make($user)->resolve(),
            'organization' => $user->organization ? OrganizationResource::make($user->organization)->resolve() : null,
            'permissions' => $user->permissions(),
            'is_platform_user' => $user->isPlatformUser(),
        ];
    }
}
