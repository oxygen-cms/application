<?php

namespace Tests\Feature\Auth;

use Doctrine\ORM\EntityManagerInterface;
use Oxygen\Auth\Entity\AuthenticationLogEntry;
use Oxygen\Auth\Entity\DoctrineSession;
use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Tests\IntegrationTestCase;

/**
 * The custom Doctrine session storage (list / delete a user's sessions) and the login log.
 */
class SessionsAndLogTest extends IntegrationTestCase {

    private User $user;
    private User $other;

    protected function setUp(): void {
        parent::setUp();
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);
        entity(PreferenceItem::class, 'modules.auth')->create();

        $group = entity(Group::class)->create([
            'permissions' => ['auth' => [
                'getUserSessions' => true,
                'getAuthenticationLogEntries' => true,
            ]],
        ]);
        $this->user = entity(User::class)->create(['group' => $group]);
        $this->other = entity(User::class)->create(['group' => $group]);
    }

    private function em(): EntityManagerInterface {
        return $this->app->make(EntityManagerInterface::class);
    }

    private function makeSession(string $id, User $user, string $ip = '203.0.113.7'): DoctrineSession {
        $session = new DoctrineSession($id, base64_encode(serialize(['a' => 'b'])));
        $session->setUser($user);
        $session->setIpAddress($ip);
        $session->setUserAgent('PHPUnit');
        $session->setLastActivity(new \DateTime());
        $this->em()->persist($session);
        $this->em()->flush();
        return $session;
    }

    // ---- sessions -----------------------------------------------------------

    public function testListsOnlyTheCurrentUsersSessions(): void {
        $this->makeSession('mine-1', $this->user);
        $this->makeSession('mine-2', $this->user, '198.51.100.9');
        $this->makeSession('theirs', $this->other);

        $response = $this->actingAs($this->user)->getJson('/oxygen/api/auth/sessions')->assertOk();

        $ids = array_column($response->json('sessions'), 'id');
        sort($ids);
        $this->assertSame(['mine-1', 'mine-2'], $ids);
        $first = collect($response->json('sessions'))->firstWhere('id', 'mine-1');
        $this->assertSame('203.0.113.7', $first['ipAddress']);
        $this->assertSame('PHPUnit', $first['userAgent']);
        $this->assertNotEmpty($first['lastActivity']);
    }

    public function testCanDeleteOwnSession(): void {
        $this->makeSession('mine-1', $this->user);

        $this->actingAs($this->user)->deleteJson('/oxygen/api/auth/sessions/mine-1')
            ->assertOk()->assertJson(['code' => 'removed']);

        $this->em()->clear();
        $this->assertNull($this->em()->find(DoctrineSession::class, 'mine-1'));
    }

    public function testCannotDeleteAnotherUsersSession(): void {
        $this->makeSession('theirs', $this->other);

        $this->actingAs($this->user)->deleteJson('/oxygen/api/auth/sessions/theirs')->assertOk();

        $this->em()->clear();
        $this->assertNotNull($this->em()->find(DoctrineSession::class, 'theirs'));
    }

    public function testSessionsRequireAuthentication(): void {
        $this->getJson('/oxygen/api/auth/sessions')->assertStatus(401);
        $this->deleteJson('/oxygen/api/auth/sessions/x')->assertStatus(401);
    }

    public function testSessionExpiryUsesCarbon(): void {
        $session = $this->makeSession('old', $this->user);
        $session->setLastActivity(now()->subMinutes(30)->toDateTime());

        $this->assertTrue($session->expired(10));
        $this->assertFalse($session->expired(60));
    }

    // ---- login log ----------------------------------------------------------

    private function makeLogEntry(User $user, int $type): void {
        $entry = new AuthenticationLogEntry();
        $entry->setUser($user);
        $entry->setIpAddress('203.0.113.7');
        $entry->setUserAgent('PHPUnit');
        $entry->setType($type);
        $this->em()->persist($entry);
        $this->em()->flush();
    }

    public function testLoginLogEntriesAreScopedToTheUser(): void {
        $this->makeLogEntry($this->user, AuthenticationLogEntry::LOGIN_SUCCESS);
        $this->makeLogEntry($this->user, AuthenticationLogEntry::LOGIN_FAILED);
        $this->makeLogEntry($this->other, AuthenticationLogEntry::LOGIN_SUCCESS);

        $response = $this->actingAs($this->user)->postJson('/oxygen/api/auth/login-log-entries')
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(2, $response->json('totalItems'));
        $this->assertCount(2, $response->json('items'));
        $this->assertSame($this->user->getId(), $response->json('items.0.user'));
    }

    public function testLoginLogRequiresAuthentication(): void {
        $this->postJson('/oxygen/api/auth/login-log-entries')->assertStatus(401);
    }
}
