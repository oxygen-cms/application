<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Oxygen\Auth\Entity\Group;
use Oxygen\Auth\Entity\User;
use Oxygen\Auth\Middleware\RequireTwoFactorEnabled;
use Oxygen\Core\Preferences\Loader\Database\PreferenceItem;
use Tests\IntegrationTestCase;

/**
 * Password reminder / reset / change flows, plus the destructive account endpoints.
 *
 * Reset tokens go through the framework's DatabaseTokenRepository, whose `expires`
 * handling changed in Laravel 12 (minutes -> seconds), so expiry is tested explicitly.
 */
class PasswordFlowsTest extends IntegrationTestCase {

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->withoutMiddleware(RequireTwoFactorEnabled::class);
        entity(PreferenceItem::class, 'modules.auth')->create();

        $group = entity(Group::class)->create([
            'permissions' => [
                'auth' => ['postChangePassword' => true, 'deleteForce' => true],
                'users' => ['deleteForce' => true],
            ],
        ]);
        $this->user = entity(User::class)->create(['group' => $group]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- send-reminder-email ------------------------------------------------

    public function testReminderEmailIsSentToKnownUser(): void {
        Notification::fake();

        $this->postJson('/oxygen/api/auth/send-reminder-email', ['email' => $this->user->getEmail()])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    public function testReminderEmailForUnknownAddressFails(): void {
        Notification::fake();

        $this->postJson('/oxygen/api/auth/send-reminder-email', ['email' => 'nobody@example.com'])
            ->assertStatus(422)
            ->assertJson(['status' => 'failed']);

        Notification::assertNothingSent();
    }

    // ---- reset-password -----------------------------------------------------

    private function resetPayload(string $token, string $password = 'a-new-Password-1'): array {
        return [
            'token' => $token,
            'email' => $this->user->getEmail(),
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function testResetPasswordWithValidToken(): void {
        $token = Password::broker()->createToken($this->user);

        $this->postJson('/oxygen/api/auth/reset-password', $this->resetPayload($token))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue(Hash::check('a-new-Password-1', $this->user->getPassword()));
    }

    public function testResetTokenCannotBeReused(): void {
        $token = Password::broker()->createToken($this->user);

        $this->postJson('/oxygen/api/auth/reset-password', $this->resetPayload($token))->assertOk();

        $this->postJson('/oxygen/api/auth/reset-password', $this->resetPayload($token, 'another-Password-2'))
            ->assertStatus(422)
            ->assertJson(['code' => 'reset_failed']);
        $this->assertFalse(Hash::check('another-Password-2', $this->user->getPassword()));
    }

    public function testResetTokenExpires(): void {
        $token = Password::broker()->createToken($this->user);
        $minutes = config('auth.passwords.users.expire');

        // still valid just before expiry...
        Carbon::setTestNow(now()->addMinutes($minutes - 1));
        $this->assertTrue(Password::broker()->tokenExists($this->user, $token));

        // ...and rejected just after (guards against the minutes -> seconds change in Laravel 12)
        Carbon::setTestNow(now()->addMinutes(2));
        $this->postJson('/oxygen/api/auth/reset-password', $this->resetPayload($token))
            ->assertStatus(422)
            ->assertJson(['code' => 'reset_failed']);
    }

    public function testResetWithWrongTokenIsRejected(): void {
        Password::broker()->createToken($this->user);

        $this->postJson('/oxygen/api/auth/reset-password', $this->resetPayload('not-the-token'))
            ->assertStatus(422)
            ->assertJson(['code' => 'reset_failed']);
    }

    public function testResetRequiresMatchingConfirmation(): void {
        $token = Password::broker()->createToken($this->user);
        $payload = $this->resetPayload($token);
        $payload['password_confirmation'] = 'different';

        $this->postJson('/oxygen/api/auth/reset-password', $payload)->assertStatus(422);
    }

    // ---- change-password ----------------------------------------------------

    public function testChangePasswordSucceeds(): void {
        $this->actingAs($this->user)
            ->postJson('/oxygen/api/auth/change-password', [
                'oldPassword' => 'password',
                'password' => 'brand-New-pass-1',
                'passwordConfirmation' => 'brand-New-pass-1',
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue(Hash::check('brand-New-pass-1', $this->user->getPassword()));
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void {
        $this->actingAs($this->user)
            ->postJson('/oxygen/api/auth/change-password', [
                'oldPassword' => 'wrong',
                'password' => 'brand-New-pass-1',
                'passwordConfirmation' => 'brand-New-pass-1',
            ])
            ->assertJson(['status' => 'failed']);

        $this->assertTrue(Hash::check('password', $this->user->getPassword()));
    }

    public function testChangePasswordRejectsMismatchedConfirmation(): void {
        $this->actingAs($this->user)
            ->postJson('/oxygen/api/auth/change-password', [
                'oldPassword' => 'password',
                'password' => 'brand-New-pass-1',
                'passwordConfirmation' => 'something-else',
            ])
            ->assertJson(['status' => 'failed']);

        $this->assertTrue(Hash::check('password', $this->user->getPassword()));
    }

    public function testChangePasswordRequiresAuthentication(): void {
        $this->postJson('/oxygen/api/auth/change-password', [
            'oldPassword' => 'password', 'password' => 'x', 'passwordConfirmation' => 'x',
        ])->assertStatus(401);
    }

    // ---- destructive endpoints ---------------------------------------------

    public function testTerminateAccountRequiresAuthentication(): void {
        $this->postJson('/oxygen/api/auth/terminate-account')->assertStatus(401);
        $this->assertNotNull($this->entityManager()->find(User::class, $this->user->getId()));
    }

    public function testForceDeleteUserRemovesThemPermanently(): void {
        $victim = entity(User::class)->create(['group' => $this->user->getGroup()]);
        $id = $victim->getId();

        $this->actingAs($this->user)
            ->deleteJson("/oxygen/api/users/{$id}/force")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->entityManager()->clear();
        $this->assertNull($this->entityManager()->find(User::class, $id));
    }

    public function testForceDeleteUserRequiresPermission(): void {
        $noPerms = entity(User::class)->create(['group' => entity(Group::class)->create(['permissions' => []])]);
        $victim = entity(User::class)->create(['group' => $this->user->getGroup()]);

        $this->actingAs($noPerms)
            ->deleteJson("/oxygen/api/users/{$victim->getId()}/force")
            ->assertOk()
            ->assertJsonPath('status', 'failed'); // permission denial convention used by the API

        $this->entityManager()->clear();
        $this->assertNotNull($this->entityManager()->find(User::class, $victim->getId()));
    }

    private function entityManager(): \Doctrine\ORM\EntityManagerInterface {
        return $this->app->make(\Doctrine\ORM\EntityManagerInterface::class);
    }
}
