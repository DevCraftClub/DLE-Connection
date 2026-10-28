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
 * Порядок / перенос элементов между сборками.
 */
final class ReorderItemsHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$rows = $request->data['items'] ?? [];

			if(!is_array($rows)) {
				$rows = [];
			}

			$normalized = [];
			$touched    = [];

			foreach($rows as $row) {
				if(!is_array($row)) {
					continue;
				}

				$id            = (int) ($row['id'] ?? 0);
				$collectionId  = (int) ($row['collection_id'] ?? 0);
				$normalized[]  = [
					'id'            => $id,
					'collection_id' => $collectionId,
				];

				if($collectionId > 0) {
					$touched[$collectionId] = true;
				}

				if($id > 0) {
					$existing = (new CollectionService())->itemsRepo()->findOneById($id);

					if($existing !== null) {
						$touched[$existing->collection_id] = true;
					}
				}
			}

			(new ItemService())->reorder($normalized);

			$response = JsonResponse::toast(__('Порядок элементов сохранён'));

			// DevCraft ConnectionsAutomation: start
			foreach(array_keys($touched) as $collectionId) {
				$response = AutomationHostBridge::afterMembershipSaved((int) $collectionId, $response);
			}
			// DevCraft ConnectionsAutomation: end

			return $response;
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
