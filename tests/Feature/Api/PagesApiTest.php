<?php

namespace Tests\Feature\Api;

use OxygenModule\Pages\Entity\Page;
use Tests\IntegrationTestCase;

/**
 * Feature tests for the Pages API endpoints.
 *
 * Tests CRUD operations on /oxygen/api/pages
 * Uses CrudApiTestTrait, SoftDeleteableCrudApiTestTrait, and VersionableCrudApiTestTrait.
 */
class PagesApiTest extends IntegrationTestCase
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
        return Page::class;
    }

    protected function getApiEndpoint(): string
    {
        return '/oxygen/api/pages';
    }

    protected function getPermissionKey(): string
    {
        return 'pages';
    }

    protected function getCreateFactoryOverrides(): array
    {
        return [
            'title' => 'Test Page',
            'slugPart' => 'test-page',
        ];
    }

    protected function getCreateRequestPayload(): array
    {
        return [
            'title' => 'New Page',
            'slugPart' => 'new-page',
            'content' => '<h1>Hello World</h1>',
            'description' => 'A test page',
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
        return 'New Page';
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
}
