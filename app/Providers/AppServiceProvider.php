<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Theme;
use Navigation;
use Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Theme::make([
            'key' => 'myTheme',
            'name' => 'My Theme',
            'image' => 'https://octobercms.com/uploads/public/53f/0b1/e89/thumb_654_828x0_0_0_auto.jpg',
            'provides' => [
                'appearance.pages' => [
                    'theme' => 'oxygen/mod-pages::pages.view'
                ]
            ]
        ]);

        Theme::make([
            'key' => 'yourTheme',
            'name' => 'Your Theme',
            'provides' => [
                'appearance.pages' => [
                    'theme' => 'oxygen/mod-pages::pages.view'
                ]
            ]
        ]);

        Navigation::order(Navigation::PRIMARY, [
            'dashboard.main', '|', 'pages.getList', 'partials.getList', 'media.getList', 'upcomingEvents.getList', /*, 'emails.getList',*/ 'users.getList', 'groups.getList'
        ]);

        Navigation::order(Navigation::SECONDARY, function() {
            return [
                'System' => ['marketplace.getHome', 'preferences.getView', 'security.getList', 'importExport.getList'],
                Auth::user()->getFullName() => ['auth.getInfo', 'auth.getPreferences', 'auth.postLogout']
            ];
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
