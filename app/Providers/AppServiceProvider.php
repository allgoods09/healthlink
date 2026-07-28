<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.admin', 'layouts.portal'], function ($view): void {
            $user = Auth::user();

            if (! $user) {
                return;
            }

            $view->with('layoutRecentNotifications', $user->notifications()->latest()->limit(6)->get());
            $view->with('layoutUnreadNotificationCount', $user->unreadNotifications()->count());
        });
    }
}
