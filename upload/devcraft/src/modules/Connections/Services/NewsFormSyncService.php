<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Modules\Connections\Dto\NewsFormSnapshot;
use RuntimeException;

/**
 * Применение снимка вкладки «Связи» после успешного Save новости.
 */
final class NewsFormSyncService {

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
		private readonly ItemService $items = new ItemService(),
	) {}

	/**
	 * @param NewsFormSnapshot|array<string, mixed> $snapshot
	 */
	public function applySnapshot(int $newsId, NewsFormSnapshot|array $snapshot): void {
		if($newsId <= 0) {
			throw new RuntimeException(__('Некорректный id новости для связей'));
		}

		$dto = $snapshot instanceof NewsFormSnapshot
			? $snapshot
			: NewsFormSnapshot::fromArray($snapshot);

		$tempMap = [];

		foreach($dto->newCollections as $newCol) {
			$created = $this->collections->create(
				$newCol['title'],
				$newCol['description'],
			);
			$tempMap[$newCol['temp_key']] = $created->id();
		}

		$resolvedCollectionIds = [];
		$order                 = 0;

		foreach($dto->memberships as $membership) {
			$collectionId = $membership['collection_id'];

			if($collectionId === null) {
				$tempKey = $membership['temp_key'] ?? '';

				if($tempKey === '' || !isset($tempMap[$tempKey])) {
					throw new RuntimeException(__('Не удалось сопоставить temp_key сборки'));
				}

				$collectionId = $tempMap[$tempKey];
			}

			$currentMeta = $this->resolveCurrentMeta($membership, $newsId);

			$resolvedCollectionIds[$collectionId] = [
				'relation_type' => $currentMeta['relation_type'],
				'is_visible'    => $currentMeta['is_visible'],
				'sort_index'    => $order++,
				'items'         => $membership['items'] ?? [],
			];
		}

		$existing = $this->collections->itemsRepo()->findByNewsId($newsId);

		foreach($existing as $item) {
			if(!isset($resolvedCollectionIds[$item->collection_id])) {
				$this->items->delete($item);
			}
		}

		foreach($resolvedCollectionIds as $collectionId => $meta) {
			$item = $this->collections->itemsRepo()->findByCollectionAndNews(
				$collectionId,
				$newsId,
			);

			if($item === null) {
				$item = $this->items->add(
					$collectionId,
					$newsId,
					$meta['relation_type'],
					$meta['is_visible'],
				);
			} else {
				$this->items->update(
					$item,
					$meta['relation_type'],
					$meta['is_visible'],
				);
			}

			$item->sort_order = $meta['sort_index'] + 1;
			$this->collections->itemsRepo()->saveEntity($item);

			foreach($meta['items'] as $row) {
				if(!is_array($row)) {
					continue;
				}

				$siblingNewsId = (int) ($row['news_id'] ?? 0);

				if($siblingNewsId <= 0 || $siblingNewsId === $newsId) {
					continue;
				}

				$sibling = $this->collections->itemsRepo()->findByCollectionAndNews(
					$collectionId,
					$siblingNewsId,
				);

				if($sibling === null) {
					$this->items->add(
						$collectionId,
						$siblingNewsId,
						(string) ($row['relation_type'] ?? ''),
						(bool) ($row['is_visible'] ?? true),
					);
					continue;
				}

				$this->items->update(
					$sibling,
					(string) ($row['relation_type'] ?? ''),
					(bool) ($row['is_visible'] ?? true),
				);
			}

			/* Порядок элементов сборки из снимка (DnD на вкладке). */
			$sortRows = [];

			foreach($meta['items'] as $row) {
				if(!is_array($row)) {
					continue;
				}

				$rowNewsId = (int) ($row['news_id'] ?? 0);

				if($rowNewsId <= 0) {
					$rowNewsId = $newsId;
				}

				$ordered = $this->collections->itemsRepo()->findByCollectionAndNews(
					$collectionId,
					$rowNewsId,
				);

				if($ordered === null) {
					continue;
				}

				$sortRows[] = [
					'id'            => $ordered->id(),
					'collection_id' => $collectionId,
				];
			}

			if($sortRows !== []) {
				$this->items->reorder($sortRows);
			}
		}
	}

	/**
	 * @param array{
	 *     relation_type:string,
	 *     is_visible:bool,
	 *     items?:list<array{news_id:int, relation_type:string, is_visible:bool}>
	 * } $membership
	 * @return array{relation_type:string, is_visible:bool}
	 */
	private function resolveCurrentMeta(array $membership, int $newsId): array {
		foreach($membership['items'] ?? [] as $row) {
			if(!is_array($row)) {
				continue;
			}

			$rowNewsId = (int) ($row['news_id'] ?? -1);

			if($rowNewsId === $newsId || ($newsId > 0 && $rowNewsId === 0)) {
				return [
					/* Текущая новость не имеет типа связи к себе. */
					'relation_type' => '',
					'is_visible'    => (bool) ($row['is_visible'] ?? true),
				];
			}
		}

		return [
			'relation_type' => '',
			'is_visible'    => (bool) ($membership['is_visible'] ?? true),
		];
	}

}
