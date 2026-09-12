<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use Throwable;

/**
 * CRUD типов связей.
 */
final class RelationTypesHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$service = new RelationTypeService();
			$action  = (string) ($request->data['action'] ?? 'list');

			return match($action) {
				'list' => JsonResponse::ok(['items' => $service->list()]),
				'create' => JsonResponse::toast(__('Тип связи создан'), [
					'id'   => ($type = $service->create((string) ($request->data['name'] ?? '')))->id(),
					'name' => $type->name,
				]),
				'update' => $this->update($service, $request),
				'delete' => $this->delete($service, $request),
				'reorder' => $this->reorder($service, $request),
				default => JsonResponse::fail(__('Ошибка'), __('Неизвестное действие'), 'error', 400),
			};
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

	private function update(RelationTypeService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$type = $service->repo()->findOneById($id);

		if($type === null) {
			return JsonResponse::fail(__('Ошибка'), __('Тип связи не найден'), 'error', 404);
		}

		$service->update($type, (string) ($request->data['name'] ?? ''));

		return JsonResponse::toast(__('Тип связи сохранён'), [
			'id'   => $type->id(),
			'name' => $type->name,
		]);
	}

	private function delete(RelationTypeService $service, AjaxRequest $request): ResponseInterface {
		$id   = (int) ($request->data['id'] ?? 0);
		$type = $service->repo()->findOneById($id);

		if($type === null) {
			return JsonResponse::fail(__('Ошибка'), __('Тип связи не найден'), 'error', 404);
		}

		$service->delete($type);

		return JsonResponse::toast(__('Тип связи удалён'));
	}

	private function reorder(RelationTypeService $service, AjaxRequest $request): ResponseInterface {
		$ids = $request->data['ids'] ?? [];

		if(!is_array($ids)) {
			$ids = [];
		}

		$service->reorder(array_map('intval', $ids));

		return JsonResponse::toast(__('Порядок типов сохранён'));
	}

}
