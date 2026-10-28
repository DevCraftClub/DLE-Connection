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
use DevCraft\Modules\Connections\Support\AutomationHostBridge;
use Throwable;

/**
 * Переключение видимости элемента.
 */
final class ToggleItemVisibilityHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$collections = new CollectionService();
			$id          = (int) ($request->data['id'] ?? 0);
			$item        = $collections->itemsRepo()->findOneById($id);

			if($item === null) {
				return JsonResponse::fail(__('Ошибка'), __('Элемент не найден'), 'error', 404);
			}

			$collectionId = $item->collection_id;
			$item         = (new ItemService())->toggleVisibility($item);

			$response = JsonResponse::toast(
				$item->is_visible ? __('Элемент показан') : __('Элемент скрыт'),
				['is_visible' => $item->is_visible],
			);

			// DevCraft ConnectionsAutomation: start
			$response = AutomationHostBridge::afterMembershipSaved($collectionId, $response);
			// DevCraft ConnectionsAutomation: end

			return $response;
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
