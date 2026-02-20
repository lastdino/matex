<?php

declare(strict_types=1);

namespace Lastdino\Matex;

use Illuminate\Support\ServiceProvider;
use Lastdino\Matex\Models\Receiving;
use Lastdino\Matex\Models\ReceivingItem;
use Lastdino\Matex\Models\StockMovement;
use Lastdino\Matex\Observers\ReceivingItemObserver;
use Lastdino\Matex\Observers\ReceivingObserver;
use Lastdino\Matex\Observers\StockMovementObserver;
use Livewire\Livewire;

class MatexServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use underscore in config key to avoid edge cases when accessing via dot notation
        $this->mergeConfigFrom(__DIR__.'/../config/matex.php', 'matex');
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

        // Expose package views under the "matex" namespace
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'matex');

        if (class_exists(\Illuminate\Support\Facades\Blade::class)) {
            \Illuminate\Support\Facades\Blade::anonymousComponentPath(__DIR__.'/../resources/views/flux', 'flux');
        }

        // Publish views so host apps can override
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/matex'),
        ], 'matex-views');

        // Load translations under the "matex" namespace
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'matex');

        $this->publishes([
            __DIR__.'/../config/matex.php' => config_path('matex.php'),
        ], 'matex-config');

        // Publish translations so host apps can override
        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/matex'),
        ], 'matex-lang');

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
            namespace: 'matex',
            viewPath: __DIR__.'/../resources/views/livewire',
        );

        // もし公開されたビューがあれば、そちらを優先するようにLivewireコンポーネントを再登録
        $publishedPath = resource_path('views/vendor/matex/livewire');
        if (is_dir($publishedPath)) {
            $files = array_diff(scandir($publishedPath), ['.', '..']);
            if (count($files) > 0) {
                Livewire::addNamespace('matex', $publishedPath);
            }
        }
    }
}
