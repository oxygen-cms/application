<?php

use Faker\Generator as Faker;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;

/**
 * Factory for Oxygen\Core\Preferences\Loader\Database\PreferenceItem
 *
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html#entity-factories
 */
$factory->define(PreferenceItem::class, function (Faker $faker) {
    return [
        'key' => $faker->unique()->slug(2),
        'contents' => [],
    ];
});

// Auth module preferences (required for permissions redirect)
$factory->defineAs(PreferenceItem::class, 'modules.auth', function (Faker $faker) {
    return [
        'key' => 'modules.auth',
        'contents' => [
            'dashboard' => 'pages.getListApi', // Default redirect route
            'home' => 'pages.getListApi',
            'notifyWhenNewDevice' => false,
            'loginLogExpiry' => 30,
        ],
    ];
});

