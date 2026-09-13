<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Exception\JsonResponseException;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use DevCraft\Modules\ConnectionsAutomation\Services\AutoRuleService;
use DevCraft\Modules\ConnectionsAutomation\Services\FeatureGateService;
use DevCraft\Modules\ConnectionsAutomation\Services\PatternPresetService;
use Throwable;

/**
 * CRUD правил и условий автоматизации.
 */
final class AutoRulesHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			if(!FeatureGateService::isEnabled()) {
				return JsonResponse::fail(
					__('Ошибка'),
					__('Модуль автоматизации связей недоступен'),
					'error',
					403,
				);
			}

			$service = new AutoRuleService();
			$action  = (string) ($request->data['action'] ?? 'list');

			return match($action) {
				'list'             => $this->listRules($service, $request),
				'get'              => $this->getRule($service, $request),
				'create'           => $this->create($service, $request),
				'create_linear'    => $this->createLinear($service, $request),
				'update'           => $this->update($service, $request),
				'delete'           => $this->delete($service, $request),
				'set_active'       => $this->setActive($service, $request),
				'apply_preset'     => $this->applyPreset($service, $request),
				'save_conditions'  => $this->saveConditions($service, $request),
				'relation_catalog' => $this->relationCatalog(),
				'presets'          => $this->presets(),
				default            => JsonResponse::fail(__('Ошибка'), __('Неизвестное действие'), 'error', 400),
			};
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

	private function listRules(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$categoryId = (int) ($request->data['category_id'] ?? 0);

		return JsonResponse::ok([
			'items' => $service->listByCategory($categoryId),
		]);
	}

	private function getRule(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$rule = $service->find($id);

		if($rule === null) {
			return JsonResponse::fail(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		return JsonResponse::ok([
			'rule'       => $service->toArray($rule),
			'conditions' => $service->listConditions($rule->id()),
		]);
	}

	private function create(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$rule = $service->create(
			(int) ($request->data['category_id'] ?? 0),
			(string) ($request->data['rule_name'] ?? ''),
			(string) ($request->data['pattern_type'] ?? PatternPresetService::PATTERN_LINEAR),
			!array_key_exists('is_active', $request->data) || !empty($request->data['is_active']),
			(int) ($request->data['sort_order'] ?? 0),
		);

		return JsonResponse::toast(__('Правило создано'), [
			'rule'       => $service->toArray($rule),
			'conditions' => $service->listConditions($rule->id()),
		]);
	}

	private function createLinear(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$rule = $service->createLinearRule(
			(int) ($request->data['category_id'] ?? 0),
			trim((string) ($request->data['rule_name'] ?? '')),
			trim((string) ($request->data['before_type'] ?? '')),
			trim((string) ($request->data['after_type'] ?? '')),
		);

		return JsonResponse::toast(__('Линейное правило создано'), [
			'rule'       => $service->toArray($rule),
			'conditions' => $service->listConditions($rule->id()),
		]);
	}

	private function update(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$rule = $service->find($id);

		if($rule === null) {
			return JsonResponse::fail(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		$rule = $service->update(
			$rule,
			array_key_exists('rule_name', $request->data) ? (string) $request->data['rule_name'] : null,
			array_key_exists('pattern_type', $request->data) ? (string) $request->data['pattern_type'] : null,
			array_key_exists('is_active', $request->data) ? !empty($request->data['is_active']) : null,
			array_key_exists('sort_order', $request->data) ? (int) $request->data['sort_order'] : null,
		);

		return JsonResponse::toast(__('Правило сохранено'), [
			'rule'       => $service->toArray($rule),
			'conditions' => $service->listConditions($rule->id()),
		]);
	}

	private function delete(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$rule = $service->find($id);

		if($rule === null) {
			return JsonResponse::fail(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		$service->delete($rule);

		return JsonResponse::toast(__('Правило удалено'));
	}

	private function setActive(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$rule = $service->find($id);

		if($rule === null) {
			return JsonResponse::fail(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		$rule = $service->setActive($rule, !empty($request->data['is_active']));

		return JsonResponse::toast(
			$rule->is_active ? __('Правило активировано') : __('Правило деактивировано'),
			['rule' => $service->toArray($rule)],
		);
	}

	private function applyPreset(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$rule = $service->find($id);

		if($rule === null) {
			return JsonResponse::fail(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		$pattern = (string) ($request->data['pattern_type'] ?? $rule->pattern_type);
		$rule    = $service->update($rule, null, $pattern, null, null, true);

		return JsonResponse::toast(__('Пресет применён'), [
			'rule'       => $service->toArray($rule),
			'conditions' => $service->listConditions($rule->id()),
		]);
	}

	private function saveConditions(AutoRuleService $service, AjaxRequest $request): ResponseInterface {
		$ruleId = (int) ($request->data['rule_id'] ?? 0);
		$rule   = $service->find($ruleId);

		if($rule === null) {
			return JsonResponse::fail(__('Ошибка'), __('Правило не найдено'), 'error', 404);
		}

		$conditions = $request->data['conditions'] ?? [];

		if(!is_array($conditions)) {
			$conditions = [];
		}

		/** @var list<array{condition_type:string, target_relation_type?:string, sort_order?:int}> $normalized */
		$normalized = [];

		foreach($conditions as $row) {
			if(!is_array($row)) {
				continue;
			}

			$normalized[] = [
				'condition_type'       => (string) ($row['condition_type'] ?? ''),
				'target_relation_type' => (string) ($row['target_relation_type'] ?? ''),
				'sort_order'           => (int) ($row['sort_order'] ?? 0),
			];
		}

		$service->replaceConditions($ruleId, $normalized);

		return JsonResponse::toast(__('Условия сохранены'), [
			'conditions' => $service->listConditions($ruleId),
		]);
	}

	private function relationCatalog(): ResponseInterface {
		return JsonResponse::ok([
			'items' => (new RelationTypeService())->list(),
		]);
	}

	private function presets(): ResponseInterface {
		$presets = new PatternPresetService();
		$items   = [];

		foreach($presets->allowedPatterns() as $pattern) {
			$items[] = [
				'pattern_type' => $pattern,
				'label'        => $presets->patternLabel($pattern),
				'slots'        => array_keys($presets->defaultSlots($pattern)),
			];
		}

		return JsonResponse::ok(['items' => $items]);
	}

}
