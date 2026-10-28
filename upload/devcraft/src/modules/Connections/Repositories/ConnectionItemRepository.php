<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\Connections\Models\ConnectionItem;

/**
 * Репозиторий элементов сборок.
 */
final class ConnectionItemRepository extends AbstractRepository {

	public function findOneById(int $id): ?ConnectionItem {
		/** @var ConnectionItem|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	public function findByCollectionAndNews(int $collectionId, int $newsId): ?ConnectionItem {
		/** @var ConnectionItem|null $entity */
		$entity = $this->select()
			->where('collection_id', $collectionId)
			->where('news_id', $newsId)
			->fetchOne();

		return $entity;
	}

	/**
	 * @return list<ConnectionItem>
	 */
	public function findByCollection(int $collectionId): array {
		/** @var list<ConnectionItem> $items */
		$items = $this->select()
			->where('collection_id', $collectionId)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $items;
	}

	/**
	 * @return list<ConnectionItem>
	 */
	public function findByNewsId(int $newsId): array {
		/** @var list<ConnectionItem> $items */
		$items = $this->select()
			->where('news_id', $newsId)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $items;
	}

	/**
	 * Элементы всех сборок, где встречается новость (для публичного блока).
	 *
	 * @return list<ConnectionItem>
	 */
	public function findVisibleInCollectionsWithNews(int $newsId): array {
		$collectionIds = [];

		foreach($this->findByNewsId($newsId) as $item) {
			$collectionIds[$item->collection_id] = true;
		}

		if($collectionIds === []) {
			return [];
		}

		/** @var list<ConnectionItem> $items */
		$items = $this->select()
			->where('collection_id', 'in', array_keys($collectionIds))
			->where('is_visible', true)
			->orderBy('collection_id', 'ASC')
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $items;
	}

	public function nextSortOrder(int $collectionId): int {
		$max = 0;

		foreach($this->findByCollection($collectionId) as $row) {
			$max = max($max, $row->sort_order);
		}

		return $max + 1;
	}

	public function deleteByCollection(int $collectionId): void {
		foreach($this->findByCollection($collectionId) as $item) {
			$this->deleteEntity($item);
		}
	}

}
