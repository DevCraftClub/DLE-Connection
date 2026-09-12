<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

/**
 * Поиск и заголовки новостей DLE (post.id).
 */
final class NewsLookupService {

	public static function titleById(int $newsId): string {
		if($newsId <= 0) {
			return '';
		}

		global $db;

		if(!isset($db) || !is_object($db)) {
			return '#' . $newsId;
		}

		$newsId = (int) $newsId;
		$row    = $db->super_query(
			'SELECT id, title FROM ' . PREFIX . "_post WHERE id = '{$newsId}' LIMIT 1",
		);

		if(!is_array($row) || empty($row['id'])) {
			return '#' . $newsId;
		}

		$title = trim((string) ($row['title'] ?? ''));

		return $title !== '' ? $title : '#' . $newsId;
	}

	/**
	 * @return list<array{id:int, title:string}>
	 */
	public static function search(string $query, int $limit = 20): array {
		global $db;

		$query = trim($query);
		$limit = max(1, min(50, $limit));

		if($query === '' || !isset($db) || !is_object($db)) {
			return [];
		}

		$escaped = $db->safesql($query);
		$like    = $db->safesql('%' . $query . '%');
		$sql     = 'SELECT id, title FROM ' . PREFIX . '_post WHERE ';

		if(ctype_digit($query)) {
			$id  = (int) $query;
			$sql .= "id = '{$id}' OR title LIKE '{$like}'";
		} else {
			$sql .= "title LIKE '{$like}'";
		}

		$sql .= " ORDER BY id DESC LIMIT {$limit}";
		$db->query($sql);

		$rows = [];

		while($row = $db->get_row()) {
			$rows[] = [
				'id'    => (int) $row['id'],
				'title' => (string) $row['title'],
			];
		}

		$db->free();

		return $rows;
	}

	/**
	 * @param list<int> $ids
	 *
	 * @return array<int, array{id:int, title:string, alt_name:string, category:string, date:string}>
	 */
	public static function postsByIds(array $ids): array {
		global $db;

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if($ids === [] || !isset($db) || !is_object($db)) {
			return [];
		}

		$in = implode(',', $ids);
		$db->query(
			'SELECT id, title, alt_name, category, date FROM ' . PREFIX . "_post WHERE id IN ({$in})",
		);

		$map = [];

		while($row = $db->get_row()) {
			$id       = (int) $row['id'];
			$map[$id] = [
				'id'       => $id,
				'title'    => (string) $row['title'],
				'alt_name' => (string) ($row['alt_name'] ?? ''),
				'category' => (string) ($row['category'] ?? ''),
				'date'     => (string) ($row['date'] ?? ''),
			];
		}

		$db->free();

		return $map;
	}

}
