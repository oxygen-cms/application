<?php

use Faker\Generator as Faker;
use Oxygen\Data\Behaviour\Publishable;
use OxygenModule\Pages\Entity\Partial;

/**
 * Factory for OxygenModule\Pages\Entity\Partial
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(Partial::class, function (Faker $faker) {
    // Key must be alpha_dot format (letters, numbers, and dots only)
    return [
        'key' => 'partial.' . $faker->unique()->word . '.' . $faker->randomNumber(4),
        'title' => $faker->sentence(3),
        'author' => $faker->name,
        'content' => '<div>' . $faker->paragraph . '</div>',
        'stage' => Publishable::STAGE_DRAFT,
        'headVersion' => null, // Required for typed property initialization
    ];
});

// Published partial
$factory->defineAs(Partial::class, 'published', function (Faker $faker) {
    return [
        'key' => 'partial.' . $faker->unique()->word . '.' . $faker->randomNumber(4),
        'title' => $faker->sentence(3),
        'author' => $faker->name,
        'content' => '<div>' . $faker->paragraph . '</div>',
        'stage' => Partial::STAGE_PUBLISHED,
        'headVersion' => null, // Required for typed property initialization
    ];
});
