<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
	private bool $isApiRequest = false;

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::REQUEST => ['onKernelRequest', 100],
			KernelEvents::EXCEPTION => ['onKernelException', 100],
		];
	}

	public function onKernelRequest(RequestEvent $event): void
	{
		$request = $event->getRequest();

		if (
			str_starts_with($request->getPathInfo(), '/api') ||
			$request->headers->get('Accept') === 'application/json' ||
			$request->getRequestFormat() === 'json'
		) {
			$this->isApiRequest = true;
			$request->setRequestFormat('json');
		}
	}

	public function onKernelException(ExceptionEvent $event): void
	{
		$request = $event->getRequest();

		if ($this->isApiRequest || $request->getRequestFormat() === 'json' || $request->headers->get('Accept') === 'application/json') {
			$exception = $event->getThrowable();
			$statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;

			$response = new JsonResponse([
				'status' => $statusCode,
				'message' => $exception->getMessage(),
				'error' => true,
				'path' => $request->getPathInfo()
			], $statusCode);

			if ($exception instanceof HttpExceptionInterface) {
				foreach ($exception->getHeaders() as $name => $value) {
					$response->headers->set($name, $value);
				}
			}

			$event->setResponse($response);
		}
	}
}
