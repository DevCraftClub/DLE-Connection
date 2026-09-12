<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Modules\Connections\Models\ConnectionItem;
use RuntimeException;

/**
 * Доменная логика элементов сборок.
 */
final class ItemService {

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
	) {}

	public function add(
		int $collectionId,
		int $newsId,
		string $relationType = '',
		bool $isVisible = true,
	): ConnectionItem {
		if($collectionId <= 0 || $newsId <= 0) {
			throw new RuntimeException(__('Некорректные параметры элемента'));
		}

		$collection = $this->collections->collectionsRepo()->findOneById($collectionId);

		if($collection === null) {
			throw new RuntimeException(__('Сборка не найдена'));
		}

		if($this->collections->itemsRepo()->findByCollectionAndNews($collectionId, $newsId) !== null) {
			throw new RuntimeException(__('Новость уже есть в этой сборке'));
		}

		$item                 = new ConnectionItem();
		$item->collection_id  = $collectionId;
		$item->news_id        = $newsId;
		$item->relation_type  = trim($relationType);
		$item->is_visible     = $isVisible;
		$item->sort_order     = $this->collections->itemsRepo()->nextSortOrder($collectionId);
		$this->collections->itemsRepo()->saveEntity($item);

		return $item;
	}

	public function update(
		ConnectionItem $item,
		?string $relationType = null,
		?bool $isVisible = null,
	): ConnectionItem {
		if($relationType !== null) {
			$item->relation_type = trim($relationType);
		}

		if($isVisible !== null) {
			$item->is_visible = $isVisible;
		}

		$this->collections->itemsRepo()->saveEntity($item);

		return $item;
	}

	public function delete(ConnectionItem $item): void {
		$this->collections->itemsRepo()->deleteEntity($item);
	}

	public function toggleVisibility(ConnectionItem $item): ConnectionItem {
		$item->is_visible = !$item->is_visible;
		$this->collections->itemsRepo()->saveEntity($item);

		return $item;
	}

	/**
	 * Клонирует элемент в целевую сборку (по умолчанию — та же, если нет конфликта уникальности).
	 */
	public function duplicate(ConnectionItem $source, ?int $targetCollectionId = null): ConnectionItem {
		$target = $targetCollectionId ?? $source->collection_id;

		return $this->add(
			$target,
			$source->news_id,
			$source->relation_type,
			$source->is_visible,
		);
	}

	/**
	 * Перестановка / перенос элементов.
	 *
	 * @param list<array{id:int, collection_id:int}> $rows
	 */
	public function reorder(array $rows): void {
		$orderByCollection = [];

		foreach($rows as $row) {
			$id           = (int) ($row['id'] ?? 0);
			$collectionId = (int) ($row['collection_id'] ?? 0);

			if($id <= 0 || $collectionId <= 0) {
				continue;
			}

			$item = $this->collections->itemsRepo()->findOneById($id);

			if($item === null) {
				continue;
			}

			if($item->collection_id !== $collectionId) {
				$exists = $this->collections->itemsRepo()->findByCollectionAndNews(
					$collectionId,
					$item->news_id,
				);

				if($exists !== null && $exists->id() !== $item->id()) {
					throw new RuntimeException(__('Новость уже есть в целевой сборке'));
				}

				$item->collection_id = $collectionId;
			}

			if(!isset($orderByCollection[$collectionId])) {
				$orderByCollection[$collectionId] = 1;
			}

			$item->sort_order = $orderByCollection[$collectionId]++;
			$this->collections->itemsRepo()->saveEntity($item);
		}
	}

}
