<?php

namespace Tests\Feature\Api;

use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;

/**
 * Trait providing common CRUD API test methods.
 *
 * Concrete test classes using this trait must implement the configuration methods
 * to specify entity class, API endpoint, permissions, and test data.
 *
 * Usage:
 * 1. Use this trait in your test class
 * 2. Implement all abstract methods to provide configuration
 * 3. Call setupCrudTest() in your setUp() method
 *
 * @see PagesApiTest for an example that doesn't use this trait (more verbose)
 * @see GroupsApiTest for an example
 */
trait CrudApiTestTrait
{
    protected User $user;
    protected Group $group;

    /**
     * Returns the entity class name for this API.
     * e.g., Group::class
     */
    abstract protected function getEntityClass(): string;

    /**
     * Returns the API endpoint base path.
     * e.g., '/oxygen/api/groups'
     */
    abstract protected function getApiEndpoint(): string;

    /**
     * Returns the permission key for this resource.
     * e.g., 'groups' (used in group permissions array)
     */
    abstract protected function getPermissionKey(): string;

    /**
     * Returns factory overrides for creating a test entity with known values.
     * e.g., ['name' => 'Test Group']
     */
    abstract protected function getCreateFactoryOverrides(): array;

    /**
     * Returns the request payload for creating a new entity via API.
     * e.g., ['name' => 'New Group']
     */
    abstract protected function getCreateRequestPayload(): array;

    /**
     * Returns the request payload for updating an entity via API.
     * e.g., ['name' => 'Updated Group']
     */
    abstract protected function getUpdateRequestPayload(): array;

    /**
     * Returns the field name used to identify the entity in responses.
     * e.g., 'name' - used to verify create/update operations
     */
    abstract protected function getIdentifyingField(): string;

    /**
     * Returns the expected value of the identifying field after creation.
     * e.g., 'New Group'
     */
    abstract protected function getExpectedCreateValue(): string;

    /**
     * Returns the expected value of the identifying field after update.
     * e.g., 'Updated Group'
     */
    abstract protected function getExpectedUpdateValue(): string;

    /**
     * Returns additional permissions beyond basic CRUD.
     * Override this method to add versioning, publishing, restore permissions, etc.
     * e.g., ['postRestore' => true, 'listVersions' => true]
     */
    protected function getAdditionalPermissions(): array
    {
        return [];
    }

    /**
     * Returns the number of entities that already exist before tests create their own.
     * Override this when the entity under test is also created during setup
     * (e.g., Groups - a group is created for the test user).
     */
    protected function getBaselineItemCount(): int
    {
        return 0;
    }

    /**
     * Returns the number of baseline entities that match the "Test" search query.
     * Override this when baseline entities have names matching "Test".
     */
    protected function getBaselineSearchMatchCount(): int
    {
        return 0;
    }

    /**
     * Set up the CRUD test with authenticated user and permissions.
     * Call this in your setUp() method.
     */
    protected function setupCrudTest(): void
    {
        // Disable 2FA middleware for tests
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        // Base CRUD permissions
        $permissions = [
            'getList' => true,
            'getInfo' => true,
            'postCreate' => true,
            'putUpdate' => true,
            'deleteDelete' => true,
        ];

        // Merge additional permissions (versioning, publishing, etc.)
        $permissions = array_merge($permissions, $this->getAdditionalPermissions());

        // Create a group with full permissions for this resource
        $this->group = entity(Group::class)->create([
            'name' => 'Test Admin',
            'nickname' => 'test-admin-' . uniqid(),
            'permissions' => [
                $this->getPermissionKey() => $permissions,
            ],
        ]);

        // Create a user in that group
        $this->user = entity(User::class)->create([
            'group' => $this->group,
        ]);
    }

    /**
     * Create a test entity using the factory with optional overrides.
     */
    protected function createTestEntity(array $overrides = []): object
    {
        $this->actingAs($this->user);
        return entity($this->getEntityClass())->create(
            array_merge($this->getCreateFactoryOverrides(), $overrides)
        );
    }

    // ========================================================================
    // COMMON CRUD TEST METHODS
    // ========================================================================

    public function test_can_list_items(): void
    {
        $this->actingAs($this->user);

        // Create some entities
        entity($this->getEntityClass(), 3)->create();

        $response = $this->getJson($this->getApiEndpoint());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items',
                'totalItems',
                'itemsPerPage',
                'status',
            ])
            ->assertJsonPath('totalItems', 3 + $this->getBaselineItemCount());
    }

    public function test_can_get_single_item(): void
    {
        $entity = $this->createTestEntity();
        $field = $this->getIdentifyingField();

        $response = $this->getJson("{$this->getApiEndpoint()}/{$entity->getId()}");

        $response->assertStatus(200)
            ->assertJsonPath("item.{$field}", $this->getCreateFactoryOverrides()[$field]);
    }

    public function test_can_create_item(): void
    {
        $this->actingAs($this->user);
        $field = $this->getIdentifyingField();

        $response = $this->postJson($this->getApiEndpoint(), $this->getCreateRequestPayload());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath("item.{$field}", $this->getExpectedCreateValue());

        // Verify entity can be retrieved via API
        $entityId = $response->json('item.id');
        $this->getJson("{$this->getApiEndpoint()}/{$entityId}")
            ->assertStatus(200)
            ->assertJsonPath("item.{$field}", $this->getExpectedCreateValue());
    }

    public function test_can_update_item(): void
    {
        $entity = $this->createTestEntity();
        $field = $this->getIdentifyingField();

        $response = $this->putJson(
            "{$this->getApiEndpoint()}/{$entity->getId()}",
            $this->getUpdateRequestPayload()
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath("item.{$field}", $this->getExpectedUpdateValue());

        // Verify update persisted via API GET
        $this->getJson("{$this->getApiEndpoint()}/{$entity->getId()}")
            ->assertStatus(200)
            ->assertJsonPath("item.{$field}", $this->getExpectedUpdateValue());
    }

    public function test_can_delete_item(): void
    {
        $entity = $this->createTestEntity();
        $entityId = $entity->getId();

        // Verify entity exists before deletion
        $this->getJson($this->getApiEndpoint())
            ->assertStatus(200)
            ->assertJsonPath('totalItems', 1 + $this->getBaselineItemCount());

        $response = $this->deleteJson("{$this->getApiEndpoint()}/{$entityId}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify entity no longer appears in list (soft-deleted or hard-deleted)
        $this->getJson($this->getApiEndpoint())
            ->assertStatus(200)
            ->assertJsonPath('totalItems', 0 + $this->getBaselineItemCount());
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $response = $this->get($this->getApiEndpoint());

        // Should redirect to login
        $response->assertStatus(302);
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        // Seed the auth preferences (required for permission denied redirect)
        entity(PreferenceItem::class, 'modules.auth')->create();

        // Create a user without permissions
        $limitedGroup = entity(Group::class)->create([
            'name' => 'Limited',
            'nickname' => 'limited-' . uniqid(),
            'permissions' => [],
        ]);

        $limitedUser = entity(User::class)->create([
            'group' => $limitedGroup,
        ]);

        $response = $this->actingAs($limitedUser)
            ->getJson($this->getApiEndpoint());

        // Should get forbidden response with error status
        $response->assertStatus(200)
            ->assertJsonPath('status', 'failed');
    }

    public function test_can_search_items(): void
    {
        $this->actingAs($this->user);
        $field = $this->getIdentifyingField();

        // Create entities with known values
        entity($this->getEntityClass())->create([$field => 'Alpha Test']);
        entity($this->getEntityClass())->create([$field => 'Beta Search']);
        entity($this->getEntityClass())->create([$field => 'Gamma Test']);

        // Search should find matching items
        $response = $this->getJson("{$this->getApiEndpoint()}?q=Test");

        $response->assertStatus(200)
            ->assertJsonPath('totalItems', 2 + $this->getBaselineSearchMatchCount());
    }
}
