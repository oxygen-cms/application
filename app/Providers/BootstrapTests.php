<?php

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Oxygen\Preferences\Loader\Database\PreferenceItem;
use Oxygen\Preferences\Loader\PreferenceRepositoryInterface;

class BootstrapTests {
    /**
     * Bootstrap the given application.
     *
     * @param \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app) {
        $preferences = \Mockery::mock(PreferenceRepositoryInterface::class);
        $this->addPreferenceItem($preferences, 'appearance.pages', ["theme" => "theme.standaloneContent"]);
        $app->instance(PreferenceRepositoryInterface::class, $preferences);
    }

    public function addPreferenceItem($preferences, $key, $value) {
        $item = new PreferenceItem();
        $item->setPreferences($value);
        $preferences->shouldReceive('findByKey')->with($key)->andReturn($item);
    }
}
