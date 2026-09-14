<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Support\DataManager;
use DevCraft\Modules\Connections\ConnectionsIdentity;
use DevCraft\Modules\Connections\Models\ConnectionCollection;
use DevCraft\Modules\Connections\Models\ConnectionItem;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionRepository;
use DevCraft\Modules\Connections\Repositories\ConnectionItemRepository;

/**
 * Доменная логика сборок связей.
 */
final class CollectionService {

	/**
	 * @return array<string, mixed>
	 */
	public function config(): array {
		$cfg = DataManager::getConfig(ConnectionsIdentity::code());

		return is_array($cfg) ? $cfg : [];
	}

	public function collectionsRepo(): ConnectionCollectionRepository {
		/** @var ConnectionCollectionRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionCollection::class);

		return $repo;
	}

	public function itemsRepo(): ConnectionItemRepository {
		/** @var ConnectionItemRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionItem::class);

		return $repo;
	}

	public function validateTitle(string $title): string {
		$title = trim($title);

		if($title === '') {
			JsonResponse::abort(
				__('Ошибка'),
				__('Название сборки не может быть пустым'),
				'validation_failed',
				422,
			);
		}

		if(mb_strlen($title) > 255) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Название сборки слишком длинное'),
				'validation_failed',
				422,
			);
		}

		return $title;
	}

	/**
	 * Пишет category id и саму категорию в связь Cycle.
	 * Без объекта категории ORM откатывает type_id (обратная HasMany).
	 */
	private function bindCollectionType(ConnectionCollection $collection, int $typeId): void {
		$normalized = (new CollectionTypeService())->normalizeTypeId($typeId);
		$collection->type_id = $normalized;
		$collection->collectionType = $normalized > 0
			? (new CollectionTypeService())->repo()->findOneById($normalized)
			: null;
	}

	public function create(
		string $title,
		?string $description = null,
		int $typeId = 0,
		bool $isSequential = true,
	): ConnectionCollection {
		$collection                = new ConnectionCollection();
		$collection->title         = $this->validateTitle($title);
		$collection->description   = $description !== null && trim($description) !== ''
			? trim($description)
			: null;
		$this->bindCollectionType($collection, $typeId);
		$collection->is_sequential = $isSequential;
		$collection->sort_order    = $this->collectionsRepo()->nextSortOrder();
		$this->collectionsRepo()->saveEntity($collection);

		return $collection;
	}

	public function update(
		ConnectionCollection $collection,
		string $title,
		?string $description = null,
		?int $typeId = null,
		?bool $isSequential = null,
	): ConnectionCollection {
		$collection->title       = $this->validateTitle($title);
		$collection->description = $description !== null && trim($description) !== ''
			? trim($description)
			: null;

		if($typeId !== null) {
			$this->bindCollectionType($collection, $typeId);
		}

		if($isSequential !== null) {
			$collection->is_sequential = $isSequential;
		}

		$this->collectionsRepo()->saveEntity($collection);

		return $collection;
	}

	/**
	 * Сбрасывает type_id сборок при удалении категории.
	 */
	public function clearTypeId(int $typeId): void {
		if($typeId <= 0) {
			return;
		}

		foreach($this->collectionsRepo()->findAllOrdered() as $collection) {
			if($collection->type_id !== $typeId) {
				continue;
			}

			$this->bindCollectionType($collection, 0);
			$this->collectionsRepo()->saveEntity($collection);
		}
	}

	public function delete(ConnectionCollection $collection): void {
		$id = $collection->id();
		(new PairRelationService($this))->repo()->deleteByCollection($id);
		$this->itemsRepo()->deleteByCollection($id);
		$this->collectionsRepo()->deleteEntity($collection);
	}

	/**
	 * @param list<int> $ids
	 */
	public function reorder(array $ids): void {
		$order = 1;

		foreach($ids as $id) {
			$collection = $this->collectionsRepo()->findOneById((int) $id);

			if($collection === null) {
				continue;
			}

			$collection->sort_order = $order++;
			$this->collectionsRepo()->saveEntity($collection);
		}
	}

	public function duplicate(ConnectionCollection $source): ConnectionCollection {
		$copy = $this->create(
			$source->title . ' (' . __('копия') . ')',
			$source->description,
			$source->type_id,
			$source->is_sequential,
		);

		foreach($this->itemsRepo()->findByCollection($source->id()) as $item) {
			$clone                 = new ConnectionItem();
			$clone->collection_id  = $copy->id();
			$clone->news_id        = $item->news_id;
			$clone->relation_type  = $item->relation_type;
			$clone->is_visible     = $item->is_visible;
			$clone->sort_order     = $item->sort_order;
			$this->itemsRepo()->saveEntity($clone);
		}

		return $copy;
	}

	/**
	 * Дерево сборок для админки.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function tree(?int $focusNewsId = null, ?array $contextByCollection = null): array {
		$newsTitles = [];
		$typeNames  = (new CollectionTypeService())->nameMap();
		$pairs      = new PairRelationService($this);
		$tree       = [];

		foreach($this->collectionsRepo()->findAllOrdered() as $collection) {
			$items      = [];
			$typeId     = (int) $collection->type_id;
			$colId      = $collection->id();
			$memberIds  = [];

			foreach($this->itemsRepo()->findByCollection($colId) as $item) {
				$memberIds[] = $item->news_id;
			}

			$contextNewsId = null;
			$forced        = is_array($contextByCollection)
				? (int) ($contextByCollection[$colId] ?? $contextByCollection[(string) $colId] ?? 0)
				: 0;

			if($forced > 0 && in_array($forced, $memberIds, true)) {
				$contextNewsId = $forced;
			} elseif($focusNewsId !== null && $focusNewsId > 0 && in_array($focusNewsId, $memberIds, true)) {
				$contextNewsId = $focusNewsId;
			} elseif($memberIds !== []) {
				$contextNewsId = $memberIds[0];
			}

			$pairByTo = [];

			if($contextNewsId !== null) {
				foreach($pairs->repo()->findByCollectionFrom($colId, $contextNewsId) as $pair) {
					$pairByTo[$pair->to_news_id] = $pair;
				}
			}

			foreach($this->itemsRepo()->findByCollection($colId) as $item) {
				$newsId = $item->news_id;

				if(!isset($newsTitles[$newsId])) {
					$newsTitles[$newsId] = NewsLookupService::titleById($newsId);
				}

				$isContext = $contextNewsId !== null && $newsId === $contextNewsId;
				$pair      = $pairByTo[$newsId] ?? null;
				$rtype     = '';
				$comment   = '';

				if(!$isContext && $pair !== null) {
					$rtype   = $pair->relation_type;
					$comment = $pair->comment;
				}

				$items[] = [
					'id'            => $item->id(),
					'news_id'       => $newsId,
					'news_title'    => $newsTitles[$newsId],
					'relation_type' => $rtype,
					'comment'       => $comment,
					'is_visible'    => $item->is_visible,
					'sort_order'    => $item->sort_order,
					'is_focus'      => $focusNewsId !== null && $focusNewsId === $newsId,
					'is_context'    => $isContext,
				];
			}

			$tree[] = [
				'id'              => $colId,
				'title'           => $collection->title,
				'description'     => $collection->description,
				'type_id'         => $typeId,
				'type_name'       => $typeId > 0 ? ($typeNames[$typeId] ?? null) : null,
				'is_sequential'   => (bool) $collection->is_sequential,
				'sort_order'      => $collection->sort_order,
				'context_news_id' => $contextNewsId,
				'items'           => $items,
			];
		}

		return $tree;
	}

}
