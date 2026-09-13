<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\Connections\Services\CollectionTypeService;
use DevCraft\Modules\Connections\Services\RelationResolutionService;
use DevCraft\Modules\ConnectionsAutomation\Models\ConnectionAutoCondition;
use DevCraft\Modules\ConnectionsAutomation\Models\ConnectionAutoRule;
use DevCraft\Modules\ConnectionsAutomation\Repositories\ConnectionAutoConditionRepository;
use DevCraft\Modules\ConnectionsAutomation\Repositories\ConnectionAutoRuleRepository;

/**
 * CRUD правил и условий автоматизации.
 */
final class AutoRuleService {

	public function __construct(
		private readonly PatternPresetService $presets = new PatternPresetService(),
	) {}

	public function rulesRepo(): ConnectionAutoRuleRepository {
		/** @var ConnectionAutoRuleRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionAutoRule::class);

		return $repo;
	}

	public function conditionsRepo(): ConnectionAutoConditionRepository {
		/** @var ConnectionAutoConditionRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionAutoCondition::class);

		return $repo;
	}

	/**
	 * @return list<array{id:int, category_id:int, rule_name:string, pattern_type:string, is_active:bool, sort_order:int}>
	 */
	public function listByCategory(int $categoryId): array {
		$rows = [];

		foreach($this->rulesRepo()->findByCategory($categoryId) as $rule) {
			$rows[] = $this->toArray($rule);
		}

		return $rows;
	}

	/**
	 * @return list<ConnectionAutoRule>
	 */
	public function listActiveByCategory(int $categoryId): array {
		return $this->rulesRepo()->findActiveByCategory($categoryId);
	}

	public function find(int $id): ?ConnectionAutoRule {
		return $this->rulesRepo()->findOneById($id);
	}

	/**
	 * Создаёт линейное правило (index_before / index_after).
	 *
	 * Пустые типы → имена из настроек Connections (`auto_label_before_id` / `auto_label_after_id`);
	 * если слоты не заданы — условия с пустым target (пользователь заполнит).
	 */
	public function createLinearRule(
		int $categoryId,
		string $ruleName = '',
		string $beforeType = '',
		string $afterType = '',
	): ConnectionAutoRule {
		$ruleName = trim($ruleName);

		if($ruleName === '') {
			$ruleName = __('Линейная');
		}

		$beforeType = trim($beforeType);
		$afterType  = trim($afterType);

		if($beforeType === '' || $afterType === '') {
			$labels = (new RelationResolutionService())->configuredAutoLabels();

			if($beforeType === '') {
				$beforeType = $labels['before'];
			}

			if($afterType === '') {
				$afterType = $labels['after'];
			}
		}

		$rule = $this->create(
			$categoryId,
			$ruleName,
			PatternPresetService::PATTERN_LINEAR,
			true,
			0,
			false,
		);

		$this->replaceConditions($rule->id(), [
			['condition_type' => 'index_before', 'target_relation_type' => $beforeType, 'sort_order' => 0],
			['condition_type' => 'index_after', 'target_relation_type' => $afterType, 'sort_order' => 1],
		]);

		return $rule;
	}

	public function create(
		int $categoryId,
		string $ruleName,
		string $patternType,
		bool $isActive = true,
		int $sortOrder = 0,
		bool $applyPreset = true,
	): ConnectionAutoRule {
		$categoryId = $this->validateCategoryId($categoryId);
		$ruleName   = $this->validateRuleName($ruleName);
		$patternType = $this->validatePattern($patternType);

		$rule               = new ConnectionAutoRule();
		$rule->category_id  = $categoryId;
		$rule->rule_name    = $ruleName;
		$rule->pattern_type = $patternType;
		$rule->is_active    = $isActive;
		$rule->sort_order   = max(0, $sortOrder);
		$this->rulesRepo()->saveEntity($rule);

		if($applyPreset && $patternType !== PatternPresetService::PATTERN_CUSTOM) {
			$this->applyPresetConditions($rule->id(), $patternType);
		}

		return $rule;
	}

	public function update(
		ConnectionAutoRule $rule,
		?string $ruleName = null,
		?string $patternType = null,
		?bool $isActive = null,
		?int $sortOrder = null,
		bool $reapplyPresetOnPatternChange = true,
	): ConnectionAutoRule {
		$oldPattern = $rule->pattern_type;

		if($ruleName !== null) {
			$rule->rule_name = $this->validateRuleName($ruleName);
		}

		if($patternType !== null) {
			$rule->pattern_type = $this->validatePattern($patternType);
		}

		if($isActive !== null) {
			$rule->is_active = $isActive;
		}

		if($sortOrder !== null) {
			$rule->sort_order = max(0, $sortOrder);
		}

		$this->rulesRepo()->saveEntity($rule);

		if(
			$reapplyPresetOnPatternChange
			&& $patternType !== null
			&& $patternType !== $oldPattern
			&& $patternType !== PatternPresetService::PATTERN_CUSTOM
		) {
			$this->applyPresetConditions($rule->id(), $patternType);
		}

		return $rule;
	}

	/**
	 * Активирует правило без деактивации соседних (FR-005a).
	 */
	public function setActive(ConnectionAutoRule $rule, bool $isActive): ConnectionAutoRule {
		$rule->is_active = $isActive;
		$this->rulesRepo()->saveEntity($rule);

		return $rule;
	}

	public function delete(ConnectionAutoRule $rule): void {
		$this->conditionsRepo()->deleteByRuleId($rule->id());
		$this->rulesRepo()->deleteEntity($rule);
	}

	/**
	 * Засевает слоты пресета (пустые target).
	 */
	public function applyPresetConditions(int $ruleId, string $patternType): void {
		$slots = $this->presets->defaultSlots($patternType);
		$rows  = [];
		$i     = 0;

		foreach($slots as $slot => $target) {
			$rows[] = [
				'condition_type'        => $slot,
				'target_relation_type'  => $target,
				'sort_order'            => $i++,
			];
		}

		$this->replaceConditions($ruleId, $rows);
	}

	/**
	 * @param list<array{condition_type:string, target_relation_type?:string, sort_order?:int}> $rows
	 */
	public function replaceConditions(int $ruleId, array $rows): void {
		if($ruleId <= 0) {
			JsonResponse::abort(__('Ошибка'), __('Некорректный id правила'), 'validation_failed', 422);
		}

		$this->conditionsRepo()->deleteByRuleId($ruleId);
		$seen = [];

		foreach($rows as $i => $row) {
			$type = trim((string) ($row['condition_type'] ?? ''));

			if($type === '') {
				continue;
			}

			if(isset($seen[$type])) {
				JsonResponse::abort(
					__('Ошибка'),
					__('Дублируется condition_type в правиле'),
					'validation_failed',
					422,
				);
			}

			$seen[$type] = true;
			$cond                        = new ConnectionAutoCondition();
			$cond->rule_id               = $ruleId;
			$cond->condition_type        = mb_substr($type, 0, 64);
			$cond->target_relation_type  = mb_substr(trim((string) ($row['target_relation_type'] ?? '')), 0, 100);
			$cond->sort_order            = (int) ($row['sort_order'] ?? $i);
			$this->conditionsRepo()->saveEntity($cond);
		}
	}

	/**
	 * @return list<array{id:int, rule_id:int, condition_type:string, target_relation_type:string, sort_order:int}>
	 */
	public function listConditions(int $ruleId): array {
		$out = [];

		foreach($this->conditionsRepo()->findByRuleId($ruleId) as $cond) {
			$out[] = [
				'id'                   => $cond->id(),
				'rule_id'              => $cond->rule_id,
				'condition_type'       => $cond->condition_type,
				'target_relation_type' => $cond->target_relation_type,
				'sort_order'           => $cond->sort_order,
			];
		}

		return $out;
	}

	/**
	 * @return array{id:int, category_id:int, rule_name:string, pattern_type:string, is_active:bool, sort_order:int}
	 */
	public function toArray(ConnectionAutoRule $rule): array {
		return [
			'id'           => $rule->id(),
			'category_id'  => $rule->category_id,
			'rule_name'    => $rule->rule_name,
			'pattern_type' => $rule->pattern_type,
			'is_active'    => $rule->is_active,
			'sort_order'   => $rule->sort_order,
		];
	}

	private function validateCategoryId(int $categoryId): int {
		if($categoryId <= 0) {
			JsonResponse::abort(__('Ошибка'), __('Укажите категорию сборки'), 'validation_failed', 422);
		}

		$types = (new CollectionTypeService())->list();
		$found = false;

		foreach($types as $type) {
			if((int) ($type['id'] ?? 0) === $categoryId) {
				$found = true;
				break;
			}
		}

		if(!$found) {
			JsonResponse::abort(__('Ошибка'), __('Категория сборки не найдена'), 'validation_failed', 422);
		}

		return $categoryId;
	}

	private function validateRuleName(string $ruleName): string {
		$ruleName = trim($ruleName);

		if($ruleName === '') {
			JsonResponse::abort(__('Ошибка'), __('Название правила не может быть пустым'), 'validation_failed', 422);
		}

		if(mb_strlen($ruleName) > 255) {
			JsonResponse::abort(__('Ошибка'), __('Название правила слишком длинное'), 'validation_failed', 422);
		}

		return $ruleName;
	}

	private function validatePattern(string $patternType): string {
		$patternType = trim($patternType);

		if(!$this->presets->isAllowedPattern($patternType)) {
			JsonResponse::abort(__('Ошибка'), __('Недопустимый тип паттерна'), 'validation_failed', 422);
		}

		return $patternType;
	}

}
