<?php

namespace App\Providers;

use App\Domain\Network\MikrotikSubscriberReader;
use App\Domain\Network\SubscriberReader;
use App\Domain\Payments\NullPaymentGateway;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Radius\RadiusTransport;
use App\Domain\Radius\UdpRadiusTransport;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Policies\CustomerPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ServicePolicy;
use App\Support\RequestContext;
use App\Support\Tenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);
        $this->app->singleton(RequestContext::class);
        $this->app->bind(RadiusTransport::class, UdpRadiusTransport::class);
        $this->app->bind(SubscriberReader::class, MikrotikSubscriberReader::class);
        $this->app->bind(PaymentGateway::class, NullPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Inertia::encryptHistory();
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower($request->string('email')->toString());

            return [
                Limit::perMinute(5)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('account:'.$email),
            ];
        });
    }
}
