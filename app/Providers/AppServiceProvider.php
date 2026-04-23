<?php

namespace App\Providers;

use App\Policies\AppPolicy;
use App\Services\FirebaseStorageService;
use App\Services\GeolocationService;
use App\Services\TimeRecordService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeolocationService::class);

        $this->app->singleton(FirebaseStorageService::class);

        $this->app->singleton(TimeRecordService::class, function ($app) {
            return new TimeRecordService(
                $app->make(GeolocationService::class),
                $app->make(FirebaseStorageService::class),
            );
        });
    }

    public function boot(): void
    {
        Gate::define('manage-companies', [AppPolicy::class, 'manageCompanies']);
        Gate::define('manage-employees', [AppPolicy::class, 'manageEmployees']);
        Gate::define('approve-edit-requests', [AppPolicy::class, 'approveEditRequests']);
    }
}
