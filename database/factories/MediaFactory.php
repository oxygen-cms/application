<?php

use Faker\Generator as Faker;
use OxygenModule\Media\Entity\Media;

/**
 * Factory for OxygenModule\Media\Entity\Media
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(Media::class, function (Faker $faker) {
    $slug = $faker->unique()->slug(2);
    return [
        'name' => $faker->sentence(2),
        'slug' => $slug,
        'filename' => md5($slug) . '.jpg',
        'type' => Media::TYPE_IMAGE,
        'author' => $faker->name,
        'caption' => $faker->sentence,
        'description' => $faker->paragraph,
    ];
});

// Document (PDF) variant
$factory->defineAs(Media::class, 'document', function (Faker $faker) {
    $slug = $faker->unique()->slug(2);
    return [
        'name' => $faker->sentence(2),
        'slug' => $slug,
        'filename' => md5($slug) . '.pdf',
        'type' => Media::TYPE_DOCUMENT,
        'author' => $faker->name,
        'caption' => $faker->sentence,
        'description' => $faker->paragraph,
    ];
});

// Audio variant
$factory->defineAs(Media::class, 'audio', function (Faker $faker) {
    $slug = $faker->unique()->slug(2);
    return [
        'name' => $faker->sentence(2),
        'slug' => $slug,
        'filename' => md5($slug) . '.mp3',
        'type' => Media::TYPE_AUDIO,
        'author' => $faker->name,
        'caption' => $faker->sentence,
        'description' => $faker->paragraph,
    ];
});
