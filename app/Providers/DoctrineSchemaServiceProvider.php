<?php

namespace App\Providers;

use Doctrine\ORM\EntityManager;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\ServiceProvider;

/**
 * Stops `doctrine:schema:*` commands from proposing to DROP tables that belong to Laravel / its packages rather than
 * to a Doctrine entity (e.g. `migrations`, which `php artisan migrate` needs, and the password-reset tokens table).
 *
 * The filter is applied ONLY while a `doctrine:schema:*` command runs. It must not be global: laravel-doctrine's
 * DoctrineTokenRepository decides whether to create the reset table by introspecting the schema, so hiding the table
 * from introspection at runtime would make it try (and fail) to create it again.
 *
 * Tables that are merely legacy (e.g. `two_factor_authentications`) are deliberately NOT listed,
 * so they still show up in the diff and must be dropped on purpose.
 */
class DoctrineSchemaServiceProvider extends ServiceProvider {

    /** @return string[] */
    public static function protectedTables(): array {
        $resetTables = array_filter(array_column(config('auth.passwords', []), 'table'));

        return array_values(array_unique(array_merge(
            ['migrations', 'personal_access_tokens', 'jobs', 'failed_jobs'],
            $resetTables
        )));
    }

    public function boot(): void {
        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event) {
            if(!str_starts_with((string) $event->command, 'doctrine:schema')) {
                return;
            }
            $protected = self::protectedTables();
            $this->app[EntityManager::class]->getConnection()->getConfiguration()->setSchemaAssetsFilter(
                fn($asset) => !in_array(is_string($asset) ? $asset : $asset->getName(), $protected, true)
            );
        });
    }
}
