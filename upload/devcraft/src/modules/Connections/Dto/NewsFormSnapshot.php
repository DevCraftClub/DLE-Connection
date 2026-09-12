<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Dto;

use RuntimeException;

/**
 * Снимок вкладки «Связи» на форме новости (POST dc_connections_snapshot).
 */
final class NewsFormSnapshot {

	public const VERSION = 1;

	/**
	 * @param list<array{
	 *     collection_id:?int,
	 *     temp_key:?string,
	 *     type_id?:int,
	 *     relation_type:string,
	 *     is_visible:bool,
	 *     items:list<array{news_id:int, relation_type:string, is_visible:bool, news_title?:string}>
	 * }> $memberships
	 * @param list<array{temp_key:string, title:string, description:?string, type_id?:int}> $newCollections
	 */
	public function __construct(
		public readonly int $version,
		public readonly array $memberships,
		public readonly array $newCollections,
	) {}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray(array $data): self {
		$version = (int) ($data['version'] ?? 0);

		if($version !== self::VERSION) {
			throw new RuntimeException(__('Неподдерживаемая версия снимка связей'));
		}

		$membershipsRaw = $data['memberships'] ?? null;
		$newRaw         = $data['new_collections'] ?? null;

		if(!is_array($membershipsRaw) || !is_array($newRaw)) {
			throw new RuntimeException(__('Некорректная структура снимка связей'));
		}

		$newCollections = [];
		$tempKeys       = [];

		foreach($newRaw as $row) {
			if(!is_array($row)) {
				throw new RuntimeException(__('Некорректная новая сборка в снимке'));
			}

			$tempKey = trim((string) ($row['temp_key'] ?? ''));
			$title   = trim((string) ($row['title'] ?? ''));

			if($tempKey === '' || $title === '') {
				throw new RuntimeException(__('Новая сборка: нужны temp_key и название'));
			}

			if(mb_strlen($title) > 255) {
				throw new RuntimeException(__('Название сборки слишком длинное'));
			}

			if(isset($tempKeys[$tempKey])) {
				throw new RuntimeException(__('Дубликат temp_key в новых сборках'));
			}

			$tempKeys[$tempKey] = true;
			$description        = array_key_exists('description', $row)
				? (is_string($row['description']) ? $row['description'] : null)
				: null;

			$newCollections[] = [
				'temp_key'    => $tempKey,
				'title'       => $title,
				'description' => $description !== null && trim($description) !== ''
					? trim($description)
					: null,
				'type_id'     => max(0, (int) ($row['type_id'] ?? 0)),
			];
		}

		$memberships = [];
		$seenRealIds = [];

		foreach($membershipsRaw as $row) {
			if(!is_array($row)) {
				throw new RuntimeException(__('Некорректное участие в снимке'));
			}

			$collectionId = array_key_exists('collection_id', $row) && $row['collection_id'] !== null && $row['collection_id'] !== ''
				? (int) $row['collection_id']
				: null;
			$tempKey = isset($row['temp_key']) && $row['temp_key'] !== null && $row['temp_key'] !== ''
				? trim((string) $row['temp_key'])
				: null;

			$hasReal = $collectionId !== null && $collectionId > 0;
			$hasTemp = $tempKey !== null && $tempKey !== '';

			if($hasReal === $hasTemp) {
				throw new RuntimeException(__('Участие: укажите либо collection_id, либо temp_key'));
			}

			if($hasTemp && !isset($tempKeys[$tempKey])) {
				throw new RuntimeException(__('temp_key не найден в new_collections'));
			}

			if($hasReal) {
				if(isset($seenRealIds[$collectionId])) {
					throw new RuntimeException(__('Дубликат collection_id в участиях'));
				}

				$seenRealIds[$collectionId] = true;
			}

			$relationType = trim((string) ($row['relation_type'] ?? ''));
			$isVisible    = !array_key_exists('is_visible', $row) || $row['is_visible'];
			$items        = self::parseItems($row['items'] ?? null, $relationType, $isVisible);

			$memberships[] = [
				'collection_id' => $hasReal ? $collectionId : null,
				'temp_key'      => $hasTemp ? $tempKey : null,
				'type_id'       => max(0, (int) ($row['type_id'] ?? 0)),
				'relation_type' => $relationType,
				'is_visible'    => $isVisible,
				'items'         => $items,
			];
		}

		return new self(self::VERSION, $memberships, $newCollections);
	}

	/**
	 * @return list<array{news_id:int, relation_type:string, is_visible:bool, comment:string, news_title?:string}>
	 */
	private static function parseItems(mixed $raw, string $fallbackType, bool $fallbackVisible): array {
		if(!is_array($raw) || $raw === []) {
			return [];
		}

		$items  = [];
		$seenId = [];

		foreach($raw as $row) {
			if(!is_array($row)) {
				continue;
			}

			$newsId = (int) ($row['news_id'] ?? 0);

			if($newsId < 0 || isset($seenId[$newsId])) {
				continue;
			}

			/* news_id=0 допустим для ещё не сохранённой новости в черновике. */
			$seenId[$newsId] = true;
			$entry           = [
				'news_id'       => $newsId,
				'relation_type' => array_key_exists('relation_type', $row)
					? trim((string) $row['relation_type'])
					: $fallbackType,
				'is_visible'    => array_key_exists('is_visible', $row)
					? (bool) $row['is_visible']
					: $fallbackVisible,
				'comment'       => array_key_exists('comment', $row)
					? trim((string) $row['comment'])
					: '',
			];
			$title = trim((string) ($row['news_title'] ?? ''));

			if($title !== '') {
				$entry['news_title'] = $title;
			}

			$items[] = $entry;
		}

		return $items;
	}

	/**
	 * @return array{version:int, memberships:list<array<string, mixed>>, new_collections:list<array<string, mixed>>}
	 */
	public function toArray(): array {
		return [
			'version'         => $this->version,
			'memberships'     => $this->memberships,
			'new_collections' => $this->newCollections,
		];
	}

}
