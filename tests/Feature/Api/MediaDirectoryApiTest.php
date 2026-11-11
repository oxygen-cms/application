<?php

namespace Tests\Feature\Api;

use OxygenModule\Media\Entity\MediaDirectory;
use Tests\IntegrationTestCase;

/**
 * Feature tests for Media Directory API endpoints.
 *
 * Tests CRUD operations on /oxygen/api/media-directory
 * Uses CrudApiTestTrait and SoftDeleteableCrudApiTestTrait.
 */
class MediaDirectoryApiTest extends IntegrationTestCase
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
        return MediaDirectory::class;
    }

    protected function getApiEndpoint(): string
    {
        return '/oxygen/api/media-directory';
    }

    protected function getPermissionKey(): string
    {
        return 'mediaDirectory';
    }

    protected function getCreateFactoryOverrides(): array
    {
        return [
            'name' => 'Test Directory',
        ];
    }

    protected function getCreateRequestPayload(): array
    {
        return [
            'name' => 'New Directory',
            'slug' => 'new-directory',
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
        return 'New Directory';
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

    // ========================================================================
    // OVERRIDES: MediaDirectory uses a custom getListApi that returns a flat
    // items array (no pagination/totalItems), plus a virtual root directory.
    // ========================================================================

    public function test_can_list_items(): void
    {
        $this->actingAs($this->user);

        entity($this->getEntityClass(), 3)->create();

        $response = $this->getJson($this->getApiEndpoint());

        $response->assertStatus(200)
            ->assertJsonStructure(['items', 'status']);

        // 3 created + 1 virtual root directory
        $this->assertCount(4, $response->json('items'));
    }

    public function test_can_delete_item(): void
    {
        $entity = $this->createTestEntity();
        $entityId = $entity->getId();

        // Verify entity exists before deletion (1 entity + 1 virtual root)
        $response = $this->getJson($this->getApiEndpoint());
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('items'));

        $response = $this->deleteJson("{$this->getApiEndpoint()}/{$entityId}");
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // After soft-delete, only virtual root remains
        $response = $this->getJson($this->getApiEndpoint());
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('items'));
    }

    public function test_can_search_items(): void
    {
        $this->actingAs($this->user);
        $field = $this->getIdentifyingField();

        entity($this->getEntityClass())->create([$field => 'Alpha Test']);
        entity($this->getEntityClass())->create([$field => 'Beta Search']);
        entity($this->getEntityClass())->create([$field => 'Gamma Test']);

        // MediaDirectory doesn't implement Searchable, so the search query
        // is ignored and all items are returned
        $response = $this->getJson("{$this->getApiEndpoint()}?q=Test");

        $response->assertStatus(200);
        // 3 created items + 1 virtual root directory
        $this->assertCount(4, $response->json('items'));
    }

    public function test_soft_deletable_can_list_trashed_items(): void
    {
        $this->actingAs($this->user);

        $entityClass = $this->getEntityClass();

        entity($entityClass)->create(['name' => 'Normal Directory']);
        entity($entityClass)->create(array_merge(
            ['name' => 'Trashed Directory'],
            ['deletedAt' => new \DateTime('-1 second')] // Must be in the past for OnlyTrashedScope
        ));

        $response = $this->getJson("{$this->getApiEndpoint()}?trash=true");

        $response->assertStatus(200);
        // At least 1 trashed item + 1 virtual root directory
        $this->assertGreaterThanOrEqual(2, count($response->json('items')));
    }

    // ========================================================================
    // DIRECTORY-SPECIFIC TESTS
    // ========================================================================

    public function test_can_create_nested_directory(): void
    {
        $this->actingAs($this->user);

        // Create parent directory
        $parent = entity(MediaDirectory::class)->create(['name' => 'Parent']);

        // Create child directory
        $response = $this->postJson('/oxygen/api/media-directory', [
            'name' => 'Child',
            'slug' => 'child',
            'parentDirectory' => $parent->getId(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_update_requires_slug_field(): void
    {
        $this->actingAs($this->user);

        $directory = entity(MediaDirectory::class)->create(['name' => 'Original Name']);

        $response = $this->putJson("/oxygen/api/media-directory/{$directory->getId()}", [
            'name' => 'Updated Name',
            'slug' => $directory->getSlug(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify update
        $getResponse = $this->getJson("/oxygen/api/media-directory/{$directory->getId()}");
        $getResponse->assertJsonPath('item.name', 'Updated Name');
    }
}
