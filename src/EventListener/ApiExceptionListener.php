<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'kernel.exception')]
class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = 500;
        $message = 'Internal Server Error';

        // 👇 Specific handling for common exception types
        if ($exception instanceof AccessDeniedException) {
            $statusCode = 403;
            $message = 'Access denied';
        } elseif ($exception instanceof NotFoundHttpException) {
            $statusCode = 404;
            $message = 'Not found';
        } elseif ($exception instanceof BadRequestHttpException) {
            $statusCode = 400;
            $message = 'Bad request';
        } elseif ($exception instanceof UnauthorizedHttpException) {
            $statusCode = 401;
            $message = 'Unauthorized';
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: $message;
        } else {
            // In dev environment, show actual error message
            if ($_ENV['APP_ENV'] !== 'prod') {
                $message = $exception->getMessage();
            }
        }

        // Optional: add trace or path in dev
        $responseData = [
            'error' => $message,
            'code' => $statusCode,
        ];

        $event->setResponse(new JsonResponse($responseData, $statusCode));
    }
}
