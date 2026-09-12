<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\ItemService;
use DevCraft\Modules\Connections\Services\NewsLookupService;
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
			$typeId      = array_key_exists('type_id', $request->data)
				? (int) $request->data['type_id']
				: null;

			if($id > 0) {
				$collection = $service->collectionsRepo()->findOneById($id);

				if($collection === null) {
					return JsonResponse::fail(__('Ошибка'), __('Сборка не найдена'), 'error', 404);
				}

				$service->update($collection, $title, $description, $typeId);
			} else {
				$newsId = (int) ($request->data['news_id'] ?? 0);

				if($newsId <= 0) {
					return JsonResponse::fail(
						__('Ошибка'),
						__('Выберите новость, от которой создаётся сборка'),
						'error',
						400,
					);
				}

				if(!NewsLookupService::exists($newsId)) {
					return JsonResponse::fail(__('Ошибка'), __('Новость не найдена'), 'error', 404);
				}

				$collection = $service->create($title, $description, $typeId ?? 0);
				(new ItemService($service))->add($collection->id(), $newsId);
			}

			return JsonResponse::toast(__('Сборка сохранена'), [
				'id'      => $collection->id(),
				'title'   => $collection->title,
				'type_id' => $collection->type_id,
			]);
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
