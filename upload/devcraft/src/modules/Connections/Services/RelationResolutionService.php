<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Modules\Connections\Models\ConnectionPairRelation;

/**
 * Единый authority для метки связи и comment относительно focus.
 */
final class RelationResolutionService {

	/**
	 * @param ConnectionPairRelation|null $pair  null = нет override-строки
	 * @return array{relation_type: string, comment: string}
	 */
	public function resolve(
		?ConnectionPairRelation $pair,
		bool $isSequential,
		int $focusSortOrder,
		int $targetSortOrder,
	): array {
		if($pair !== null) {
			return [
				'relation_type' => $pair->relation_type,
				'comment'       => $pair->comment,
			];
		}

		if($isSequential) {
			if($targetSortOrder < $focusSortOrder) {
				return [
					'relation_type' => __('Предыстория'),
					'comment'       => '',
				];
			}

			if($targetSortOrder > $focusSortOrder) {
				return [
					'relation_type' => __('Продолжение'),
					'comment'       => '',
				];
			}

			return [
				'relation_type' => '',
				'comment'       => '',
			];
		}

		return [
			'relation_type' => __('Связанный материал'),
			'comment'       => '',
		];
	}

}
