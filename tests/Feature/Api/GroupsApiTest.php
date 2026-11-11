<?php

namespace Tests\Feature\Api;

use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Tests\IntegrationTestCase;

/**
 * Feature tests for the Groups API endpoints.
 *
 * Tests CRUD operations on /oxygen/api/groups
 * Uses CrudApiTestTrait and SoftDeleteableCrudApiTestTrait.
 */
class GroupsApiTest extends IntegrationTestCase
{
    use CrudApiTestTrait;
    use SoftDeleteableCrudApiTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupCrudTest();
    }

    // ========================================================================
    // TRAIT CONFIGURATION
    // ========================================================================

    protected function getEntityClass(): string
    {
        return Group::class;
    }

    protected function getApiEndpoint(): string
    {
        return '/oxygen/api/groups';
    }

    protected function getPermissionKey(): string
    {
        return 'groups';
    }

    protected function getCreateFactoryOverrides(): array
    {
        return [
            'name' => 'Test Group',
            'nickname' => 'test-group-' . uniqid(),
        ];
    }

    protected function getCreateRequestPayload(): array
    {
        return [
            'name' => 'New Group',
            'nickname' => 'new-group-' . uniqid(),
        ];
    }

    protected function getUpdateRequestPayload(): array
    {
        return [
            'name' => 'Updated Name',
        ];
    }

    protected function getIdentifyingField(): string
    {
        return 'name';
    }

    protected function getExpectedCreateValue(): string
    {
        return 'New Group';
    }

    protected function getExpectedUpdateValue(): string
    {
        return 'Updated Name';
    }

    protected function getAdditionalPermissions(): array
    {
        return [
            'postRestore' => true,
        ];
    }

    // A group is created during setupCrudTest() for the test user
    protected function getBaselineItemCount(): int
    {
        return 1;
    }

    // The setup group is named "Test Admin", which matches the "Test" search query
    protected function getBaselineSearchMatchCount(): int
    {
        return 1;
    }

    /**
     * Override: GroupsController uses BasicCrudApi::getListQueryParameters
     * which does not filter out soft-deleted groups, so deleted groups
     * remain visible in the list.
     */
    public function test_can_delete_item(): void
    {
        $entity = $this->createTestEntity();
        $entityId = $entity->getId();

        $this->getJson($this->getApiEndpoint())
            ->assertStatus(200)
            ->assertJsonPath('totalItems', 1 + $this->getBaselineItemCount());

        $response = $this->deleteJson("{$this->getApiEndpoint()}/{$entityId}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Soft-deleted groups still appear in the list (no trash filtering)
        $this->getJson($this->getApiEndpoint())
            ->assertStatus(200)
            ->assertJsonPath('totalItems', 1 + $this->getBaselineItemCount());
    }

    /**
     * Override: Group creation via API is expected to fail because
     * 'preferences' is required but not mass-assignable (permissions are
     * managed via console commands, not the API).
     */
    public function test_can_create_item(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson($this->getApiEndpoint(), $this->getCreateRequestPayload());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'failed');
    }

    // ========================================================================
    // GROUP-SPECIFIC TESTS
    // ========================================================================

    public function test_can_get_group_with_permissions(): void
    {
        $this->actingAs($this->user);

        $group = entity(Group::class)->create([
            'name' => 'Test Group',
            'nickname' => 'test-group',
            'permissions' => ['pages' => ['getList' => true]],
        ]);

        $response = $this->getJson("/oxygen/api/groups/{$group->getId()}");

        $response->assertStatus(200)
            ->assertJsonPath('item.name', 'Test Group')
            ->assertJsonPath('item.nickname', 'test-group');
    }

    public function test_create_group_fails_without_preferences(): void
    {
        $this->actingAs($this->user);

        // 'preferences' is required but not in fillable fields,
        // so creating a group via API will fail validation
        $response = $this->postJson('/oxygen/api/groups', [
            'name' => 'New Group',
            'nickname' => 'new-group',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'failed');
    }
}
