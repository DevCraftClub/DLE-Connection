<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\Connections\Models\ConnectionCollectionType;

/**
 * Репозиторий категорий сборок.
 */
final class ConnectionCollectionTypeRepository extends AbstractRepository {

	public function findOneById(int $id): ?ConnectionCollectionType {
		/** @var ConnectionCollectionType|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	public function findOneByName(string $name): ?ConnectionCollectionType {
		/** @var ConnectionCollectionType|null $entity */
		$entity = $this->select()->where('name', $name)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<ConnectionCollectionType>
	 */
	public function findAllOrdered(): array {
		/** @var list<ConnectionCollectionType> $items */
		$items = $this->select()
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $items;
	}

	public function nextSortOrder(): int {
		$max = 0;

		foreach($this->findAllOrdered() as $row) {
			$max = max($max, $row->sort_order);
		}

		return $max + 1;
	}

}
