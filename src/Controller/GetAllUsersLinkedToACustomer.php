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
	#[Route('/api/customers/{id}/users', name: 'api_get_customer_users', defaults: ['format' => 'json'], methods: ['GET'])]
	public function __invoke(
		int $id,
		Request $request,
		CustomerRepository $customerRepository,
		UserRepository $userRepository,
		SerializerInterface $serializer,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache
	): JsonResponse {
		// Check if customer exists
		$customer = $customerRepository->find($id);
		if (!$customer) {
			return new JsonResponse(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
		}

		$cacheTime = 60; // 1 minute

		// Get pagination parameters
		$page = $request->query->getInt('page', 1);
		$limit = $request->query->getInt('limit', 10);

		// Create cache key based on request parameters
		$cacheKey = sprintf('customer_%d_users_page_%d_limit_%d', $id, $page, $limit);

		// Get or create the cached response
		$responseData = $cache->get($cacheKey, function (ItemInterface $item) use ($cacheTime, $id, $page, $limit, $customer, $urlGenerator) {
			// Set cache lifetime to 1 hour
			$item->expiresAfter($cacheTime);

			// Add cache tags for invalidation
			$item->tag(['users', 'customer_' . $id . '_users']);

			// Get paginated users
			$users = $this->getPaginatedUsers($customer->getUsers()->toArray(), $page, $limit);
			$totalItems = count($customer->getUsers());
			$totalPages = ceil($totalItems / $limit);

			// Build HATEOAS links
			$links = [];
			$links['self'] = $urlGenerator->generate('api_get_customer_users', ['id' => $id, 'page' => $page, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['first'] = $urlGenerator->generate('api_get_customer_users', ['id' => $id, 'page' => 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['last'] = $urlGenerator->generate('api_get_customer_users', ['id' => $id, 'page' => $totalPages, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['customer'] = $urlGenerator->generate('api_get_product', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);

			if ($page > 1) {
				$links['previous'] = $urlGenerator->generate('api_get_customer_users', ['id' => $id, 'page' => $page - 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			}

			if ($page < $totalPages) {
				$links['next'] = $urlGenerator->generate('api_get_customer_users', ['id' => $id, 'page' => $page + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			}

			// Build response
			return [
				'data' => $users,
				'meta' => [
					'current_page' => $page,
					'per_page' => $limit,
					'total_items' => $totalItems,
					'total_pages' => $totalPages,
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

	/**
	 * Simple pagination for array
	 *
	 * @param array $users
	 * @param int $page
	 * @param int $limit
	 * @return array
	 */
	private function getPaginatedUsers(array $users, int $page, int $limit): array
	{
		$offset = ($page - 1) * $limit;
		return array_slice($users, $offset, $limit);
	}
}
