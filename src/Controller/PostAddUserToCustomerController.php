<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/customers/{id}/add-user', name: 'api_post_add_user_to_customer', defaults: ['format' => 'json'], methods: ['POST'])]
class PostAddUserToCustomerController extends AbstractController
{
	public function __invoke(
		int $id,
		Request $request,
		SerializerInterface $serializer,
		UserRepository $userRepository,
		CustomerRepository $customerRepository,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache,
		UserPasswordHasherInterface $passwordHasher,
	): JsonResponse {
		// Check if customer exists
		$customer = $customerRepository->find($id);
		if (!$customer) {
			return new JsonResponse(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
		}

		// Get user data from request
		$data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
		$keys = ['email', 'password'];
		foreach ($keys as $key) {
			if (!isset($data[$key])) {
				return new JsonResponse(['error' => sprintf('Missing key: %s', $key)], Response::HTTP_BAD_REQUEST);
			}
		}

		$email = $data['email'];
		$password = $data['password'];

		// Check if user already exists
		$user = $userRepository->findOneBy(['email' => $email]);
		if ($user) {
			return new JsonResponse(['error' => 'User already exists'], Response::HTTP_CONFLICT);
		}

		// Create new user
		$user = new User();
		$user->setEmail($email);
		$user->setPassword($passwordHasher->hashPassword($user, $password));
		$user->setCustomer($customer);

		// Save user to database
		$userRepository->save($user);
		$cache->invalidateTags(['users', 'customer_' . $id . '_users']);

		// Build HATEOAS links
		$links = [
			'self' => $urlGenerator->generate('api_get_user_linked_to_a_customer', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
			'customer' => $urlGenerator->generate('api_get_product', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
			'customer_users' => $urlGenerator->generate('api_get_customer_users', referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
		];

		// Build response
		$responseData = [
			'data' => [
				'id' => $user->getId(),
				'email' => $user->getEmail(),
				'roles' => $user->getRoles(),
				'customer_id' => $customer->getId(),
			],
			'_links' => $links
		];

		$context = [
			'circular_reference_handler' => function ($object) {
				return $object->getId();
			},
			'enable_max_depth' => true,
		];

		$json = $serializer->serialize($responseData, 'json', $context);
		$response = new JsonResponse($json, Response::HTTP_CREATED, [], true);

		// Add HTTP cache headers
		$response
			->setPublic()
			->setMaxAge(60)
			->setSharedMaxAge(60)
			->setEtag(md5($json))
			->isNotModified($request)
		;

		return $response;
	}
}
