<?php

namespace Tests\Feature\Api;

use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use OxygenModule\Media\Entity\Media;
use Tests\IntegrationTestCase;

/**
 * Feature tests for Media API endpoints.
 *
 * Tests CRUD operations on /oxygen/api/media
 * Uses SoftDeleteableCrudApiTestTrait and VersionableCrudApiTestTrait for specific features.
 * Media API has custom response format ('files'/'totalFiles' instead of 'items'/'totalItems'),
 * so CRUD tests are kept custom instead of using CrudApiTestTrait.
 */
class MediaApiTest extends IntegrationTestCase
{
    use SoftDeleteableCrudApiTestTrait;
    use VersionableCrudApiTestTrait;

    protected User $user;
    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable 2FA middleware for tests
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        // Seed the auth preferences
        entity(PreferenceItem::class, 'modules.auth')->create();

        // Create a group with media permissions
        $this->group = entity(Group::class)->create([
            'name' => 'Test Admin',
            'nickname' => 'test-admin-' . uniqid(),
            'permissions' => [
                'media' => [
                    'getList' => true,
                    'getInfo' => true,
                    'putUpdate' => true,
                    'deleteDelete' => true,
                    'postRestore' => true,
                    'listVersions' => true,
                    'postMakeHeadVersion' => true,
                ],
            ],
        ]);

        // Create a user in that group
        $this->user = entity(User::class)->create([
            'group' => $this->group,
        ]);
    }

    // ========================================================================
    // TRAIT CONFIGURATION
    // ========================================================================

    protected function getEntityClass(): string
    {
        return Media::class;
    }

    protected function getApiEndpoint(): string
    {
        return '/oxygen/api/media';
    }

    protected function getCreateFactoryOverrides(): array
    {
        return [
            'name' => 'Test Media',
        ];
    }

    protected function getAdditionalPermissions(): array
    {
        return [
            'postRestore' => true,
            'listVersions' => true,
            'postMakeHeadVersion' => true,
        ];
    }

    // ========================================================================
    // CUSTOM CRUD TESTS (Media API uses 'files' instead of 'items')
    // ========================================================================

    public function test_can_list_media(): void
    {
        $this->actingAs($this->user);

        entity(Media::class)->create(['name' => 'Test Image']);
        entity(Media::class)->create(['name' => 'Another Image']);

        $response = $this->getJson('/oxygen/api/media');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'files');
    }

    public function test_can_get_single_media(): void
    {
        $this->actingAs($this->user);

        $media = entity(Media::class)->create(['name' => 'Test Media']);

        $response = $this->getJson("/oxygen/api/media/{$media->getId()}");

        $response->assertStatus(200)
            ->assertJsonPath('item.name', 'Test Media');
    }

    public function test_can_update_media(): void
    {
        $this->actingAs($this->user);

        $media = entity(Media::class)->create(['name' => 'Original Name']);

        $response = $this->putJson("/oxygen/api/media/{$media->getId()}", [
            'name' => 'Updated Name',
            'slug' => $media->getSlug(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Verify update
        $getResponse = $this->getJson("/oxygen/api/media/{$media->getId()}");
        $getResponse->assertJsonPath('item.name', 'Updated Name');
    }

    public function test_can_delete_media(): void
    {
        $this->actingAs($this->user);

        $media = entity(Media::class)->create(['name' => 'To Delete']);
        $mediaId = $media->getId();

        $response = $this->deleteJson("/oxygen/api/media/{$mediaId}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
