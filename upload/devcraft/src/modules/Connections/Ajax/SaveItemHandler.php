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
use DevCraft\Modules\Connections\Services\NewsLookupService;
use DevCraft\Modules\Connections\Support\AutomationHostBridge;
use Throwable;

/**
 * Добавление / обновление элемента сборки.
 */
final class SaveItemHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$service      = new ItemService();
			$collections  = new CollectionService();
			$id           = (int) ($request->data['id'] ?? 0);
			$collectionId = (int) ($request->data['collection_id'] ?? 0);
			$newsId       = (int) ($request->data['news_id'] ?? 0);
			$relationType = (string) ($request->data['relation_type'] ?? '');
			$isVisible    = !array_key_exists('is_visible', $request->data) || !empty($request->data['is_visible']);

			if($id > 0) {
				$item = $collections->itemsRepo()->findOneById($id);

				if($item === null) {
					return JsonResponse::fail(__('Ошибка'), __('Элемент не найден'), 'error', 404);
				}

				$collectionId = $item->collection_id;
				$nextNewsId = array_key_exists('news_id', $request->data) ? $newsId : null;
				$nextType   = array_key_exists('relation_type', $request->data) ? $relationType : null;
				$nextVis    = array_key_exists('is_visible', $request->data) ? $isVisible : null;
				$service->update($item, $nextType, $nextVis, $nextNewsId > 0 ? $nextNewsId : null);
			} else {
				$item = $service->add($collectionId, $newsId, $relationType, $isVisible);
				$collectionId = $item->collection_id;
			}

			$response = JsonResponse::toast(__('Элемент сохранён'), [
				'id'         => $item->id(),
				'news_id'    => $item->news_id,
				'news_title' => NewsLookupService::titleById($item->news_id),
			]);

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
