<?php

namespace App\Providers;

use App\Services\AuditTrailService;
use App\Services\StorefrontContext;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StorefrontContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentTimezone::set(config('app.business_timezone'));

        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $models): void {
                $model = $models[0] ?? null;

                if ($model instanceof Model) {
                    app(AuditTrailService::class)->record((string) str($eventName)->between('eloquent.', ':'), $model);
                }
            });
        }
    }
}
