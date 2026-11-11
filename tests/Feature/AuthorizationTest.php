<?php

namespace Tests\Feature;

use Oxygen\Auth\Repository\DoctrineUserRepository;
use Oxygen\Core\Preferences\Loader\Database\DoctrinePreferenceRepository;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Tests\TestCase;

class AuthorizationTest extends TestCase {

    public function testLoginRedirect(): void {
        $pagesPreferences = \Mockery::mock(DoctrinePreferenceRepository::class);
        $theme = new PreferenceItem();
        $theme->setPreferences([
            'theme' => "default"
        ]);

        $pages = new PreferenceItem();
        $pages->setPreferences([
            "theme" => "oxygen/mod-pages::pages.view",
            "contentView" => "oxygen/mod-pages::pages.view"
        ]);
        $pagesPreferences->shouldReceive('findByKey')->with('appearance.pages')->andReturn($pages);
        $pagesPreferences->shouldReceive('findByKey')->with('appearance.themes')->andReturn($theme);
        $this->app->instance(DoctrinePreferenceRepository::class, $pagesPreferences);

        $response = $this->get('/oxygen/api/pages');
        $response->assertStatus(302);
        $response->assertRedirectToRoute('login', ['location' => 'http://localhost']);
    }

}