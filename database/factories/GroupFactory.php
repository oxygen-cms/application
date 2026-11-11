<?php

use Faker\Generator as Faker;
use Oxygen\Auth\Entity\Group;

/**
 * Factory for Oxygen\Auth\Entity\Group
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(Group::class, function (Faker $faker) {
    return [
        'name' => $faker->unique()->jobTitle,
        'nickname' => $faker->unique()->slug(2),
        'icon' => 'users',
        'description' => $faker->sentence,
        'permissions' => [],
        'preferences' => ['_factory' => true], // Non-empty to pass validation
        'parent' => null, // Must explicitly set typed nullable properties
        'createdAt' => new DateTime(),
        'updatedAt' => new DateTime(),
    ];
});

// Admin group with all permissions
$factory->defineAs(Group::class, 'admin', function (Faker $faker) {
    return [
        'name' => 'Administrators',
        'nickname' => 'admin',
        'icon' => 'user-shield',
        'description' => 'Full administrative access',
        'permissions' => [
            'pages' => ['getList' => true, 'getInfo' => true, 'postCreate' => true, 'putUpdate' => true, 'deleteDelete' => true],
            'auth' => ['getList' => true, 'getInfo' => true, 'postCreate' => true, 'putUpdate' => true, 'deleteDelete' => true],
        ],
        'preferences' => ['_factory' => true],
        'parent' => null, // Must explicitly set typed nullable properties
        'createdAt' => new DateTime(),
        'updatedAt' => new DateTime(),
    ];
});
