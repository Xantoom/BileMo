<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

	/**
	 * @param int $page
	 * @param int $limit
	 * @return Paginator<Product>
	 */
	public function findAllPaginated(int $page = 1, int $limit = 10): Paginator
	{
		$query = $this->createQueryBuilder('p')
			->orderBy('p.name', 'ASC')
			->getQuery()
			->setFirstResult(($page - 1) * $limit)
			->setMaxResults($limit)
		;

		return new Paginator($query);
	}
}
