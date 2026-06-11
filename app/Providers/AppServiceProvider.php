<?php

namespace App\Providers;

use App\Models\BallByBall;
use App\Observers\BallByBallObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Cricket\FantasyPointsService::class);
        $this->app->singleton(\App\Services\Cricket\BallByBallStatsService::class);
        $this->app->singleton(\App\Services\Cricket\PointsCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Trigger fantasy points recalculation on every ball entry/edit/delete
        BallByBall::observe(BallByBallObserver::class);
    }
}