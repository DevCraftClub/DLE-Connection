<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\Connections\Models\ConnectionCollection;

/**
 * Репозиторий сборок связей.
 */
final class ConnectionCollectionRepository extends AbstractRepository {

	public function findOneById(int $id): ?ConnectionCollection {
		/** @var ConnectionCollection|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<ConnectionCollection>
	 */
	public function findAllOrdered(): array {
		/** @var list<ConnectionCollection> $items */
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
