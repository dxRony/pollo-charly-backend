<?php

namespace App\Providers;

use App\Services\Images\CloudinaryImageStorage;
use App\Services\Images\ImageStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ImageStorage::class, CloudinaryImageStorage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        $this->configureAuthRateLimiting();
    }

    /**
     * Limita los intentos de autenticación por correo y no por IP: detrás de CloudFront todas las
     * peticiones llegan con la IP de la red de CloudFront, así que un límite por IP agruparía a
     * usuarios distintos.
     */
    private function configureAuthRateLimiting(): void
    {
        $byEmail = fn (Request $request, string $scope): string => $scope.'|'.Str::lower(trim((string) $request->input('email')));

        $tooManyAttempts = fn (Request $request, array $headers) => response()->json([
            'message' => 'Demasiados intentos. Espera un minuto antes de intentarlo de nuevo.',
        ], 429, $headers);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by($byEmail($request, 'login'))
            ->response($tooManyAttempts));

        RateLimiter::for('password-recovery', fn (Request $request) => Limit::perMinute(3)
            ->by($byEmail($request, 'password-recovery'))
            ->response($tooManyAttempts));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by($byEmail($request, 'password-reset'))
            ->response($tooManyAttempts));
    }
}
