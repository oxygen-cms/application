<?php

use Faker\Generator as Faker;
use Oxygen\Data\Behaviour\Publishable;
use OxygenModule\Pages\Entity\Page;

/**
 * Factory for OxygenModule\Pages\Entity\Page
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(Page::class, function (Faker $faker) {
    return [
        'slugPart' => $faker->unique()->slug(2),
        'title' => $faker->sentence(3),
        'author' => $faker->name,
        'description' => $faker->paragraph,
        'tags' => implode(', ', $faker->words(3)),
        'meta' => null,
        'content' => '<h1>Test Page</h1><p>' . $faker->paragraph . '</p>',
        'richContent' => null,
        'options' => '{}', // TEXT column expecting JSON string, not array
        'stage' => Publishable::STAGE_DRAFT,
    ];
});

// Published page
$factory->defineAs(Page::class, 'published', function (Faker $faker) {
    return [
        'slugPart' => $faker->unique()->slug(2),
        'title' => $faker->sentence(3),
        'author' => $faker->name,
        'description' => $faker->paragraph,
        'tags' => implode(', ', $faker->words(3)),
        'meta' => null,
        'content' => '<h1>Test Page</h1><p>' . $faker->paragraph . '</p>',
        'richContent' => null,
        'options' => '{}', // TEXT column expecting JSON string
        'stage' => Page::STAGE_PUBLISHED,
    ];
});

// Home page (root)
$factory->defineAs(Page::class, 'home', function (Faker $faker) {
    return [
        'slugPart' => '/',
        'title' => 'Home',
        'author' => null,
        'description' => 'Welcome to our website',
        'tags' => 'home, main',
        'meta' => null,
        'content' => '<h1>Welcome</h1>',
        'richContent' => null,
        'options' => '{}', // TEXT column expecting JSON string
        'stage' => Page::STAGE_PUBLISHED,
    ];
});
