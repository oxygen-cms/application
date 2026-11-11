<?php

namespace Tests\Feature\Api;

/**
 * Trait providing soft-delete/restore test methods for soft-deletable resources.
 *
 * Concrete test classes using this trait must already have CrudApiTestTrait
 * and must implement the configuration methods inherited from that trait.
 *
 * Usage:
 * 1. Use both CrudApiTestTrait and SoftDeleteableCrudApiTestTrait in your test class
 * 2. Implement configuration methods from CrudApiTestTrait
 * 3. Add 'postRestore' => true to getAdditionalPermissions()
 * 4. Call setupCrudTest() in your setUp() method
 *
 * @see GroupsApiTest for an example
 */
trait SoftDeleteableCrudApiTestTrait
{
    // ========================================================================
    // SOFT DELETE / RESTORE TESTS
    // ========================================================================

    public function test_soft_deletable_can_restore_soft_deleted_item(): void
    {
        $this->actingAs($this->user);

        $entityClass = $this->getEntityClass();
        $factoryData = array_merge($this->getCreateFactoryOverrides(), [
            'deletedAt' => new \DateTime('-1 second'), // Must be in the past
        ]);

        $item = entity($entityClass)->create($factoryData);

        $response = $this->postJson("{$this->getApiEndpoint()}/{$item->getId()}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_soft_deletable_can_list_trashed_items(): void
    {
        $this->actingAs($this->user);

        $entityClass = $this->getEntityClass();

        // Create a normal item
        $normalData = $this->getCreateFactoryOverrides();
        // Make it unique to avoid conflicts
        if (isset($normalData['email'])) {
            $normalData['email'] = 'normal-' . uniqid() . '@example.com';
        }
        if (isset($normalData['slug'])) {
            $normalData['slug'] = 'normal-' . uniqid();
        }
        if (isset($normalData['slugPart'])) {
            $normalData['slugPart'] = 'normal-' . uniqid();
        }
        if (isset($normalData['nickname'])) {
            $normalData['nickname'] = 'normal-' . uniqid();
        }
        entity($entityClass)->create($normalData);

        // Create a trashed item with different identifying data to avoid conflicts
        $trashedData = $this->getCreateFactoryOverrides();
        // Modify to ensure unique constraint satisfaction
        if (isset($trashedData['email'])) {
            $trashedData['email'] = 'trashed-' . uniqid() . '@example.com';
        }
        if (isset($trashedData['slug'])) {
            $trashedData['slug'] = 'trashed-' . uniqid();
        }
        if (isset($trashedData['slugPart'])) {
            $trashedData['slugPart'] = 'trashed-' . uniqid();
        }
        if (isset($trashedData['nickname'])) {
            $trashedData['nickname'] = 'trashed-' . uniqid();
        }
        if (isset($trashedData['name'])) {
            $trashedData['name'] = 'Trashed ' . uniqid();
        }

        // Create item deleted in the past (OnlyTrashedScope requires deletedAt < current time)
        $deletedAt = new \DateTime('-1 second');
        entity($entityClass)->create(array_merge($trashedData, [
            'deletedAt' => $deletedAt,
        ]));

        // List with trash=true should only show trashed from this specific test
        $response = $this->getJson("{$this->getApiEndpoint()}?trash=true");

        $response->assertStatus(200);
        // Note: Some APIs use 'totalFiles' (Media), most use 'totalItems'
        // We check that at least 1 trashed item exists (there may be more from other tests)
        if ($response->json('totalFiles') !== null) {
            $this->assertGreaterThanOrEqual(1, $response->json('totalFiles'));
        } else {
            $this->assertGreaterThanOrEqual(1, $response->json('totalItems'));
        }
    }

    public function test_soft_deletable_restore_requires_authentication(): void
    {
        // Create item while authenticated (Blameable requires it)
        $this->actingAs($this->user);
        $entityClass = $this->getEntityClass();
        $item = entity($entityClass)->create(array_merge($this->getCreateFactoryOverrides(), [
            'deletedAt' => new \DateTime('-1 second'), // Must be in the past
        ]));
        $this->app['auth']->logout();

        $response = $this->postJson("{$this->getApiEndpoint()}/{$item->getId()}/restore");

        $response->assertStatus(401);
    }
}
