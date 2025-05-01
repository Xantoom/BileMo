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

class GetProductController extends AbstractController
{
	#[Route('/api/products/{id}', name: 'api_get_product', defaults: ['format' => 'json'], methods: ['GET'])]
	public function __invoke(
		int $id,
		ProductRepository $productRepository,
		SerializerInterface $serializer,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache,
		Request $request
	): JsonResponse {
		$product = $productRepository->find($id);
		if (!$product) {
			return new JsonResponse(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
		}

		$cacheTime = 24 * 3600; // 24 hours

		// Create cache key based on product ID
		$cacheKey = sprintf('product_%d', $product->getId());

		// Get or create the cached response
		$responseData = $cache->get($cacheKey, function (ItemInterface $item) use ($cacheTime, $product, $urlGenerator) {
			// Set cache lifetime to 24 hours
			$item->expiresAfter($cacheTime);

			// Add cache tags for invalidation
			$item->tag(['products', 'product_' . $product->getId()]);

			// Build HATEOAS links
			$links = [
				'self' => $urlGenerator->generate('api_get_product', ['id' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
				'collection' => $urlGenerator->generate('api_get_all_products', [], UrlGeneratorInterface::ABSOLUTE_URL),
			];

			// Build response
			return [
				'data' => $product,
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
