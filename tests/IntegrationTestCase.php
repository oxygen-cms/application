<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Oxygen\Auth\Entity\User;

/**
 * Base test case for integration/feature tests that interact with the database.
 *
 * Uses RefreshDoctrineDatabase trait to reset database between tests.
 * Uses Doctrine entity factories for creating test data.
 *
 * @see https://laravel.com/docs/10.x/http-tests
 * @see https://laravel-doctrine-orm-official.readthedocs.io/en/latest/testing.html
 */
abstract class IntegrationTestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDoctrineDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Refresh the Doctrine database (creates schema and starts transaction)
        $this->refreshDoctrineDatabase();

        // Entity factories are automatically loaded from database/factories
        // via the entity() helper function from laravel-doctrine/orm
    }

    /**
     * Authenticate as a user for the request.
     * Uses Laravel's built-in actingAs() which sets session auth.
     *
     * @see https://laravel.com/docs/10.x/http-tests#session-and-authentication
     *
     * @param User|null $user The user to authenticate as (creates one if null)
     * @param string $guard The authentication guard to use
     * @return $this
     */
    protected function actingAsUser(User $user = null, string $guard = 'web'): self
    {
        $user = $user ?? entity(User::class)->create();
        return $this->actingAs($user, $guard);
    }
}
