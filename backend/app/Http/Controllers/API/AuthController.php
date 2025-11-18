<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInviteRequest;
use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Mail\UserInvitationMail;
use App\Models\AuthInvitation;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::query()->with('roles')->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->status === 'disabled') {
            return response()->json(['message' => 'Account disabled'], Response::HTTP_FORBIDDEN);
        }

        $expiresAt = Carbon::now()->addMinutes(config('sanctum.expiration', 120));
        $token = $user->createToken('api', abilities: ['*'], expiresAt: $expiresAt);

        $user->forceFill([
            'last_login_at' => now(),
            'status' => $user->status === 'invited' ? 'active' : $user->status,
        ])->save();

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => now()->diffInSeconds($expiresAt),
            'user' => new UserResource($user->refresh()->load('roles')),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function invite(InviteUserRequest $request)
    {
        Gate::authorize('invite-users');

        $payload = $request->validated();

        $user = User::query()->firstOrCreate(
            ['email' => $payload['email']],
            [
                'name' => $payload['name'],
                'password_hash' => Hash::make(Str::random(40)),
                'timezone' => 'UTC',
                'status' => 'invited',
                'invited_at' => now(),
            ]
        );

        $token = Str::uuid()->toString();
        $invitation = AuthInvitation::query()->updateOrCreate(
            ['email' => $payload['email']],
            [
                'token' => $token,
                'expires_at' => now()->addDay(),
                'invited_by' => auth()->id(),
                'accepted_at' => null,
            ]
        );

        $roles = Role::query()->whereIn('slug', $payload['roles'])->pluck('id')->all();
        if ($roles) {
            $syncPayload = [];
            foreach ($roles as $roleId) {
                $syncPayload[$roleId] = [
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                ];
            }
            $user->roles()->syncWithoutDetaching($syncPayload);
        }

        Mail::to($user->email)->queue(new UserInvitationMail($invitation));

        return response()->json([
            'message' => 'Invitation sent.',
            'invitation_id' => $invitation->id,
        ], Response::HTTP_CREATED);
    }

    public function acceptInvite(AcceptInviteRequest $request)
    {
        $invitation = AuthInvitation::query()
            ->where('token', $request->validated('token'))
            ->where('expires_at', '>=', now())
            ->firstOrFail();

        $user = User::query()->where('email', $invitation->email)->firstOrFail();

        $user->forceFill([
            'name' => $request->validated('name'),
            'password_hash' => Hash::make($request->validated('password')),
            'status' => 'active',
            'last_login_at' => now(),
        ])->save();

        $invitation->update(['accepted_at' => now()]);

        return response()->json([
            'message' => 'Invitation accepted',
        ]);
    }
}
