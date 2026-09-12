<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

/**
 * Публичный вывод связей для полной новости.
 */
final class PublicTreeService {

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
	) {}

	/**
	 * Сборки, где есть $newsId: без текущей новости, только visible, пустые убрать.
	 *
	 * @param list<int> $typeInclude  whitelist type_id (пусто = без whitelist)
	 * @param list<int> $typeExclude  blacklist type_id
	 * @return list<array{id:int, title:string, description:?string, type_id:int, items:list<array<string, mixed>>}>
	 */
	public function forNews(int $newsId, array $typeInclude = [], array $typeExclude = []): array {
		if($newsId <= 0 || !$this->collections->isEnabled()) {
			return [];
		}

		$items = $this->collections->itemsRepo()->findVisibleInCollectionsWithNews($newsId);

		if($items === []) {
			return [];
		}

		$byCollection = [];
		$newsIds      = [];

		foreach($items as $item) {
			if($item->news_id === $newsId) {
				continue;
			}

			$byCollection[$item->collection_id][] = $item;
			$newsIds[]                            = $item->news_id;
		}

		if($byCollection === []) {
			return [];
		}

		$posts = NewsLookupService::postsByIds($newsIds);
		$tree  = [];

		foreach($this->collections->collectionsRepo()->findAllOrdered() as $collection) {
			$colId  = $collection->id();
			$typeId = (int) ($collection->type_id ?? 0);

			if($typeInclude !== [] && !in_array($typeId, $typeInclude, true)) {
				continue;
			}

			if($typeExclude !== [] && in_array($typeId, $typeExclude, true)) {
				continue;
			}

			if(!isset($byCollection[$colId]) || $byCollection[$colId] === []) {
				continue;
			}

			$rowItems = [];

			foreach($byCollection[$colId] as $item) {
				$post = $posts[$item->news_id] ?? null;

				if($post === null) {
					continue;
				}

				$rowItems[] = [
					'id'            => $item->id(),
					'news_id'       => $item->news_id,
					'title'         => $post['title'],
					'alt_name'      => $post['alt_name'],
					'category'      => $post['category'],
					'date'          => $post['date'],
					'relation_type' => $item->relation_type,
				];
			}

			if($rowItems === []) {
				continue;
			}

			$tree[] = [
				'id'          => $colId,
				'title'       => $collection->title,
				'description' => $collection->description,
				'type_id'     => $typeId,
				'items'       => $rowItems,
			];
		}

		return $tree;
	}

}
