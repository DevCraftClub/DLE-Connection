<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\Connections\Models\ConnectionItem;

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
			JsonResponse::abort(
				__('Ошибка'),
				__('Некорректные параметры элемента'),
				'validation_failed',
				422,
			);
		}

		$collection = $this->collections->collectionsRepo()->findOneById($collectionId);

		if($collection === null) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Сборка не найдена'),
				'not_found',
				404,
			);
		}

		if($this->collections->itemsRepo()->findByCollectionAndNews($collectionId, $newsId) !== null) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Новость уже есть в этой сборке'),
				'duplicate',
				409,
			);
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
		?int $newsId = null,
	): ConnectionItem {
		if($newsId !== null) {
			$newsId = (int) $newsId;

			if($newsId <= 0) {
				JsonResponse::abort(
					__('Ошибка'),
					__('Некорректный идентификатор новости'),
					'validation_failed',
					422,
				);
			}

			if($newsId !== $item->news_id) {
				$exists = $this->collections->itemsRepo()->findByCollectionAndNews(
					$item->collection_id,
					$newsId,
				);

				if($exists !== null && $exists->id() !== $item->id()) {
					JsonResponse::abort(
						__('Ошибка'),
						__('Новость уже есть в этой сборке'),
						'duplicate',
						409,
					);
				}

				$item->news_id = $newsId;
			}
		}

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
					JsonResponse::abort(
						__('Ошибка'),
						__('Новость уже есть в целевой сборке'),
						'duplicate',
						409,
					);
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
