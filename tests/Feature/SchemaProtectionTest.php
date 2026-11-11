<?php

namespace Tests\Feature;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\IntegrationTestCase;

/**
 * `doctrine:schema:update` must never propose dropping framework tables, but must still show genuinely unmapped ones.
 */
class SchemaProtectionTest extends IntegrationTestCase {

    private function updateSql(): string {
        $em = $this->app->make(EntityManagerInterface::class);
        return implode("\n", (new SchemaTool($em))->getUpdateSchemaSql($em->getMetadataFactory()->getAllMetadata()));
    }

    private function startCommand(string $name): void {
        $this->app['events']->dispatch(new CommandStarting($name, new ArrayInput([]), new NullOutput()));
    }

    public function testFrameworkTablesAreNeverDropped(): void {
        $connection = $this->app->make(EntityManagerInterface::class)->getConnection();
        $resetTable = config('auth.passwords.users.table');
        $tables = ['migrations', 'personal_access_tokens', $resetTable, 'legacy_unmapped_table'];
        foreach($tables as $table) {
            $connection->executeStatement("CREATE TABLE \"$table\" (id INTEGER PRIMARY KEY)");
        }

        try {
            $this->startCommand('doctrine:schema:update');
            $sql = $this->updateSql();
        } finally {
            foreach($tables as $table) { $connection->executeStatement("DROP TABLE IF EXISTS \"$table\""); }
        }

        $this->assertStringNotContainsString('DROP TABLE migrations', $sql);
        $this->assertStringNotContainsString('DROP TABLE personal_access_tokens', $sql);
        $this->assertStringNotContainsString("DROP TABLE $resetTable", $sql);
        $this->assertStringContainsString('DROP TABLE legacy_unmapped_table', $sql, 'genuinely unmapped tables must still be reported');
    }

    public function testTheFilterIsNotActiveAtRuntime(): void {
        // otherwise laravel-doctrine's token repository can't see its own table and tries to create it again
        $connection = $this->app->make(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('CREATE TABLE "migrations" (id INTEGER PRIMARY KEY)');
        try {
            $this->assertContains('migrations', $connection->createSchemaManager()->listTableNames());
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS "migrations"');
        }
    }

    public function testThePasswordResetTableIsProtected(): void {
        $this->assertSame('password_resets', config('auth.passwords.users.table'));
        $this->assertContains('password_resets', \App\Providers\DoctrineSchemaServiceProvider::protectedTables());
    }
}
