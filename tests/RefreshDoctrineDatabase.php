<?php

namespace Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Illuminate\Contracts\Console\Kernel;

/**
 * Trait for refreshing the Doctrine database between tests.
 *
 * Unlike Laravel's RefreshDatabase which uses migrations, this trait
 * uses Doctrine's SchemaTool to create/drop tables based on entity mappings.
 */
trait RefreshDoctrineDatabase
{
    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    public function refreshDoctrineDatabase(): void
    {
        $this->beforeRefreshingDoctrineDatabase();

        $this->usingDoctrineTransaction(function () {
            // Database will be rolled back after each test
        });

        $this->afterRefreshingDoctrineDatabase();
    }

    /**
     * Begin a database transaction on the testing database.
     *
     * @return void
     */
    public function beginDatabaseTransaction(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->app->make(EntityManagerInterface::class);
        $connection = $em->getConnection();

        // Ensure schema exists
        $this->ensureSchemaExists($em);

        $connection->beginTransaction();
    }

    /**
     * Ensure the Doctrine schema exists in the database.
     *
     * @param EntityManagerInterface $em
     * @return void
     */
    protected function ensureSchemaExists(EntityManagerInterface $em): void
    {
        static $schemaCreated = false;

        if (!$schemaCreated) {
            $schemaTool = new SchemaTool($em);
            $metadata = $em->getMetadataFactory()->getAllMetadata();

            if (!empty($metadata)) {
                // Drop all tables and recreate
                $schemaTool->dropSchema($metadata);
                $schemaTool->createSchema($metadata);
            }

            $schemaCreated = true;
        }
    }

    /**
     * Handle database transactions on the specified connections.
     *
     * @param callable $callback
     * @return void
     */
    protected function usingDoctrineTransaction(callable $callback): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->app->make(EntityManagerInterface::class);

        $this->beforeApplicationDestroyed(function () use ($em) {
            $connection = $em->getConnection();

            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            // Clear entity manager to remove any cached entities
            $em->clear();
        });

        $this->beginDatabaseTransaction();

        $callback();
    }

    /**
     * Perform any work before the database has started refreshing.
     *
     * @return void
     */
    protected function beforeRefreshingDoctrineDatabase(): void
    {
        // Override in test class if needed
    }

    /**
     * Perform any work after the database has finished refreshing.
     *
     * @return void
     */
    protected function afterRefreshingDoctrineDatabase(): void
    {
        // Override in test class if needed
    }
}
