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
 * Создание / переименование сборки.
 */
final class SaveCollectionHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$service     = new CollectionService();
			$id          = (int) ($request->data['id'] ?? 0);
			$title       = (string) ($request->data['title'] ?? '');
			$description = array_key_exists('description', $request->data)
				? (string) $request->data['description']
				: null;

			if($id > 0) {
				$collection = $service->collectionsRepo()->findOneById($id);

				if($collection === null) {
					return JsonResponse::fail(__('Ошибка'), __('Сборка не найдена'), 'error', 404);
				}

				$service->update($collection, $title, $description);
			} else {
				$collection = $service->create($title, $description);
			}

			return JsonResponse::toast(__('Сборка сохранена'), [
				'id'    => $collection->id(),
				'title' => $collection->title,
			]);
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
