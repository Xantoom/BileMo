<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class GetAllProductsController extends AbstractController
{
	#[Route('/api/products', name: 'api_get_all_products', defaults: ['format' => 'json'], methods: ['GET'])]
	public function __invoke(
		Request $request,
		SerializerInterface $serializer,
		ProductRepository $productRepository,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache
	): JsonResponse {
		// Get pagination parameters
		$page = $request->query->getInt('page', 1);
		$limit = $request->query->getInt('limit', 10);

		// Create cache key based on request parameters
		$cacheKey = sprintf('products_page_%d_limit_%d', $page, $limit);

		// Get or create the cached response
		$responseData = $cache->get($cacheKey, function (ItemInterface $item) use ($page, $limit, $productRepository, $urlGenerator) {
			// Set cache lifetime to 1 hour
			$item->expiresAfter(3600);

			// Add cache tags for invalidation
			$item->tag(['products', 'products_list']);

			// Get paginated data
			$paginator = $productRepository->findAllPaginated($page, $limit);
			$products = iterator_to_array($paginator->getIterator());
			$totalItems = count($paginator);
			$totalPages = ceil($totalItems / $limit);

			// Build HATEOAS links
			$links = [];
			$links['self'] = $urlGenerator->generate('api_get_all_products', ['page' => $page, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['first'] = $urlGenerator->generate('api_get_all_products', ['page' => 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			$links['last'] = $urlGenerator->generate('api_get_all_products', ['page' => $totalPages, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);

			if ($page > 1) {
				$links['previous'] = $urlGenerator->generate('api_get_all_products', ['page' => $page - 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			}

			if ($page < $totalPages) {
				$links['next'] = $urlGenerator->generate('api_get_all_products', ['page' => $page + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL);
			}

			// Build response
			return [
				'data' => $products,
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
			->setMaxAge(3600)
			->setSharedMaxAge(3600)
			->setEtag(md5($json))
			->isNotModified($request)
		;

		return $response;
	}
}
