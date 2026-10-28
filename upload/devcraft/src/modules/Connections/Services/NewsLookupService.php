<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DcApi;
use Throwable;

/**
 * Поиск и заголовки новостей DLE через SDK (`DcApi::query('post')`).
 */
final class NewsLookupService {

	/**
	 * Проверяет существование новости по id.
	 */
	public static function exists(int $newsId): bool {
		if($newsId <= 0) {
			return false;
		}

		try {
			$row = DcApi::query('post')->find($newsId);
		} catch(Throwable) {
			return false;
		}

		return is_array($row) && !empty($row['id']);
	}

	public static function titleById(int $newsId): string {
		if($newsId <= 0) {
			return '';
		}

		try {
			$row = DcApi::query('post')
				->select(['id', 'title'])
				->where('id', (string) $newsId)
				->limit(1)
				->fetchAll();
		} catch(Throwable) {
			return '#' . $newsId;
		}

		$first = $row[0] ?? null;

		if(!is_array($first) || empty($first['id'])) {
			return '#' . $newsId;
		}

		$title = trim((string) ($first['title'] ?? ''));

		return $title !== '' ? $title : '#' . $newsId;
	}

	/**
	 * @return list<array{id:int, title:string}>
	 */
	public static function search(string $query, int $limit = 20): array {
		$query = trim($query);
		$limit = max(1, min(50, $limit));

		if($query === '') {
			return [];
		}

		$byId = [];

		try {
			if(ctype_digit($query)) {
				$row = DcApi::query('post')
					->select(['id', 'title'])
					->where('id', $query)
					->limit(1)
					->fetchAll();
				$first = $row[0] ?? null;

				if(is_array($first) && !empty($first['id'])) {
					$byId[(int) $first['id']] = [
						'id'    => (int) $first['id'],
						'title' => (string) ($first['title'] ?? ''),
					];
				}
			}

			/* Префикс % → LIKE (см. TableQuery::where). */
			$likeRows = DcApi::query('post')
				->select(['id', 'title'])
				->where('title', '%' . $query)
				->orderBy('id', 'DESC')
				->limit($limit)
				->fetchAll();
		} catch(Throwable) {
			return array_values($byId);
		}

		$map = $byId;

		foreach($likeRows as $row) {
			if(!is_array($row)) {
				continue;
			}

			$id = (int) ($row['id'] ?? 0);

			if($id <= 0 || isset($map[$id])) {
				continue;
			}

			$map[$id] = [
				'id'    => $id,
				'title' => (string) ($row['title'] ?? ''),
			];
		}

		$rows = array_values($map);
		usort($rows, static fn(array $a, array $b): int => $b['id'] <=> $a['id']);

		return array_slice($rows, 0, $limit);
	}

	/**
	 * @param list<int> $ids
	 *
	 * @return array<int, array{id:int, title:string, alt_name:string, category:string, date:string}>
	 */
	public static function postsByIds(array $ids): array {
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if($ids === []) {
			return [];
		}

		$map = [];

		try {
			/* TableQuery без whereIn: по одному find (кеш dle_api_query). */
			foreach($ids as $id) {
				$row = DcApi::query('post')->find($id);

				if(!is_array($row) || empty($row['id'])) {
					continue;
				}

				$map[$id] = [
					'id'       => $id,
					'title'    => (string) ($row['title'] ?? ''),
					'alt_name' => (string) ($row['alt_name'] ?? ''),
					'category' => (string) ($row['category'] ?? ''),
					'date'     => (string) ($row['date'] ?? ''),
				];
			}
		} catch(Throwable) {
			return $map;
		}

		return $map;
	}

}
