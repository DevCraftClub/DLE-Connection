<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Support\DataManager;
use DevCraft\Modules\Connections\ConnectionsIdentity;
use DevCraft\Modules\Connections\Models\ConnectionCollection;
use DevCraft\Modules\Connections\Models\ConnectionItem;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionRepository;
use DevCraft\Modules\Connections\Repositories\ConnectionItemRepository;
use RuntimeException;

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

	public function isEnabled(): bool {
		$cfg = $this->config();

		return !empty($cfg['enabled']);
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
			throw new RuntimeException(__('Название сборки не может быть пустым'));
		}

		if(mb_strlen($title) > 255) {
			throw new RuntimeException(__('Название сборки слишком длинное'));
		}

		return $title;
	}

	public function create(string $title, ?string $description = null): ConnectionCollection {
		$collection              = new ConnectionCollection();
		$collection->title       = $this->validateTitle($title);
		$collection->description = $description !== null && trim($description) !== ''
			? trim($description)
			: null;
		$collection->sort_order  = $this->collectionsRepo()->nextSortOrder();
		$this->collectionsRepo()->saveEntity($collection);

		return $collection;
	}

	public function update(ConnectionCollection $collection, string $title, ?string $description = null): ConnectionCollection {
		$collection->title       = $this->validateTitle($title);
		$collection->description = $description !== null && trim($description) !== ''
			? trim($description)
			: null;
		$this->collectionsRepo()->saveEntity($collection);

		return $collection;
	}

	public function delete(ConnectionCollection $collection): void {
		$id = $collection->id();
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
	public function tree(?int $focusNewsId = null): array {
		$newsTitles = [];
		$tree       = [];

		foreach($this->collectionsRepo()->findAllOrdered() as $collection) {
			$items = [];

			foreach($this->itemsRepo()->findByCollection($collection->id()) as $item) {
				$newsId = $item->news_id;

				if(!isset($newsTitles[$newsId])) {
					$newsTitles[$newsId] = NewsLookupService::titleById($newsId);
				}

				$items[] = [
					'id'            => $item->id(),
					'news_id'       => $newsId,
					'news_title'    => $newsTitles[$newsId],
					'relation_type' => $item->relation_type,
					'is_visible'    => $item->is_visible,
					'sort_order'    => $item->sort_order,
					'is_focus'      => $focusNewsId !== null && $focusNewsId === $newsId,
				];
			}

			$tree[] = [
				'id'          => $collection->id(),
				'title'       => $collection->title,
				'description' => $collection->description,
				'sort_order'  => $collection->sort_order,
				'items'       => $items,
			];
		}

		return $tree;
	}

}
