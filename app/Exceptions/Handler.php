<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Oxygen\Auth\HandlesUnauthenticatedException;
use Oxygen\Data\Exception\InvalidEntityException;
use Oxygen\Core\Preferences\UsesViewErrorPathsFromTheme;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler {

    use UsesViewErrorPathsFromTheme;
    use HandlesUnauthenticatedException;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        HttpException::class,
        TokenMismatchException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        InvalidEntityException::class
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param Throwable $e
     * @return void
     * @throws Throwable
     */
    public function report(Throwable $e): void {
        if ($this->shouldReport($e) && config('app.debug') === false && app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
        parent::report($e);
    }

}
