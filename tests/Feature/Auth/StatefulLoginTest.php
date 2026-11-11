<?php

namespace Tests\Feature\Auth;

use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Tests\IntegrationTestCase;

/**
 * Login exactly as the SPA does it: requests carrying a stateful `Referer`/`Origin`, so Sanctum's
 * EnsureFrontendRequestsAreStateful adds the session middleware. (A plain `api` request has no session.)
 */
class StatefulLoginTest extends IntegrationTestCase {

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        entity(PreferenceItem::class, 'modules.auth')->create();
        $group = entity(Group::class)->create(['permissions' => []]);
        $this->user = entity(User::class)->create(['group' => $group]);
    }

    private function spa(): static {
        // every real HTTP request is a fresh process; the test app is reused, so drop guards' cached users
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web'); // `auth:sanctum` middleware switches the default guard for the rest of the process
        return $this->withHeaders(['Referer' => 'http://localhost/', 'Origin' => 'http://localhost']);
    }

    private function credentials(array $extra = []): array {
        return ['username' => $this->user->getUsername(), 'password' => 'password'] + $extra;
    }

    private function withTwoFactor(): void {
        $this->user->createTwoFactorAuth();
        $this->user->confirmTwoFactorAuth($this->user->makeTwoFactorCode());
        // enabling consumed this window's code (replay protection); clear it so the same window's code can log in
        cache()->flush();
    }

    public function testStatefulLoginAuthenticatesTheSession(): void {
        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials())
            ->assertOk()
            ->assertJsonPath('user.id', $this->user->getId());

        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function testWrongPasswordIsRejected(): void {
        $this->spa()->postJson('/oxygen/api/auth/login', ['username' => $this->user->getUsername(), 'password' => 'nope'])
            ->assertStatus(401)
            ->assertJson(['code' => 'incorrect_username_password']);

        $this->assertGuest('web');
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void {
        for($i = 0; $i < 5; $i++) {
            $this->spa()->postJson('/oxygen/api/auth/login', ['username' => $this->user->getUsername(), 'password' => 'nope']);
        }

        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials())->assertStatus(429);
    }

    public function testLogoutEndsTheSession(): void {
        $this->withoutMiddleware(RequireTwoFactorEnabled::class); // otherwise users without 2FA get a 403 here
        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials())->assertOk();

        $this->spa()->postJson('/oxygen/api/auth/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function testTwoFactorLoginSucceedsWithAValidCode(): void {
        $this->withTwoFactor();

        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials(['2fa_code' => $this->user->makeTwoFactorCode()]))
            ->assertOk();

        $this->assertAuthenticatedAs($this->user, 'web');
        $this->assertNotNull(session('2fa.totp_confirmed_at'));
    }

    public function testTwoFactorLoginRejectsAMissingOrWrongCode(): void {
        $this->withTwoFactor();

        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials())
            ->assertStatus(401)->assertJson(['code' => 'two_factor_auth_failed']);
        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials(['2fa_code' => '000000']))
            ->assertStatus(401)->assertJson(['code' => 'two_factor_auth_failed']);

        $this->assertGuest('web');
    }

    public function testTotpCodeCannotBeReplayedForASecondLogin(): void {
        $this->withTwoFactor();
        $code = $this->user->makeTwoFactorCode();

        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials(['2fa_code' => $code]))->assertOk();
        $this->spa()->postJson('/oxygen/api/auth/logout')->assertNoContent();
        $this->assertGuest('web');

        // an attacker who observed the code must not be able to use it again within its validity window
        $this->spa()->postJson('/oxygen/api/auth/login', $this->credentials(['2fa_code' => $code]))
            ->assertStatus(401)
            ->assertJson(['code' => 'two_factor_auth_failed']);
        $this->assertGuest('web');
    }
}
