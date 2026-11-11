<?php

namespace Tests\Feature\Api;

use OxygenModule\Pages\Entity\Partial;
use Tests\IntegrationTestCase;

/**
 * Feature tests for Partials API endpoints.
 *
 * Tests CRUD operations on /oxygen/api/partials
 * Uses CrudApiTestTrait, SoftDeleteableCrudApiTestTrait, and VersionableCrudApiTestTrait.
 */
class PartialsApiTest extends IntegrationTestCase
{
    use CrudApiTestTrait;
    use SoftDeleteableCrudApiTestTrait;
    use VersionableCrudApiTestTrait;

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
        return Partial::class;
    }

    protected function getApiEndpoint(): string
    {
        return '/oxygen/api/partials';
    }

    protected function getPermissionKey(): string
    {
        return 'partials';
    }

    protected function getCreateFactoryOverrides(): array
    {
        return [
            'title' => 'Test Partial',
        ];
    }

    protected function getCreateRequestPayload(): array
    {
        return [
            'key' => 'test.partial',
            'title' => 'New Partial',
            'content' => '<p>Partial content</p>',
        ];
    }

    protected function getUpdateRequestPayload(): array
    {
        return [
            'title' => 'Updated Title',
        ];
    }

    protected function getIdentifyingField(): string
    {
        return 'title';
    }

    protected function getExpectedCreateValue(): string
    {
        return 'New Partial';
    }

    protected function getExpectedUpdateValue(): string
    {
        return 'Updated Title';
    }

    protected function getAdditionalPermissions(): array
    {
        return [
            'postRestore' => true,
            'listVersions' => true,
            'postMakeHeadVersion' => true,
            'postPublish' => true,
        ];
    }

    // ========================================================================
    // PARTIAL-SPECIFIC TESTS
    // ========================================================================

    public function test_update_requires_key_field(): void
    {
        $this->actingAs($this->user);

        $partial = entity(Partial::class)->create(['title' => 'Original Title']);

        $response = $this->putJson("/oxygen/api/partials/{$partial->getId()}", [
            'key' => $partial->getKey(),
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify update
        $getResponse = $this->getJson("/oxygen/api/partials/{$partial->getId()}");
        $getResponse->assertJsonPath('item.title', 'Updated Title');
    }
}
