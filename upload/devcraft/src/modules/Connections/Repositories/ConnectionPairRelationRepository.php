<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\Connections\Models\ConnectionPairRelation;

/**
 * Репозиторий направленных пар связей.
 */
final class ConnectionPairRelationRepository extends AbstractRepository {

	public function findOneById(int $id): ?ConnectionPairRelation {
		/** @var ConnectionPairRelation|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	public function findByCollectionFromTo(
		int $collectionId,
		int $fromNewsId,
		int $toNewsId,
	): ?ConnectionPairRelation {
		/** @var ConnectionPairRelation|null $entity */
		$entity = $this->select()
			->where('collection_id', $collectionId)
			->where('from_news_id', $fromNewsId)
			->where('to_news_id', $toNewsId)
			->fetchOne();

		return $entity;
	}

	/**
	 * @return list<ConnectionPairRelation>
	 */
	public function findByCollectionFrom(int $collectionId, int $fromNewsId): array {
		/** @var list<ConnectionPairRelation> $rows */
		$rows = $this->select()
			->where('collection_id', $collectionId)
			->where('from_news_id', $fromNewsId)
			->fetchAll();

		return $rows;
	}

	/**
	 * Карта to_news_id → pair для нескольких сборок и одного контекста.
	 *
	 * @param list<int> $collectionIds
	 * @return array<int, array<int, ConnectionPairRelation>> collection_id → to_news_id → pair
	 */
	public function mapForFocus(array $collectionIds, int $fromNewsId): array {
		if($collectionIds === [] || $fromNewsId <= 0) {
			return [];
		}

		/** @var list<ConnectionPairRelation> $rows */
		$rows = $this->select()
			->where('collection_id', 'in', $collectionIds)
			->where('from_news_id', $fromNewsId)
			->fetchAll();

		$map = [];

		foreach($rows as $row) {
			$map[$row->collection_id][$row->to_news_id] = $row;
		}

		return $map;
	}

	public function deleteByCollection(int $collectionId): void {
		/** @var list<ConnectionPairRelation> $rows */
		$rows = $this->select()->where('collection_id', $collectionId)->fetchAll();

		foreach($rows as $row) {
			$this->deleteEntity($row);
		}
	}

}
