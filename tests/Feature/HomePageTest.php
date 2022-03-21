<?php

namespace Tests\Feature;

use Oxygen\Preferences\Loader\Database\PreferenceItem;
use OxygenModule\Pages\Entity\Page;
use OxygenModule\Pages\Repository\PageRepositoryInterface;
use Tests\TestCase;
use Oxygen\Preferences\Loader\Database\DoctrinePreferenceRepository;

class HomePageTest extends TestCase {
    /**
     * A basic functional test example.
     *
     * @return void
     * @throws \Exception
     */
    public function testBasicExample() {
        $pages = \Mockery::mock(PageRepositoryInterface::class);
        $page = new Page();
        $page->setTitle('Example Title');
        $page->setContent('Hello World');
        $page->setCreatedAt(new \DateTime());
        $page->setUpdatedAt(new \DateTime());
        $pages->shouldReceive('findBySlug')->with('/')->andReturn($page);
        $this->app->instance(PageRepositoryInterface::class, $pages);

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

        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
