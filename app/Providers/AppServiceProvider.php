<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('portal-login', function (Request $request): array {
            return [
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
                Limit::perMinute(5)->by('login-account:'.$request->ip().'|'.mb_strtolower($request->string('email')->toString())),
            ];
        });
        RateLimiter::for('portal-submissions', function (Request $request): array {
            return [
                Limit::perMinute(120)->by('submission-ip:'.$request->ip()),
                Limit::perMinute(6)->by('submission-session:'.$request->ip().'|'.$request->session()->getId()),
            ];
        });
        RateLimiter::for('portal-access', fn (Request $request) => Limit::perMinute(120)->by('private-access:'.$request->ip().'|'.$request->session()->getId()));
    }
}
