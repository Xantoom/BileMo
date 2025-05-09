<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
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

class GetAllUsersLinkedToACustomer extends AbstractController
{
	#[Route('/api/customers/get-users', name: 'api_get_customer_users', defaults: ['format' => 'json'], methods: ['GET'])]
	public function __invoke(
		Request $request,
		CustomerRepository $customerRepository,
		UserRepository $userRepository,
		SerializerInterface $serializer,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache
	): JsonResponse {
		$userConnected = $this->getUser();
		if (!$userConnected) {
			return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
		}

		$user = $userRepository->findOneBy(['email' => $userConnected->getUserIdentifier()]);
		if (!$user) {
			return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
		}

		if (!$user->getCustomer()) {
			return new JsonResponse(['error' => 'User does not have a customer'], Response::HTTP_FORBIDDEN);
		}

		$id = $user->getCustomer()->getId();

		$cacheTime = 60; // 1 minute

		// Get pagination parameters
		$page = $request->query->getInt('page', 1);
		$limit = $request->query->getInt('limit', 10);

		// Create cache key based on request parameters
		$cacheKey = sprintf('customer_%d_users_page_%d_limit_%d', $id, $page, $limit);

		// Get or create the cached response
		$responseData = $cache->get($cacheKey, function (ItemInterface $item) use (
			$cacheTime,
			$id,
			$page,
			$limit,
			$userRepository,
			$urlGenerator
		) {
			// Set cache lifetime
			$item->expiresAfter($cacheTime);

			// Add cache tags for invalidation
			$item->tag(['users', 'customer_' . $id . '_users']);

			// Get simplified users directly from repository
			$result = $userRepository->findSimplifiedUsersByCustomerPaginated($id, $page, $limit);
			$users = $result['users'];
			$pagination = $result['pagination'];

			// Build HATEOAS links
			$links = [];
			$links['self'] = $urlGenerator->generate('api_get_customer_users', ['page' => $page, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['first'] = $urlGenerator->generate('api_get_customer_users', ['page' => 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['last'] = $urlGenerator->generate('api_get_customer_users', ['page' => $pagination['total_pages'], 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);

			if ($page > 1) {
				$links['previous'] = $urlGenerator->generate('api_get_customer_users', ['page' => $page - 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			}

			if ($page < $pagination['total_pages']) {
				$links['next'] = $urlGenerator->generate('api_get_customer_users', ['page' => $page + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			}

			// Build response
			return [
				'data' => $users,
				'meta' => $pagination,
				'_links' => $links
			];
		});

		// Serialize the response data
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
