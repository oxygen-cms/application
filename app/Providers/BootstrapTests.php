<?php

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Mockery\MockInterface;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Oxygen\Core\Preferences\Loader\PreferenceRepositoryInterface;

class BootstrapTests {
    /**
     * Bootstrap the given application.
     *
     * @param Application $app
     * @return void
     */
    public function bootstrap(Application $app): void {
        $preferences = \Mockery::mock(PreferenceRepositoryInterface::class);
        $this->addPreferenceItem($preferences, 'appearance.pages', ["theme" => "theme.standaloneContent"]);
        $app->instance(PreferenceRepositoryInterface::class, $preferences);
    }

    public function addPreferenceItem(MockInterface $preferences, string $key, mixed $value): void {
        $item = new PreferenceItem();
        $item->setPreferences($value);
        $e = $preferences->shouldReceive('findByKey');
        $e->with($key)->andReturn($item);
    }
}
