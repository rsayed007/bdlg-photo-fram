<?php

namespace App\Providers;

use App\Contracts\ProfileFetcher;
use App\Models\FrameGeneration;
use App\Policies\FrameGenerationPolicy;
use App\Services\GoogleMaps\BrowsershotProfileFetcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProfileFetcher::class, BrowsershotProfileFetcher::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::policy(FrameGeneration::class, FrameGenerationPolicy::class);
        Gate::define('admin', fn ($user) => $user->isAdmin());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Outbound scraping politeness (used by GoogleMapsThrottle job middleware).
        RateLimiter::for('google-maps', fn () => Limit::perMinute(max(1, (int) config('localguides.sync.per_minute', 4))));

        RateLimiter::for('profile-add', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('generations', fn (Request $request) => Limit::perHour(20)->by($request->user()?->id ?: $request->ip()));
    }
}
