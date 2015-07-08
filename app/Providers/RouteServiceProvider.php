<?php

namespace App\Providers;

use Illuminate\Routing\Router;
use Oxygen\Core\Contracts\CoreConfiguration;
use Oxygen\Core\Contracts\Routing\BlueprintRegistrar;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Oxygen\Core\Blueprint\BlueprintManager;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to the controller routes in your routes file.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function boot(Router $router) {
        parent::boot($router);
    }

    /**
     * Define the routes for the application.
     *
     * @param  \Illuminate\Routing\Router                                                  $router
     * @param \Oxygen\Core\Contracts\Routing\BlueprintRegistrar                            $registrar
     * @param \Oxygen\Core\Blueprint\BlueprintManager                                      $manager
     */
    public function map(Router $router, BlueprintRegistrar $registrar, BlueprintManager $manager) {
        $router->group(['namespace' => $this->namespace], function ($router) {
            require app_path('Http/routes.php');
        });

        $manager->registerRoutes($registrar);
    }
}
