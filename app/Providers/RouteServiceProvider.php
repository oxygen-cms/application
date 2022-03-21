<?php

namespace App\Providers;

use App\Http\Controllers\AdminController;
use Illuminate\Routing\Router;
use Oxygen\Core\Contracts\Routing\BlueprintRegistrar;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Oxygen\Core\Blueprint\BlueprintManager;

class RouteServiceProvider extends ServiceProvider {
    /**
     * This namespace is applied to the controller routes in your routes file.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

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
     * @param BlueprintRegistrar $registrar
     * @param BlueprintManager $manager
     */
    public function map(Router $router, BlueprintRegistrar $registrar, BlueprintManager $manager) {
        $manager->registerRoutes($registrar);

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

        $manager->registerFinalRoutes($registrar);
    }
}
