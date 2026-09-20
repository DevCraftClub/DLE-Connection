<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Support\ParseTemplateTags;
use DevCraft\Modules\Connections\Models\ConnectionPairRelation;

/**
 * Публичный вывод связей для полной новости.
 */
final class PublicTreeService {

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
		private readonly RelationResolutionService $resolver = new RelationResolutionService(),
		private readonly PairRelationService $pairs = new PairRelationService(),
	) {}

	/**
	 * Сборки, где есть $newsId: без текущей новости, только visible, пустые убрать.
	 *
	 * @param list<int>    $typeInclude      whitelist type_id (пусто = без whitelist)
	 * @param list<int>    $typeExclude      blacklist type_id
	 * @param string|null  $categorySlug     если задан — только категория с этим slug (исключает type_id=0)
	 * @param list<string> $categoryExclude  blacklist ярлыков категорий (как type_exclude, но по slug)
	 * @return list<array{
	 *     id:int,
	 *     title:string,
	 *     description:?string,
	 *     type_id:int,
	 *     category_slug:?string,
	 *     is_sequential:bool,
	 *     sort_order:int,
	 *     items:list<array<string, mixed>>
	 * }>
	 */
	public function forNews(
		int $newsId,
		array $typeInclude = [],
		array $typeExclude = [],
		?string $categorySlug = null,
		array $categoryExclude = [],
	): array {
		if($newsId <= 0) {
			return [];
		}

		$typeService = new CollectionTypeService();
		$slugFilter  = $categorySlug !== null ? trim($categorySlug) : '';
		$slugTypeId  = null;

		if($slugFilter !== '') {
			$type = $typeService->repo()->findOneBySlug($slugFilter);

			if($type === null) {
				return [];
			}

			$slugTypeId = $type->id();
		}

		$categoryExcludeTypeIds = [];

		if($slugTypeId === null && $categoryExclude !== []) {
			$slugToId = array_flip($typeService->slugMap());

			foreach($categoryExclude as $excludeSlug) {
				$key = trim((string) $excludeSlug);

				if($key !== '' && isset($slugToId[$key])) {
					$categoryExcludeTypeIds[(int) $slugToId[$key]] = (int) $slugToId[$key];
				}
			}

			$categoryExcludeTypeIds = array_values($categoryExcludeTypeIds);
		}

		$focusSortByCollection = [];

		foreach($this->collections->itemsRepo()->findByNewsId($newsId) as $focus) {
			$focusSortByCollection[$focus->collection_id] = $focus->sort_order;
		}

		if($focusSortByCollection === []) {
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

		$collectionIds = array_keys($byCollection);
		$pairMap       = $this->pairs->repo()->mapForFocus($collectionIds, $newsId);
		$typeSlugMap   = $typeService->slugMap();
		$posts         = NewsLookupService::postsByIds($newsIds);
		$tree          = [];

		foreach($this->collections->collectionsRepo()->findAllOrdered() as $collection) {
			$colId  = $collection->id();
			$typeId = (int) ($collection->type_id ?? 0);

			if($slugTypeId !== null) {
				if($typeId !== $slugTypeId) {
					continue;
				}
			} else {
				if($typeInclude !== [] && !in_array($typeId, $typeInclude, true)) {
					continue;
				}

				if($typeExclude !== [] && in_array($typeId, $typeExclude, true)) {
					continue;
				}

				if($categoryExcludeTypeIds !== [] && in_array($typeId, $categoryExcludeTypeIds, true)) {
					continue;
				}
			}

			if(!isset($byCollection[$colId]) || $byCollection[$colId] === []) {
				continue;
			}

			$focusSort = $focusSortByCollection[$colId] ?? null;

			if($focusSort === null) {
				continue;
			}

			$rowItems = [];

			foreach($byCollection[$colId] as $item) {
				$post = $posts[$item->news_id] ?? null;

				if($post === null) {
					continue;
				}

				/** @var ConnectionPairRelation|null $pair */
				$pair     = $pairMap[$colId][$item->news_id] ?? null;
				$resolved = $this->resolver->resolve(
					$pair,
					(bool) $collection->is_sequential,
					$focusSort,
					$item->sort_order,
				);

				$ts      = strtotime((string) $post['date']);
				$dateIso = ($ts !== false) ? date('Y-m-d', $ts) : '';

				$rowItems[] = [
					'id'            => $item->id(),
					'news_id'       => $item->news_id,
					'title'         => $post['title'],
					'alt_name'      => $post['alt_name'],
					'category'      => $post['category'],
					'date'          => $dateDisplay,
					'date_iso'      => $dateIso,
					'full_link'     => ParseTemplateTags::fullLink([
						'id'       => $item->news_id,
						'title'    => $post['title'],
						'alt_name' => $post['alt_name'],
						'category' => $post['category'],
						'date'     => $post['date'],
					]),
					'relation_type' => $resolved['relation_type'],
					'comment'       => $resolved['comment'],
				];
			}

			if($rowItems === []) {
				continue;
			}

			$tree[] = [
				'id'             => $colId,
				'title'          => $collection->title,
				'description'    => $collection->description,
				'type_id'        => $typeId,
				'category_slug'  => $typeId > 0 ? ($typeSlugMap[$typeId] ?? null) : null,
				'is_sequential'  => (bool) $collection->is_sequential,
				'sort_order'     => $collection->sort_order,
				'items'          => $rowItems,
			];
		}

		return $tree;
	}

}
