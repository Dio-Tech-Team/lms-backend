<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
        // Login: 5 attempts per minute per login name + IP
        RateLimiter::for('login', function (Request $request) {
            // return Limit::perMinute(5)->by(strtolower((string) $request->input('login')) . '|' . $request->ip());
            return Limit::perMinute(5)->by('login|' . strtolower((string) $request->input('login')));
        });

        // OTP: 5 attempts per minute per account
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by('otp|' . $request->input('user_id'));
        });

        // Authenticated API: 60 requests per minute per user
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Mail::extend('brevo', function (array $config) {
            return (new BrevoTransportFactory)->create(
                new Dsn('brevo+api', 'default', config('services.brevo.key'))
            );
        });
    }
}
