<?php

namespace Tests\Feature\Api;

/**
 * Trait providing versioning test methods for versionable resources.
 *
 * Concrete test classes using this trait must already have CrudApiTestTrait
 * and must implement the configuration methods inherited from that trait.
 *
 * Note: Versionable resources are also soft-deletable, so you should also
 * use SoftDeleteableCrudApiTestTrait to test restore functionality.
 *
 * Usage:
 * 1. Use CrudApiTestTrait, SoftDeleteableCrudApiTestTrait, and VersionableCrudApiTestTrait
 * 2. Implement configuration methods from CrudApiTestTrait
 * 3. Optionally override supportsPublishing() if resource supports publishing
 * 4. Add versioning permissions to getAdditionalPermissions():
 *    - 'listVersions' => true
 *    - 'postMakeHeadVersion' => true
 *    - 'postPublish' => true (if publishable)
 * 5. Call setupCrudTest() in your setUp() method
 *
 * @see PagesApiTest for an example
 */
trait VersionableCrudApiTestTrait
{
    /**
     * Override this method to indicate if the resource supports publishing.
     * Defaults to false.
     */
    protected function supportsPublishing(): bool
    {
        return false;
    }

    // ========================================================================
    // VERSIONING TESTS
    // ========================================================================

    public function test_versionable_can_list_versions_of_item(): void
    {
        $this->actingAs($this->user);

        $entityClass = $this->getEntityClass();
        $item = entity($entityClass)->create($this->getCreateFactoryOverrides());

        $response = $this->getJson("{$this->getApiEndpoint()}/{$item->getId()}/versions");

        $response->assertStatus(200)
            ->assertJsonStructure(['items', 'status'])
            ->assertJsonCount(1, 'items'); // Should have at least the head version
    }

    public function test_versionable_make_head_version_fails_when_already_head(): void
    {
        $this->actingAs($this->user);

        $entityClass = $this->getEntityClass();
        $item = entity($entityClass)->create($this->getCreateFactoryOverrides());

        // Try to make it head again (should fail since it's already the head)
        $response = $this->postJson("{$this->getApiEndpoint()}/{$item->getId()}/make-head");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'failed');
    }

    // ========================================================================
    // PUBLISHING TESTS (only if resource supports it)
    // ========================================================================

    public function test_versionable_can_publish_draft_item(): void
    {
        if (!$this->supportsPublishing()) {
            $this->markTestSkipped('Resource does not support publishing');
        }

        $this->actingAs($this->user);

        $entityClass = $this->getEntityClass();
        $factoryData = array_merge($this->getCreateFactoryOverrides(), [
            'stage' => 0, // Draft stage
        ]);

        $item = entity($entityClass)->create($factoryData);

        $response = $this->postJson("{$this->getApiEndpoint()}/{$item->getId()}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    // ========================================================================
    // AUTHENTICATION TESTS
    // ========================================================================

    public function test_versionable_list_versions_requires_authentication(): void
    {
        // Create item while authenticated (Blameable requires it)
        $this->actingAs($this->user);
        $entityClass = $this->getEntityClass();
        $item = entity($entityClass)->create($this->getCreateFactoryOverrides());
        $this->app['auth']->logout();

        $response = $this->getJson("{$this->getApiEndpoint()}/{$item->getId()}/versions");

        $response->assertStatus(401);
    }

    public function test_versionable_publish_requires_authentication(): void
    {
        if (!$this->supportsPublishing()) {
            $this->markTestSkipped('Resource does not support publishing');
        }

        // Create item while authenticated (Blameable requires it)
        $this->actingAs($this->user);
        $entityClass = $this->getEntityClass();
        $item = entity($entityClass)->create($this->getCreateFactoryOverrides());
        $this->app['auth']->logout();

        $response = $this->postJson("{$this->getApiEndpoint()}/{$item->getId()}/publish");

        $response->assertStatus(401);
    }
}
