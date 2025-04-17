<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
	private Generator $faker;
	private ObjectManager $manager;

	private array $users = [];
	private array $customers = [];
	private array $products = [];

	public function __construct(
		private readonly UserPasswordHasherInterface $passwordHasher,
	) {
	}

	public function load(ObjectManager $manager): void
    {
	    $this->faker = Faker\Factory::create('fr_FR');
		$this->manager = $manager;

	    $this->createCustomers();
	    $this->createUsers();
		$this->createProducts();
    }

	private function createCustomers(): void
	{
		for ($i = 0; $i < 16; $i++) {
			$customer = (new Customer())
				->setName($this->faker->company())
			;
			$this->manager->persist($customer);
			$this->customers[] = $customer;
		}

		$this->manager->flush();
	}

	private function createUsers(): void
	{
		$password = 'password';

		foreach ($this->customers as $customer) {
			for ($i = 0; $i < 4; $i++) {
				$user = (new User())
					->setEmail($this->faker->email())
					->setRoles(['ROLE_USER'])
					->setCustomer($customer)
				;
				$user->setPassword($this->passwordHasher->hashPassword($user, $password));
				$this->manager->persist($user);
				$this->users[] = $user;
			}
		}

		$this->manager->flush();
	}

	private function createProducts(): void
	{
		for ($i = 0; $i < 12; $i++) {
			$product = (new Product())
				->setName($this->faker->word())
				->setReference($this->faker->uuid())
				->setPrice($this->faker->randomNumber(3, true))
				->setBrand($this->faker->company())
				->setModel($this->faker->word())
				->setDescription($this->faker->text())
			;
			$this->manager->persist($product);
			$this->products[] = $product;
		}

		$this->manager->flush();
	}
}
