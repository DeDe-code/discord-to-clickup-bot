<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No database-related registrations needed
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
