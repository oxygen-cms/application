<?php

namespace App\Providers;

use App\Http\Controllers\AdminController;
use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use OxygenModule\Pages\Controller\PagesController;

class RouteServiceProvider extends ServiceProvider {
    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/oxygen';

    /**
     * Define your route model bindings, pattern filters, etc.
     * @return void
     */
    public function boot() {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @param Router $router
     */
    public function map(Router $router): void {
        $router->prefix('/oxygen')
            ->middleware(['web'])
            ->group(function(Router $router) {
                // TODO: this should be redundant once all non-API routes are gone from Oxygen
                $router->get('/auth/2fa-setup', [AdminController::class, 'getView'])
                    ->name('2fa.notice');
                $router->get('/dashboard', [AdminController::class, 'getView'])
                    ->name('dashboard.main');
                $router->get('/auth/reset-password', [AdminController::class, 'getView'])
                    ->name('password.reset');
                $router->get('/auth/needs-verified-email', [AdminController::class, 'getView'])
                    ->name('verification.notice');
                $router->get('/auth/login', [AdminController::class, 'getView'])
                    ->name('login');
                $router->get('/{path?}', [AdminController::class, 'getView'])
                    ->where('path', '([a-zA-Z\.0-9/ \-]+)')
                    ->name('spa');
            });

        // Page catch-all route — must be registered LAST
        $router->get('/{slug?}', [PagesController::class, 'getView'])
            ->where('slug', '([a-z0-9/\-]+)')
            ->middleware(['public']);
    }
}
