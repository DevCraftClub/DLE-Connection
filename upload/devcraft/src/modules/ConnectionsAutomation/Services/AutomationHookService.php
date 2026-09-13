<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Services;

use DevCraft\Modules\Connections\Services\CollectionService;

/**
 * Хук после изменения состава/порядка сборки.
 */
final class AutomationHookService {

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
		private readonly AutoRuleService $rules = new AutoRuleService(),
		private readonly AutomationRunnerService $runner = new AutomationRunnerService(),
		private readonly PatternPresetService $presets = new PatternPresetService(),
	) {}

	/**
	 * 0 правил → no-op; 1 → run preserve; >1 → conflict без запуска.
	 *
	 * @return array{
	 *     automation_conflict?: bool,
	 *     automation_ran?: bool,
	 *     automation_result?: array<string, mixed>,
	 *     rules?: list<array{id:int, rule_name:string, pattern_type:string, is_active:bool}>
	 * }
	 */
	public function onCollectionMembershipSaved(int $collectionId): array {
		if($collectionId <= 0 || !FeatureGateService::isEnabled()) {
			return [];
		}

		$collection = $this->collections->collectionsRepo()->findOneById($collectionId);

		if($collection === null || $collection->type_id <= 0) {
			return [];
		}

		$active = $this->rules->listActiveByCategory($collection->type_id);
		$count  = count($active);

		if($count === 0) {
			return [];
		}

		$rulesPayload = [];

		foreach($active as $rule) {
			$rulesPayload[] = [
				'id'           => $rule->id(),
				'rule_name'    => $rule->rule_name,
				'pattern_type' => $rule->pattern_type,
				'is_active'    => $rule->is_active,
			];
		}

		if($count > 1) {
			return [
				'automation_conflict' => true,
				'rules'               => $rulesPayload,
			];
		}

		$result = $this->runner->runForCollection(
			$collectionId,
			$active[0]->id(),
			AutomationRunnerService::MODE_PRESERVE,
		);

		return [
			'automation_conflict' => false,
			'automation_ran'      => true,
			'automation_result'   => $result,
			'rules'               => $rulesPayload,
		];
	}

	/**
	 * Статус для UI-чипа сборки.
	 *
	 * @return array{enabled:bool, conflict:bool, label:string, active_count:int, rules:list<array<string,mixed>>}
	 */
	public function statusForCollection(int $collectionId): array {
		if(!FeatureGateService::isEnabled()) {
			return [
				'enabled'      => false,
				'conflict'     => false,
				'label'        => '',
				'active_count' => 0,
				'rules'        => [],
			];
		}

		$collection = $this->collections->collectionsRepo()->findOneById($collectionId);

		if($collection === null || $collection->type_id <= 0) {
			return [
				'enabled'      => true,
				'conflict'     => false,
				'label'        => __('Нет правил'),
				'active_count' => 0,
				'rules'        => [],
			];
		}

		$active = $this->rules->listActiveByCategory($collection->type_id);
		$count  = count($active);
		$rules  = [];

		foreach($active as $rule) {
			$rules[] = $this->rules->toArray($rule);
		}

		if($count === 0) {
			$label = __('Нет правил');
		} elseif($count > 1) {
			$label = __('Конфликт правил');
		} else {
			$label = $this->presets->patternLabel($active[0]->pattern_type);
		}

		return [
			'enabled'      => true,
			'conflict'     => $count > 1,
			'label'        => $label,
			'active_count' => $count,
			'rules'        => $rules,
		];
	}

}
