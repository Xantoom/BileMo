<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

	/**
	 * @param int $customerId
	 * @param int $page
	 * @param int $limit
	 * @return array
	 */
	public function findSimplifiedUsersByCustomerPaginated(int $customerId, int $page = 1, int $limit = 10): array
	{
		$qb = $this->createQueryBuilder('u')
			->select('u.id, u.email, u.roles')
			->andWhere('u.customer = :customerId')
			->setParameter('customerId', $customerId)
			->orderBy('u.email', 'ASC')
			->setFirstResult(($page - 1) * $limit)
			->setMaxResults($limit);

		$query = $qb->getQuery();

		// Get the total count for pagination metadata
		$countQb = $this->createQueryBuilder('u')
			->select('COUNT(u.id)')
			->andWhere('u.customer = :customerId')
			->setParameter('customerId', $customerId);

		$totalItems = (int)$countQb->getQuery()->getSingleScalarResult();
		$totalPages = ceil($totalItems / $limit);

		// Execute the query to get the simplified data
		$users = $query->getArrayResult();

		return [
			'users' => $users,
			'pagination' => [
				'total_items' => $totalItems,
				'total_pages' => (int) $totalPages,
				'current_page' => $page,
				'per_page' => $limit
			]
		];
	}

	public function save(User $user): void
	{
		$this->getEntityManager()->persist($user);
		$this->getEntityManager()->flush();
	}
}
