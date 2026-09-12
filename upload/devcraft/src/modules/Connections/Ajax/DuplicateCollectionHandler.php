<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\CollectionService;
use Throwable;

/**
 * Копирование сборки.
 */
final class DuplicateCollectionHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$service    = new CollectionService();
			$id         = (int) ($request->data['id'] ?? 0);
			$collection = $service->collectionsRepo()->findOneById($id);

			if($collection === null) {
				return JsonResponse::fail(__('Ошибка'), __('Сборка не найдена'), 'error', 404);
			}

			$copy = $service->duplicate($collection);

			return JsonResponse::toast(__('Сборка скопирована'), [
				'id'    => $copy->id(),
				'title' => $copy->title,
			]);
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
