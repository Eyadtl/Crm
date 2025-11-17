<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        Gate::define('invite-users', fn (User $user) => $user->hasRole('admin'));
        Gate::define('manage-email-accounts', fn (User $user) => $user->hasRole('manager') || $user->hasRole('admin'));
        Gate::define('manage-projects', fn (User $user) => $user->hasRole('manager') || $user->hasRole('editor'));
        Gate::define('view-email', fn (User $user) => in_array($user->status, ['active']));
        Gate::define('manage-exports', fn (User $user) => $user->hasRole('manager') || $user->hasRole('editor'));
    }
}
