<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Exception\JsonResponseException;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\ConnectionsAutomation\Services\AutoRuleService;
use DevCraft\Modules\ConnectionsAutomation\Services\AutomationRunnerService;
use DevCraft\Modules\ConnectionsAutomation\Services\FeatureGateService;
use Throwable;

/**
 * Список правил для сборки и запуск автоматизации.
 */
final class RunAutomationHandler implements AjaxHandlerInterface {

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

			$method = (string) ($request->method ?? '');
			$action = (string) ($request->data['action'] ?? '');

			if($method === 'list_rules_for_collection' || $action === 'list_rules_for_collection') {
				return $this->listRules($request);
			}

			return $this->run($request);
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

	private function listRules(AjaxRequest $request): ResponseInterface {
		$collectionId = (int) ($request->data['collection_id'] ?? 0);
		$collection   = (new CollectionService())->collectionsRepo()->findOneById($collectionId);

		if($collection === null) {
			return JsonResponse::fail(__('Ошибка'), __('Сборка не найдена'), 'error', 404);
		}

		$active = (new AutoRuleService())->listActiveByCategory($collection->type_id);
		$rules  = [];

		foreach($active as $rule) {
			$rules[] = [
				'id'           => $rule->id(),
				'rule_name'    => $rule->rule_name,
				'pattern_type' => $rule->pattern_type,
				'is_active'    => $rule->is_active,
			];
		}

		return JsonResponse::ok([
			'rules'    => $rules,
			'conflict' => count($rules) > 1,
		]);
	}

	private function run(AjaxRequest $request): ResponseInterface {
		$collectionId = (int) ($request->data['collection_id'] ?? 0);
		$ruleId       = (int) ($request->data['rule_id'] ?? 0);
		$mode         = (string) ($request->data['mode'] ?? AutomationRunnerService::MODE_PRESERVE);

		if($ruleId <= 0) {
			$collection = (new CollectionService())->collectionsRepo()->findOneById($collectionId);

			if($collection === null) {
				return JsonResponse::fail(__('Ошибка'), __('Сборка не найдена'), 'error', 404);
			}

			$active = (new AutoRuleService())->listActiveByCategory($collection->type_id);

			if(count($active) === 1) {
				$ruleId = $active[0]->id();
			} elseif(count($active) > 1) {
				return JsonResponse::fail(
					__('Ошибка'),
					__('Выберите правило: для категории активно несколько правил'),
					'validation_failed',
					422,
				);
			} else {
				return JsonResponse::fail(
					__('Ошибка'),
					__('Нет активных правил для категории сборки'),
					'validation_failed',
					422,
				);
			}
		}

		$result = (new AutomationRunnerService())->runForCollection($collectionId, $ruleId, $mode);

		return JsonResponse::toast(__('Автоматизация выполнена'), $result);
	}

}
