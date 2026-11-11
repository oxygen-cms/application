<?php

use Faker\Generator as Faker;
use Illuminate\Support\Facades\Hash;
use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;

/**
 * Factory for Oxygen\Auth\Entity\User
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(User::class, function (Faker $faker, array $attributes) {
    // Users require a Group - create one if not provided
    $group = $attributes['group'] ?? entity(Group::class)->create();

    return [
        'username' => $faker->unique()->regexify('[a-z]{8}[0-9]{2}'),
        'fullName' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => Hash::make('password'),
        'group' => $group,
        'preferences' => ['_factory' => true],
        'verifiedAt' => new DateTime(), // Mark email as verified for tests
    ];
});

// Admin user
$factory->defineAs(User::class, 'admin', function (Faker $faker, array $attributes) {
    $group = $attributes['group'] ?? entity(Group::class, 'admin')->create();

    return [
        'username' => 'admin',
        'fullName' => 'Administrator',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'group' => $group,
        'preferences' => ['_factory' => true],
        'verifiedAt' => new DateTime(), // Mark email as verified for tests
    ];
});
