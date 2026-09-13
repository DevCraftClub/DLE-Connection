<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;
use DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\PairRelationService;
use DevCraft\Modules\ConnectionsAutomation\Models\ConnectionAutoRule;

/**
 * Генерация направленных пар по правилу автоматизации.
 */
final class AutomationRunnerService {

	public const string MODE_PRESERVE = 'preserve';

	public const string MODE_FULL_RESET = 'full_reset';

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
		private readonly PairRelationService $pairs = new PairRelationService(),
		private readonly AutoRuleService $rules = new AutoRuleService(),
		private readonly PatternPresetService $presets = new PatternPresetService(),
	) {}

	/**
	 * @return array{ok:bool, written:int, deleted:int, preserved_overrides:int, rule_id:int}
	 */
	public function runForCollection(int $collectionId, int $ruleId, string $mode = self::MODE_PRESERVE): array {
		$mode = $mode === self::MODE_FULL_RESET ? self::MODE_FULL_RESET : self::MODE_PRESERVE;

		if($collectionId <= 0 || $ruleId <= 0) {
			JsonResponse::abort(__('Ошибка'), __('Некорректные параметры запуска'), 'validation_failed', 422);
		}

		$collection = $this->collections->collectionsRepo()->findOneById($collectionId);

		if($collection === null) {
			JsonResponse::abort(__('Ошибка'), __('Сборка не найдена'), 'error', 404);
		}

		$rule = $this->rules->find($ruleId);

		if($rule === null) {
			JsonResponse::abort(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		if($collection->type_id > 0 && $rule->category_id !== $collection->type_id) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Правило не относится к категории этой сборки'),
				'validation_failed',
				422,
			);
		}

		$conditionMap = $this->conditionMap($rule);
		$this->assertCatalogTypes($conditionMap);

		$visibleItems = [];

		foreach($this->collections->itemsRepo()->findByCollection($collectionId) as $item) {
			if($item->is_visible) {
				$visibleItems[] = $item;
			}
		}

		$desired = $this->buildDesired($visibleItems, $rule->pattern_type, $conditionMap);
		$visibleNewsIds = [];

		foreach($visibleItems as $item) {
			$visibleNewsIds[$item->news_id] = true;
		}

		$db = Application::instance()->database()->connection();
		$db->begin();

		try {
			$written             = 0;
			$preservedOverrides  = 0;

			foreach($desired as $edge) {
				$result = $this->pairs->upsertFromAutomation(
					$collectionId,
					$edge['from_news_id'],
					$edge['to_news_id'],
					$edge['relation_type'],
					'',
					$mode === self::MODE_FULL_RESET,
					$mode === self::MODE_PRESERVE,
				);

				if($result === null) {
					$preservedOverrides++;
					continue;
				}

				$written++;
			}

			$deleted = $this->pairs->deletePairsNotInDesired(
				$collectionId,
				$desired,
				array_keys($visibleNewsIds),
				$mode === self::MODE_PRESERVE,
			);

			$db->commit();
		} catch(\Throwable $e) {
			$db->rollback();
			throw $e;
		}

		return [
			'ok'                   => true,
			'written'              => $written,
			'deleted'              => $deleted,
			'preserved_overrides'  => $preservedOverrides,
			'rule_id'              => $ruleId,
		];
	}

	/**
	 * @return array<string, string> condition_type → target_relation_type
	 */
	private function conditionMap(ConnectionAutoRule $rule): array {
		$map = [];

		foreach($this->rules->conditionsRepo()->findByRuleId($rule->id()) as $cond) {
			$map[$cond->condition_type] = $cond->target_relation_type;
		}

		return $map;
	}

	/**
	 * @param array<string, string> $conditionMap
	 */
	private function assertCatalogTypes(array $conditionMap): void {
		/** @var ConnectionRelationTypeRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionRelationType::class);
		$missing = [];

		foreach($conditionMap as $slot => $typeName) {
			$typeName = trim($typeName);

			if($typeName === '') {
				continue;
			}

			if($repo->findOneByName($typeName) === null) {
				$missing[] = $typeName . ' (' . $slot . ')';
			}
		}

		if($missing !== []) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Тип связи из условия отсутствует в каталоге: ') . implode(', ', $missing),
				'validation_failed',
				422,
			);
		}
	}

	/**
	 * @param list<\DevCraft\Modules\Connections\Models\ConnectionItem> $visibleItems
	 * @param array<string, string> $conditionMap
	 * @return list<array{from_news_id:int, to_news_id:int, relation_type:string}>
	 */
	private function buildDesired(array $visibleItems, string $patternType, array $conditionMap): array {
		$n = count($visibleItems);

		if($n < 2) {
			return [];
		}

		$desired = [];

		for($i = 0; $i < $n; $i++) {
			for($j = 0; $j < $n; $j++) {
				if($i === $j) {
					continue;
				}

				$slot = $this->presets->slotForPair($patternType, $i, $j);

				if($slot === null) {
					continue;
				}

				if(!array_key_exists($slot, $conditionMap)) {
					continue;
				}

				$type = trim($conditionMap[$slot]);

				if($type === '') {
					continue;
				}

				$desired[] = [
					'from_news_id'  => $visibleItems[$i]->news_id,
					'to_news_id'    => $visibleItems[$j]->news_id,
					'relation_type' => $type,
				];
			}
		}

		return $desired;
	}

}
