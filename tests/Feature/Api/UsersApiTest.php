<?php

namespace Tests\Feature\Api;

use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Tests\IntegrationTestCase;

/**
 * Feature tests for the Users API endpoints.
 *
 * Tests CRUD operations on /oxygen/api/users
 */
class UsersApiTest extends IntegrationTestCase
{
    use SoftDeleteableCrudApiTestTrait;

    protected User $user;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable 2FA middleware for tests
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        // Create a group with user management permissions
        $this->group = entity(Group::class)->create([
            'name' => 'Super Admin',
            'nickname' => 'super-admin',
            'permissions' => [
                'users' => [
                    'getList' => true,
                    'getInfo' => true,
                    'postCreate' => true,
                    'putUpdate' => true,
                    'deleteDelete' => true,
                    'deleteForce' => true,
                    'postRestore' => true,
                    'postImpersonate' => true,
                ],
            ],
        ]);

        // Create a user in that group
        $this->user = entity(User::class)->create([
            'group' => $this->group,
        ]);
    }

    // ========================================================================
    // TRAIT CONFIGURATION (for SoftDeleteableCrudApiTestTrait)
    // ========================================================================

    protected function getEntityClass(): string
    {
        return User::class;
    }

    protected function getApiEndpoint(): string
    {
        return '/oxygen/api/users';
    }

    protected function getCreateFactoryOverrides(): array
    {
        return [
            'fullName' => 'Test User',
            'email' => 'testuser@example.com',
            'group' => $this->group,
        ];
    }

    // ========================================================================
    // BASIC CRUD TESTS
    // ========================================================================

    public function test_can_list_users(): void
    {
        $this->actingAs($this->user);

        // Create additional users (one already exists from setUp)
        entity(User::class)->create(['fullName' => 'Alice Smith', 'email' => 'alice@example.com', 'group' => $this->group]);
        entity(User::class)->create(['fullName' => 'Bob Jones', 'email' => 'bob@example.com', 'group' => $this->group]);

        $response = $this->getJson('/oxygen/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'totalItems',
                'itemsPerPage',
                'status',
            ])
            // 3 users total: setUp user + Alice + Bob
            ->assertJsonPath('totalItems', 3);
    }

    public function test_can_get_single_user(): void
    {
        $this->actingAs($this->user);

        $otherUser = entity(User::class)->create([
            'fullName' => 'Test User',
            'email' => 'test@example.com',
            'group' => $this->group,
        ]);

        $response = $this->getJson("/oxygen/api/users/{$otherUser->getId()}");

        $response->assertStatus(200)
            ->assertJsonPath('item.fullName', 'Test User')
            ->assertJsonPath('item.email', 'test@example.com');
    }

    public function test_can_update_user(): void
    {
        $this->actingAs($this->user);

        $otherUser = entity(User::class)->create([
            'fullName' => 'Original Name',
            'email' => 'original@example.com',
            'group' => $this->group,
        ]);

        $response = $this->putJson("/oxygen/api/users/{$otherUser->getId()}", [
            'fullName' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('item.fullName', 'Updated Name');

        // Verify update persisted
        $this->getJson("/oxygen/api/users/{$otherUser->getId()}")
            ->assertStatus(200)
            ->assertJsonPath('item.fullName', 'Updated Name');
    }

    public function test_can_delete_user(): void
    {
        $this->actingAs($this->user);

        $otherUser = entity(User::class)->create([
            'fullName' => 'To Delete',
            'email' => 'delete@example.com',
            'group' => $this->group,
        ]);
        $userId = $otherUser->getId();

        $response = $this->deleteJson("/oxygen/api/users/{$userId}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    // ========================================================================
    // AUTH TESTS
    // ========================================================================

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $response = $this->get('/oxygen/api/users');

        $response->assertStatus(302);
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        // Seed the auth preferences
        entity(PreferenceItem::class, 'modules.auth')->create();

        // Create a user without users permissions
        $limitedGroup = entity(Group::class)->create([
            'name' => 'Limited',
            'nickname' => 'limited-' . uniqid(),
            'permissions' => [],
        ]);

        $limitedUser = entity(User::class)->create([
            'group' => $limitedGroup,
        ]);

        $response = $this->actingAs($limitedUser)
            ->getJson('/oxygen/api/users');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'failed');
    }

    // ========================================================================
    // FULL NAME UPDATE TEST
    // ========================================================================

    public function test_can_update_user_full_name(): void
    {
        $this->actingAs($this->user);

        $otherUser = entity(User::class)->create([
            'fullName' => 'Old Name',
            'email' => 'name@example.com',
            'group' => $this->group,
        ]);

        $response = $this->putJson("/oxygen/api/users/{$otherUser->getId()}/fullName", [
            'fullName' => 'New Full Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('item.fullName', 'New Full Name');
    }

    // ========================================================================
    // BASIC USER INFO TEST
    // ========================================================================

    public function test_can_get_basic_user_info(): void
    {
        // Seed auth preferences (required by some middleware)
        entity(PreferenceItem::class, 'modules.auth')->create();

        $this->actingAs($this->user);

        $otherUser = entity(User::class)->create([
            'fullName' => 'Basic Info User',
            'email' => 'basic@example.com',
            'group' => $this->group,
        ]);

        $response = $this->getJson("/oxygen/api/users/{$otherUser->getId()}/basic");

        // This endpoint returns public user info
        $response->assertStatus(200);
    }

    // ========================================================================
    // IMPERSONATION TESTS
    // ========================================================================

    public function test_can_impersonate_another_user(): void
    {
        $this->actingAs($this->user);

        $otherUser = entity(User::class)->create([
            'fullName' => 'Impersonate Target',
            'email' => 'target@example.com',
            'group' => $this->group,
        ]);

        $response = $this->postJson("/oxygen/api/users/{$otherUser->getId()}/impersonate");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('impersonating', true);
    }

    public function test_cannot_impersonate_self(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/oxygen/api/users/{$this->user->getId()}/impersonate");

        $response->assertStatus(400)
            ->assertJsonPath('status', 'failed');
    }

    public function test_can_stop_impersonating(): void
    {
        $this->actingAs($this->user);

        // First start impersonating
        $otherUser = entity(User::class)->create([
            'fullName' => 'Impersonate Target',
            'email' => 'stoptarget@example.com',
            'group' => $this->group,
        ]);
        $this->postJson("/oxygen/api/users/{$otherUser->getId()}/impersonate");

        // Now stop impersonating
        $response = $this->postJson('/oxygen/api/users/stop-impersonating');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('impersonating', false);
    }

    // ========================================================================
    // SEARCH TEST
    // ========================================================================

    public function test_can_search_users(): void
    {
        $this->actingAs($this->user);

        entity(User::class)->create(['fullName' => 'Alice Wonderland', 'email' => 'alice1@example.com', 'group' => $this->group]);
        entity(User::class)->create(['fullName' => 'Bob Builder', 'email' => 'bob1@example.com', 'group' => $this->group]);
        entity(User::class)->create(['fullName' => 'Carol Wonderland', 'email' => 'carol@example.com', 'group' => $this->group]);

        // Search for users with "Wonderland" in name
        $response = $this->getJson('/oxygen/api/users?q=Wonderland');

        $response->assertStatus(200)
            ->assertJsonPath('totalItems', 2);
    }
}
