<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Exception\JsonResponseException;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\ItemService;
use Throwable;

/**
 * Удаление элемента.
 */
final class DeleteItemHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$collections = new CollectionService();
			$id          = (int) ($request->data['id'] ?? 0);
			$item        = $collections->itemsRepo()->findOneById($id);

			if($item === null) {
				return JsonResponse::fail(__('Ошибка'), __('Элемент не найден'), 'error', 404);
			}

			(new ItemService())->delete($item);

			return JsonResponse::toast(__('Элемент удалён'));
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
