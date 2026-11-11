<?php

namespace Tests\Feature;

use App\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Laravel skips CSRF checks while running tests, so the HTTP tests can't exercise it. This checks the Sanctum 4
 * configuration/wiring and that the middleware itself enforces tokens when it is not skipped.
 */
class CsrfProtectionTest extends TestCase {

    public function testSanctumUsesTheSanctum4MiddlewareKeys(): void {
        $this->assertSame(ValidateCsrfToken::class, config('sanctum.middleware.validate_csrf_token'));
        $this->assertNull(config('sanctum.middleware.verify_csrf_token'));
        $this->assertSame(\Laravel\Sanctum\Http\Middleware\AuthenticateSession::class, config('sanctum.middleware.authenticate_session'));
    }

    public function testTheWebGroupIncludesCsrfValidation(): void {
        $groups = (new \ReflectionProperty(Kernel::class, 'middlewareGroups'))->getValue($this->app->make(Kernel::class));

        $this->assertContains(ValidateCsrfToken::class, $groups['web']);
    }

    private function enforcing(): ValidateCsrfToken {
        return new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests() { return false; } // switch off Laravel's "skip in tests" behaviour
        };
    }

    private function postRequest(?string $token): Request {
        $request = Request::create('/oxygen/api/auth/login', 'POST');
        $session = new Store('test', new \Illuminate\Session\ArraySessionHandler(10));
        $session->start();
        $session->put('_token', 'the-real-token');
        $request->setLaravelSession($session);
        if($token !== null) {
            $request->headers->set('X-CSRF-TOKEN', $token);
        }
        return $request;
    }

    public function testMissingOrWrongTokenIsRejected(): void {
        foreach([null, 'wrong-token'] as $token) {
            try {
                $this->enforcing()->handle($this->postRequest($token), fn() => response('ok'));
                $this->fail('request without a valid CSRF token was accepted');
            } catch(TokenMismatchException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMatchingTokenIsAccepted(): void {
        $response = $this->enforcing()->handle($this->postRequest('the-real-token'), fn() => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }
}
