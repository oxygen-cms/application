<?php

namespace App\Http;

use App\Http\Middleware\EnableCors;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Oxygen\Auth\Middleware\StartSessionIfNeeded;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

class Kernel extends HttpKernel {

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, string>
     */
    protected $middleware = [
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            \LaravelDoctrine\ORM\Middleware\SubstituteBindings::class,
        ],
        'api' => [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:360,1',
            \LaravelDoctrine\ORM\Middleware\SubstituteBindings::class,
        ],
        'api_auth_unverified' => [
            'api',
            'auth:sanctum'
        ],
        'api_auth' => [
            'api_auth_unverified',
            '2fa.require',
            'verified'
        ],
        'public' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSessionIfNeeded::class,
            AuthenticateSession::class
        ]
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'bindings' => SubstituteBindings::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class
    ];

}
