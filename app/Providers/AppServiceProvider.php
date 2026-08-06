<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RevenueCenter;
use App\Models\StockRelease;
use App\Policies\DepartmentPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\RevenueCenterPolicy;
use App\Policies\StockReleasePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

// 👇 1. ADD THESE THREE IMPORTS FOR RATE LIMITING
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        PurchaseRequest::class => PurchaseRequestPolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        InventoryItem::class => InventoryPolicy::class,
        RevenueCenter::class => RevenueCenterPolicy::class,
        Department::class => DepartmentPolicy::class,
        StockRelease::class => StockReleasePolicy::class,
    ];

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
        // Your existing policy registration
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 👇 2. CALL THE NEW RATE LIMITING METHOD HERE
        $this->configureRateLimiting();
    }

    /**
     * 👇 3. ADD THIS NEW METHOD TO CONFIGURE RATE LIMITS
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Global API limiter (Good practice to have a fallback for all API routes)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Custom limiter specifically for the login route
        RateLimiter::for('login', function (Request $request) {
            // Combines email and IP to prevent blocking the whole office network 
            // if multiple people share an IP but use different emails.
            $throttleKey = strtolower($request->input('email')) . '|' . $request->ip();

            // Allow 5 attempts per minute
            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}