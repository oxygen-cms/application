<?php

namespace App\Exceptions;

use Exception;
use Lang;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Oxygen\Core\Http\Notification;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        return parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e) {
        if($request->wantsJson()) {
            if(app('config')['app.debug']) {
                $status = $this->isHttpException($e) ? $e->getStatusCode() : 500;
                return new JsonResponse(['error' => [
                    'type' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]], $status);
            } else if ($e instanceof TokenMismatchException){
                $notification = new Notification(Lang::get('messages.invalidCSRFToken'), Notification::WARNING);
                return new JsonResponse($notification->toArray(), 500);
            }
        }

        return parent::render($request, $e);
    }
}
