<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/users/{id}', name: 'api_get_user_linked_to_a_customer', defaults: ['format' => 'json'], methods: ['GET'])]
class GetUserLinkedToACustomerController extends AbstractController
{
	public function __invoke(
		int $id,
		UserRepository $userRepository,
		SerializerInterface $serializer,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache,
		Request $request
	): JsonResponse {
		$userConnected = $this->getUser();
		if (!$userConnected) {
			return new JsonResponse(['error' => 'User not connected'], Response::HTTP_UNAUTHORIZED);
		}

		$user = $userRepository->find($id);
		if (!$user) {
			return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
		}

		// Check if user is linked to a customer
		if (!$user->getCustomer()) {
			return new JsonResponse(['error' => 'User is not linked to any customer'], Response::HTTP_NOT_FOUND);
		}

		$userConnected = $userRepository->findOneBy(['email' => $userConnected->getUserIdentifier()]);
		if (!$userConnected) {
			return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
		}

		if (!$this->isGranted('ROLE_ADMIN')) {
			if (!$userConnected->getCustomer()) {
				return new JsonResponse(['error' => 'User not linked to any customer'], Response::HTTP_NOT_FOUND);
			}

			if ($userConnected->getCustomer()->getId() !== $user->getCustomer()->getId()) {
				return new JsonResponse(['error' => 'User not linked to the same customer'], Response::HTTP_FORBIDDEN);
			}
		}

		$cacheTime = 60; // 1 minute

		// Create cache key based on user ID
		$cacheKey = sprintf('user_%d', $user->getId());

		// Get or create the cached response
		$responseData = $cache->get($cacheKey, function (ItemInterface $item) use ($cacheTime, $user, $urlGenerator) {
			// Set cache lifetime
			$item->expiresAfter($cacheTime);

			// Add cache tags for invalidation
			$item->tag(['users', 'user_' . $user->getId(), 'customer_' . $user->getCustomer()?->getId() . '_users']);

			// Build HATEOAS links
			$links = [
				'self' => $urlGenerator->generate('api_get_user_linked_to_a_customer', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
				'customer' => $urlGenerator->generate('api_get_product', ['id' => $user->getCustomer()?->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
				'customer_users' => $urlGenerator->generate('api_get_customer_users', referenceType:  UrlGeneratorInterface::ABSOLUTE_URL),
			];

			// Build response
			return [
				'data' => [
					'id' => $user->getId(),
					'email' => $user->getEmail(),
					'roles' => $user->getRoles(),
					'customer_id' => $user->getCustomer()?->getId(),
				],
				'_links' => $links
			];
		});

		$json = $serializer->serialize($responseData, 'json');
		$response = new JsonResponse($json, Response::HTTP_OK, [], true);

		// Add HTTP cache headers
		$response
			->setPublic()
			->setMaxAge($cacheTime)
			->setSharedMaxAge($cacheTime)
			->setEtag(md5($json))
			->isNotModified($request);

		return $response;
	}
}
