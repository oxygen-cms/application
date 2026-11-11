<?php

use Faker\Generator as Faker;
use OxygenModule\Media\Entity\MediaDirectory;

/**
 * Factory for OxygenModule\Media\Entity\MediaDirectory
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(MediaDirectory::class, function (Faker $faker) {
    $name = $faker->unique()->words(2, true);
    return [
        'name' => $name,
        'slug' => \Illuminate\Support\Str::slug($name),
    ];
});
