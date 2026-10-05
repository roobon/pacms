<?php

namespace App\Providers;

use App\Auth\AuthActivitySubscriber;
use App\Cms\Blocks\BlockRegistry;
use App\Cms\Design\DesignTokenService;
use App\Cms\Sources\SourceRegistry;
use App\Models\Media;
use App\Models\News;
use App\Models\Page;
use App\Models\Term;
use App\Models\User;
use App\Services\ActivityLog\ActivityLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(ActivityLogger::class);
        $this->app->singleton(DesignTokenService::class);
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(SourceRegistry::class);
    }

    public function boot(): void
    {
        // Short, stable aliases for polymorphic columns (DATABASE-ARCHITECTURE.md §1).
        Relation::enforceMorphMap([
            'user' => User::class,
            'page' => Page::class,
            'media' => Media::class,
            'term' => Term::class,
            'news' => News::class,
        ]);

        // Catch N+1 queries and silently dropped attributes during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Super Admin holds every ability. Everything else is checked by permission.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Password::defaults(function () {
            $rule = Password::min(config('pacms.security.password_min_length'))->letters()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        $this->configureRateLimiting();

        Paginator::useBootstrapFive();

        Event::subscribe(AuthActivitySubscriber::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));
    }
}
