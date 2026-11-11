<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Oxygen\Auth\Entity\User;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Oxygen\Auth\TwoFactor\Events\TwoFactorDisabled;
use Oxygen\Auth\TwoFactor\Events\TwoFactorEnabled;
use Oxygen\Auth\TwoFactor\Rules\TotpCodeRule;
use Tests\IntegrationTestCase;

/**
 * Covers the TOTP two-factor implementation in `Oxygen\Auth\TwoFactor`
 * (folded in from the laraguard fork).
 */
class TwoFactorAuthTest extends IntegrationTestCase {

    protected function setUp(): void {
        parent::setUp();
        // required by the auth middleware's redirect/permission handling
        entity(PreferenceItem::class, 'modules.auth')->create();
    }

    private function userWithTwoFactor(bool $enabled = true): User {
        $user = entity(User::class)->create();
        $user->createTwoFactorAuth();
        if($enabled) {
            $user->confirmTwoFactorAuth($user->makeTwoFactorCode());
        }
        return $user;
    }

    public function testNewUserHasTwoFactorDisabled(): void {
        $user = entity(User::class)->create();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertFalse($user->validateTwoFactorCode('123456'));
    }

    public function testCreateAndConfirmEnablesTwoFactor(): void {
        Event::fake([TwoFactorEnabled::class]);
        $user = entity(User::class)->create();

        $secret = $user->createTwoFactorAuth();
        $this->assertStringStartsWith('otpauth://totp/', $secret->toUri());
        $this->assertStringContainsString('<svg', $secret->toQr());
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->assertFalse($user->confirmTwoFactorAuth('000000'));
        $this->assertFalse($user->hasTwoFactorEnabled());

        $this->assertTrue($user->confirmTwoFactorAuth($user->makeTwoFactorCode()));
        $this->assertTrue($user->hasTwoFactorEnabled());
        Event::assertDispatched(TwoFactorEnabled::class);
    }

    public function testTotpRule(): void {
        $user = $this->userWithTwoFactor();
        // the confirming code is marked as used (replay protection); clear that so we can reuse the same TOTP window
        cache()->flush();
        $rule = new TotpCodeRule($user);

        $this->assertTrue($rule->passes('code', $user->makeTwoFactorCode()));
        $this->assertFalse($rule->passes('code', '000000'));
        $this->assertFalse($rule->passes('code', 123456));
        $this->assertFalse((new TotpCodeRule(null))->passes('code', $user->makeTwoFactorCode()));
    }

    public function testDisableTwoFactor(): void {
        Event::fake([TwoFactorDisabled::class]);
        $user = $this->userWithTwoFactor();

        $user->disableTwoFactorAuth();

        $this->assertFalse($user->hasTwoFactorEnabled());
        Event::assertDispatched(TwoFactorDisabled::class);
    }

    public function testLoginWithoutTwoFactorDoesNotNeedCode(): void {
        $user = entity(User::class)->create();

        $this->postJson('/oxygen/api/auth/login', ['username' => $user->getUsername(), 'password' => 'password'])
            ->assertOk();
    }

    public function testLoginRequiresValidCodeWhenEnabled(): void {
        $user = $this->userWithTwoFactor();
        $credentials = ['username' => $user->getUsername(), 'password' => 'password'];

        $this->postJson('/oxygen/api/auth/login', $credentials)
            ->assertStatus(401)
            ->assertJson(['code' => 'two_factor_auth_failed']);

        $this->postJson('/oxygen/api/auth/login', $credentials + ['2fa_code' => '000000'])
            ->assertStatus(401)
            ->assertJson(['code' => 'two_factor_auth_failed']);

        // A valid code passes validation (covered by testTotpRule); a full successful login needs the SPA's
        // stateful session middleware, which the plain `api` group used in tests doesn't provide.
    }

    public function testRequireMiddlewareBlocksUsersWithoutTwoFactor(): void {
        $user = entity(User::class)->create();

        $this->actingAs($user)
            ->postJson('/oxygen/api/auth/verify-email')
            ->assertStatus(403)
            ->assertJson(['code' => 'two_factor_setup_required']);
    }

    public function testDisabledMiddlewareBlocksUsersWithTwoFactor(): void {
        $user = $this->userWithTwoFactor();

        $this->actingAs($user)
            ->postJson('/oxygen/api/auth/two-factor-setup')
            ->assertOk()
            ->assertJsonFragment(['content' => trans('oxygen/auth::messages.twoFactor.alreadyEnabled')]);
    }
}
