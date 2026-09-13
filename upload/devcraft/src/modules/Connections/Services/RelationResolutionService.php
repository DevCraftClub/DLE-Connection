<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Support\DataManager;
use DevCraft\Modules\Connections\ConnectionsIdentity;
use DevCraft\Modules\Connections\Models\ConnectionPairRelation;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;
use DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository;

/**
 * Единый authority для метки связи и comment относительно focus.
 *
 * Авто-подписи (без пары) берутся только из настроек модуля
 * (`auto_label_*_id` → имя типа из каталога). Хардкода продуктовых строк нет.
 */
final class RelationResolutionService {

	/** @var array<int, string> */
	private array $nameById = [];

	private bool $configLoaded = false;

	private int $beforeId = 0;

	private int $afterId = 0;

	private int $relatedId = 0;

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

		$this->ensureConfig();

		if($isSequential) {
			if($targetSortOrder < $focusSortOrder) {
				return [
					'relation_type' => $this->labelById($this->beforeId),
					'comment'       => '',
				];
			}

			if($targetSortOrder > $focusSortOrder) {
				return [
					'relation_type' => $this->labelById($this->afterId),
					'comment'       => '',
				];
			}

			return [
				'relation_type' => '',
				'comment'       => '',
			];
		}

		return [
			'relation_type' => $this->labelById($this->relatedId),
			'comment'       => '',
		];
	}

	/**
	 * Имена типов из настроек модуля (для сидов правил и UI).
	 *
	 * @return array{before: string, after: string, related: string}
	 */
	public function configuredAutoLabels(): array {
		$this->ensureConfig();

		return [
			'before'  => $this->labelById($this->beforeId),
			'after'   => $this->labelById($this->afterId),
			'related' => $this->labelById($this->relatedId),
		];
	}

	private function ensureConfig(): void {
		if($this->configLoaded) {
			return;
		}

		$cfg = DataManager::getConfig(ConnectionsIdentity::code());
		$cfg = is_array($cfg) ? $cfg : [];

		$this->beforeId  = (int) ($cfg['auto_label_before_id'] ?? 0);
		$this->afterId   = (int) ($cfg['auto_label_after_id'] ?? 0);
		$this->relatedId = (int) ($cfg['auto_label_related_id'] ?? 0);
		$this->configLoaded = true;
	}

	private function labelById(int $id): string {
		if($id <= 0) {
			return '';
		}

		if(array_key_exists($id, $this->nameById)) {
			return $this->nameById[$id];
		}

		try {
			/** @var ConnectionRelationTypeRepository $repo */
			$repo = Application::instance()->database()->repository(ConnectionRelationType::class);
			$type = $repo->findOneById($id);
			$name = $type !== null ? trim($type->name) : '';
		} catch(\Throwable) {
			$name = '';
		}

		$this->nameById[$id] = $name;

		return $name;
	}

}
