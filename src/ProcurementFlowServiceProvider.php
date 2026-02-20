<?php

declare(strict_types=1);

namespace Lastdino\ProcurementFlow;

use Illuminate\Support\ServiceProvider;
use Lastdino\ProcurementFlow\Models\Receiving;
use Lastdino\ProcurementFlow\Models\ReceivingItem;
use Lastdino\ProcurementFlow\Models\StockMovement;
use Lastdino\ProcurementFlow\Observers\ReceivingItemObserver;
use Lastdino\ProcurementFlow\Observers\ReceivingObserver;
use Lastdino\ProcurementFlow\Observers\StockMovementObserver;
use Livewire\Livewire;

class ProcurementFlowServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use underscore in config key to avoid edge cases when accessing via dot notation
        $this->mergeConfigFrom(__DIR__.'/../config/procurement-flow.php', 'procurement_flow');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Web routes (Volt UI) and views namespace
        $webRoutes = __DIR__.'/../routes/web.php';
        if (file_exists($webRoutes)) {
            $this->loadRoutesFrom($webRoutes);
        }

        $apiRoutes = __DIR__.'/../routes/api.php';
        if (file_exists($apiRoutes)) {
            $this->loadRoutesFrom($apiRoutes);
        }

        // Expose package views under the "procflow" namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'procflow');

        if (class_exists(\Illuminate\Support\Facades\Blade::class)) {
            \Illuminate\Support\Facades\Blade::anonymousComponentPath(__DIR__.'/../resources/views/flux', 'flux');
        }

        // Publish views so host apps can override
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/procflow'),
        ], 'procurement-flow-views');

        // Load translations under the "procflow" namespace
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'procflow');

        $this->publishes([
            __DIR__.'/../config/procurement-flow.php' => config_path('procurement-flow.php'),
        ], 'procurement-flow-config');

        // Publish translations so host apps can override
        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/procflow'),
        ], 'procurement-flow-lang');

        $this->loadLivewireComponents();

        // Register model observers
        Receiving::observe(ReceivingObserver::class);
        ReceivingItem::observe(ReceivingItemObserver::class);
        StockMovement::observe(StockMovementObserver::class);
    }

    // custom methods for livewire components
    protected function loadLivewireComponents(): void
    {
        Livewire::addNamespace(
            namespace: 'procflow',
            viewPath: __DIR__.'/../resources/views/livewire',
        );

        // もし公開されたビューがあれば、そちらを優先するようにLivewireコンポーネントを再登録
        $publishedPath = resource_path('views/vendor/procflow/livewire');
        if (is_dir($publishedPath)) {
            $files = array_diff(scandir($publishedPath), ['.', '..']);
            if (count($files) > 0) {
                Livewire::addNamespace('procflow', $publishedPath);
            }
        }
    }
}
