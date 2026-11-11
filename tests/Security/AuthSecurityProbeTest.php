<?php

namespace Tests\Security;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Oxygen\Auth\Entity\DoctrineSession;
use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\IntegrationTestCase;

/**
 * Security probes: each test asserts the behaviour a hardened app SHOULD have, so a failure is a finding.
 * Grouped by class of flaw (OWASP ASVS V2/V3/V4).
 *
 * NOT part of the default suites (phpunit.xml only lists Unit and Feature), because some probes currently FAIL
 * by design - they document known, not-yet-fixed weaknesses (sessions surviving a password change, no password
 * strength rules, reminder-form email enumeration, ...). Run explicitly with:
 *
 *     vendor/bin/phpunit tests/Security --testdox
 *
 * As each finding is fixed its probe turns green; once all pass, move this directory under tests/Feature.
 */
class AuthSecurityProbeTest extends IntegrationTestCase {

    private User $user;
    private Group $group;

    protected function setUp(): void {
        parent::setUp();
        entity(PreferenceItem::class, 'modules.auth')->create();
        $this->group = entity(Group::class)->create(['permissions' => []]);
        $this->user = entity(User::class)->create(['group' => $this->group]);
    }

    private function spa(array $headers = []): static {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        return $this->withHeaders($headers + ['Referer' => 'http://localhost/', 'Origin' => 'http://localhost']);
    }

    private function login(string $username, string $password, array $extra = [], array $headers = []) {
        return $this->spa($headers)->postJson('/oxygen/api/auth/login', ['username' => $username, 'password' => $password] + $extra);
    }

    private function userWith(array $permissions): User {
        return entity(User::class)->create(['group' => entity(Group::class)->create(['permissions' => $permissions])]);
    }

    private function enableTwoFactor(User $user): void {
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->makeTwoFactorCode());
        cache()->flush();
    }

    private function collectKeys(mixed $data, array &$keys = []): array {
        if(is_array($data)) {
            foreach($data as $k => $v) { $keys[] = (string) $k; $this->collectKeys($v, $keys); }
        }
        return $keys;
    }

    // ===== 1. User enumeration ===================================================================================

    public function testLoginDoesNotRevealWhetherTheUsernameExists(): void {
        $unknown = $this->login('nosuchuser99', 'whatever');
        $wrong = $this->login($this->user->getUsername(), 'wrong-password');

        $this->assertSame($unknown->getStatusCode(), $wrong->getStatusCode());
        $this->assertSame($unknown->json(), $wrong->json());
    }

    public function testPasswordReminderDoesNotRevealWhetherTheEmailExists(): void {
        Notification::fake();

        $known = $this->postJson('/oxygen/api/auth/send-reminder-email', ['email' => $this->user->getEmail()]);
        $this->app['auth']->forgetGuards();
        $unknown = $this->postJson('/oxygen/api/auth/send-reminder-email', ['email' => 'nobody-here@example.com']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode(), 'status code differs for known vs unknown email');
        $this->assertSame($known->json('content'), $unknown->json('content'), 'message differs for known vs unknown email');
    }

    public function testDeactivatedAccountDoesNotConfirmThatThePasswordWasCorrect(): void {
        $deleted = entity(User::class)->create(['group' => $this->group]);
        $deleted->delete();
        $this->app->make(\Doctrine\ORM\EntityManagerInterface::class)->flush();

        $correct = $this->login($deleted->getUsername(), 'password');
        $incorrect = $this->login($deleted->getUsername(), 'wrong-password');

        $this->assertSame($incorrect->json('code'), $correct->json('code'), 'a deactivated account leaks that the password was right');
    }

    // ===== 2. Brute force / rate limiting ========================================================================

    public function testLoginIsThrottledPerUsernameRegardlessOfCase(): void {
        $name = $this->user->getUsername();
        for($i = 0; $i < 5; $i++) {
            $this->login($i % 2 ? strtoupper($name) : $name, 'nope');
        }

        $this->login($name, 'password')->assertStatus(429);
    }

    public function testLoginThrottleIsNotBypassedByRotatingForwardedForHeaders(): void {
        $name = $this->user->getUsername();
        for($i = 0; $i < 5; $i++) {
            $this->login($name, 'nope', [], ['X-Forwarded-For' => "198.51.100.$i"]);
        }

        $this->login($name, 'password', [], ['X-Forwarded-For' => '198.51.100.200'])->assertStatus(429);
    }

    public function testTwoFactorCodeGuessingIsThrottled(): void {
        $this->enableTwoFactor($this->user);

        $statuses = [];
        for($i = 0; $i < 8; $i++) {
            $statuses[] = $this->login($this->user->getUsername(), 'password', ['2fa_code' => sprintf('%06d', 100000 + $i)])->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'unlimited 2FA guesses: statuses were ' . implode(',', $statuses));
    }

    public function testPasswordReminderCannotBeUsedToEmailBombAnAddress(): void {
        Notification::fake();

        for($i = 0; $i < 10; $i++) {
            $this->app['auth']->forgetGuards();
            $this->postJson('/oxygen/api/auth/send-reminder-email', ['email' => $this->user->getEmail()]);
        }

        Notification::assertSentToTimes($this->user, ResetPassword::class, 1);
    }

    public function testResetTokenGuessingIsThrottled(): void {
        Password::broker()->createToken($this->user);
        $statuses = [];
        for($i = 0; $i < 30; $i++) {
            $this->app['auth']->forgetGuards();
            $statuses[] = $this->postJson('/oxygen/api/auth/reset-password', [
                'token' => bin2hex(random_bytes(20)), 'email' => $this->user->getEmail(),
                'password' => 'Another-pass-123', 'password_confirmation' => 'Another-pass-123',
            ])->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'no rate limit on reset-password attempts');
    }

    // ===== 3. Session management =================================================================================

    public function testSessionIdIsRegeneratedOnLogin(): void {
        $this->spa()->getJson('/oxygen/api/csrf-cookie');
        $before = session()->getId();

        $this->login($this->user->getUsername(), 'password')->assertOk();

        $this->assertNotSame($before, session()->getId(), 'session fixation: id kept across login');
    }

    private function makeOtherSession(User $user): void {
        $em = $this->app->make(\Doctrine\ORM\EntityManagerInterface::class);
        $s = new DoctrineSession('other-device', base64_encode(serialize([])));
        $s->setUser($user);
        $s->setIpAddress('203.0.113.9');
        $s->setUserAgent('other');
        $s->setLastActivity(new \DateTime());
        $em->persist($s);
        $em->flush();
    }

    private function otherSessionExists(): bool {
        $em = $this->app->make(\Doctrine\ORM\EntityManagerInterface::class);
        $em->clear();
        return $em->find(DoctrineSession::class, 'other-device') !== null;
    }

    public function testChangingPasswordSignsOutOtherSessions(): void {
        $user = $this->userWith(['auth' => ['postChangePassword' => true]]);
        $this->makeOtherSession($user);

        $this->withoutMiddleware(RequireTwoFactorEnabled::class);
        $this->actingAs($user)->postJson('/oxygen/api/auth/change-password', [
            'oldPassword' => 'password', 'password' => 'Brand-new-pass-1', 'passwordConfirmation' => 'Brand-new-pass-1',
        ])->assertOk();

        $this->assertFalse($this->otherSessionExists(), 'stolen sessions survive a password change');
    }

    public function testResettingPasswordSignsOutExistingSessions(): void {
        $this->makeOtherSession($this->user);
        $token = Password::broker()->createToken($this->user);

        $this->postJson('/oxygen/api/auth/reset-password', [
            'token' => $token, 'email' => $this->user->getEmail(),
            'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1',
        ])->assertOk();

        $this->assertFalse($this->otherSessionExists(), 'stolen sessions survive a password reset');
    }

    public function testDeactivatedUsersCannotBeRestoredFromTheSession(): void {
        $deleted = entity(User::class)->create(['group' => $this->group]);
        $deleted->delete();
        $this->app->make(\Doctrine\ORM\EntityManagerInterface::class)->flush();

        $provider = $this->app['auth']->guard('web')->getProvider();

        $this->assertTrue($provider->retrieveById($deleted->getId()) === null, 'a soft-deleted user is still resolved from an existing session');
    }

    public function testSessionCookieHasSafeFlags(): void {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    public function testSessionCookieIsSecureByDefault(): void {
        // `null` means "secure only if the request is HTTPS-detected by the browser/proxy"; production should force it
        $this->assertTrue(config('session.secure'), 'config/session.php `secure` is env-driven and unset by default (verify SESSION_SECURE_COOKIE=true in prod)');
    }

    // ===== 4. Passwords ==========================================================================================

    public function testPasswordsAreStoredHashedWithAnAdequateCost(): void {
        $hash = $this->user->getPassword();

        $this->assertNotSame('password', $hash);
        $info = password_get_info($hash);
        $this->assertNotSame('unknown', $info['algoName']);
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function testResetRejectsWeakPasswords(): void {
        foreach(['a', '123', 'password'] as $weak) {
            $token = Password::broker()->createToken($this->user);
            $response = $this->postJson('/oxygen/api/auth/reset-password', [
                'token' => $token, 'email' => $this->user->getEmail(), 'password' => $weak, 'password_confirmation' => $weak,
            ]);
            $this->assertSame(422, $response->getStatusCode(), "reset accepted the weak password '$weak'");
        }
    }

    public function testChangePasswordRejectsWeakPasswords(): void {
        $user = $this->userWith(['auth' => ['postChangePassword' => true]]);
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        foreach(['a', '123'] as $weak) {
            $response = $this->actingAs($user)->postJson('/oxygen/api/auth/change-password', [
                'oldPassword' => 'password', 'password' => $weak, 'passwordConfirmation' => $weak,
            ]);
            $this->assertSame('failed', $response->json('status'), "change-password accepted the weak password '$weak'");
        }
    }

    // ===== 5. Sensitive data exposure ============================================================================

    public function testApiResponsesNeverContainSecrets(): void {
        $this->enableTwoFactor($this->user);
        $admin = $this->userWith(['users' => ['getList' => true, 'getInfo' => true, 'getInfoBasic' => true]]);
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);
        $hash = $this->user->getPassword();
        $secret = (string) (new \ReflectionProperty(\Oxygen\Auth\Entity\TwoFactorAuthentication::class, 'shared_secret'))
            ->getValue((new \ReflectionProperty(User::class, 'twoFactorAuth'))->getValue($this->user));

        $responses = [
            $this->actingAs($admin)->getJson('/oxygen/api/users'),
            $this->actingAs($admin)->getJson('/oxygen/api/users/' . $this->user->getId()),
            $this->actingAs($admin)->getJson('/oxygen/api/users/' . $this->user->getId() . '/basic'),
        ];

        foreach($responses as $r) {
            $body = $r->getContent();
            $this->assertStringNotContainsString($hash, $body, 'password hash leaked');
            $this->assertStringNotContainsString($secret, $body, '2FA shared secret leaked');
            foreach($this->collectKeys($r->json() ?? []) as $key) {
                $this->assertDoesNotMatchRegularExpression('/password|remember|secret|token/i', $key, "suspicious key `$key` in response");
            }
        }
    }

    // ===== 6. Authorisation (IDOR / privilege checks) ============================================================

    public function testUsersCanOnlyEditTheirOwnFullName(): void {
        $me = $this->userWith(['users' => ['owner_putUpdateFullName' => true]]);
        $victim = entity(User::class)->create(['group' => $this->group, 'fullName' => 'Original Name']);
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        $this->actingAs($me)->putJson("/oxygen/api/users/{$victim->getId()}/fullName", ['fullName' => 'Hacked Name']);

        $em = $this->app->make(\Doctrine\ORM\EntityManagerInterface::class);
        $em->clear();
        $this->assertSame('Original Name', $em->find(User::class, $victim->getId())->getFullName());
    }

    public function testGeneralUserUpdateRequiresThePermission(): void {
        $me = $this->userWith(['users' => ['owner_putUpdateFullName' => true]]);
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        $response = $this->actingAs($me)->putJson("/oxygen/api/users/{$me->getId()}", ['group' => 1, 'fullName' => 'Me Again']);

        $response->assertJsonPath('status', 'failed');
    }

    public function testBasicUserInfoRequiresAPermission(): void {
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        $this->actingAs($this->user)->getJson("/oxygen/api/users/{$this->user->getId()}/basic")->assertJsonPath('status', 'failed');
    }

    public function testImpersonationRequiresAPermission(): void {
        $other = entity(User::class)->create(['group' => $this->group]);
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);

        $this->actingAs($this->user)->postJson("/oxygen/api/users/{$other->getId()}/impersonate")->assertJsonPath('status', 'failed');
        $this->assertFalse(app(\Lab404\Impersonate\Services\ImpersonateManager::class)->isImpersonating());
    }

    public function testProtectedApiRejectsUsersWithUnverifiedEmail(): void {
        $unverified = entity(User::class)->create(['group' => $this->group, 'verifiedAt' => null]);

        $status = $this->actingAs($unverified)->getJson('/oxygen/api/users')->getStatusCode();

        $this->assertContains($status, [401, 403, 409], "unverified user got HTTP $status");
    }

    public function testEmailVerificationLinkMustBeSigned(): void {
        $unverified = entity(User::class)->create(['group' => $this->group, 'verifiedAt' => null]);

        $this->actingAs($unverified)
            ->get('/oxygen/verify-email?id=' . $unverified->getId() . '&hash=' . sha1($unverified->getEmailForVerification()))
            ->assertStatus(403);
    }

    // ===== 7. Two-factor robustness ==============================================================================

    #[DataProvider('badCodes')]
    public function testMalformedTwoFactorCodesAreRejectedCleanly(mixed $code): void {
        $this->enableTwoFactor($this->user);

        $response = $this->login($this->user->getUsername(), 'password', ['2fa_code' => $code]);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertGuest('web');
    }

    public static function badCodes(): array {
        return [
            'array' => [['123456']], 'integer' => [123456], 'null' => [null], 'empty' => [''],
            'too long' => [str_repeat('1', 5000)], 'sql' => ["' OR '1'='1"], 'bool' => [true],
        ];
    }

    public function testPasswordResetDoesNotDisableTwoFactor(): void {
        $this->enableTwoFactor($this->user);
        $token = Password::broker()->createToken($this->user);
        $this->postJson('/oxygen/api/auth/reset-password', [
            'token' => $token, 'email' => $this->user->getEmail(),
            'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1',
        ])->assertOk();

        $this->login($this->user->getUsername(), 'Brand-new-pass-1')->assertStatus(401)->assertJson(['code' => 'two_factor_auth_failed']);
    }
}
