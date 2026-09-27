<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'in:en,ru,uz'],
        ]);

        $user->update([...$data, 'email' => strtolower($data['email'])]);

        return $this->ok(UserResource::make($user->load('roles')), 'Profile updated.');
    }

    public function avatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000']]);

        $old = $user->avatar_path;
        $path = $request->file('avatar')->storeAs(
            'avatars/'.($user->organization_id ?? 'platform'),
            Str::uuid().'.'.$request->file('avatar')->guessExtension(),
            ['disk' => 'public'],
        );
        $user->update(['avatar_path' => $path]);
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return $this->ok(UserResource::make($user->load('roles')), 'Avatar updated.');
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return $this->ok(UserResource::make($user->load('roles')), 'Avatar removed.');
    }

    public function password(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user->update(['password' => $request->input('password')]);
        // Sign out every other session.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();
        $this->audit->log('auth.password_changed', $user);

        return $this->ok(null, 'Password changed. Other sessions were signed out.');
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentId = $user->currentAccessToken()?->id;

        return $this->ok($user->tokens()->orderByDesc('last_used_at')->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at?->toIso8601ZuluString(),
            'created_at' => $token->created_at?->toIso8601ZuluString(),
            'expires_at' => $token->expires_at?->toIso8601ZuluString(),
            'is_current' => $token->id === $currentId,
        ])->values());
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $request->user()->tokens()->whereKey($id)->delete();

        return $this->ok(null, 'Session revoked.');
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return $this->ok(null, 'Other sessions revoked.');
    }
}
