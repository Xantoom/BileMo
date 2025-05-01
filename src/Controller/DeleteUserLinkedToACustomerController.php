<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/users/{id}/delete', name: 'api_delete_user_linked_to_a_customer', defaults: ['format' => 'json'], methods: ['DELETE'])]
class DeleteUserLinkedToACustomerController extends AbstractController
{
	public function __invoke(
		int $id,
		SerializerInterface $serializer,
		UserRepository $userRepository,
		CustomerRepository $customerRepository,
		UrlGeneratorInterface $urlGenerator,
		TagAwareCacheInterface $cache,
		UserPasswordHasherInterface $passwordHasher,
		EntityManagerInterface $entityManager
	): JsonResponse {
		// Check if user exists
		$user = $userRepository->find($id);
		if (!$user) {
			return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
		}

		// Check if user is linked to a customer
		$customer = $user->getCustomer();
		if (!$customer) {
			return new JsonResponse(['error' => 'User is not linked to any customer'], Response::HTTP_NOT_FOUND);
		}

		// Store customer ID before removing the user
		$customerId = $customer->getId();

		// Remove the user
		$entityManager->remove($user);
		$entityManager->flush();

		// Invalidate cache tags
		$cache->invalidateTags([
			'users',
			'user_' . $id,
			'customer_' . $customerId . '_users'
		]);

		// Return a 200 OK response with success message
		return new JsonResponse(['message' => 'User successfully deleted'], Response::HTTP_OK);
	}
}
