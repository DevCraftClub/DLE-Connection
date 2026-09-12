<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\ItemService;
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

			foreach($rows as $row) {
				if(!is_array($row)) {
					continue;
				}

				$normalized[] = [
					'id'            => (int) ($row['id'] ?? 0),
					'collection_id' => (int) ($row['collection_id'] ?? 0),
				];
			}

			(new ItemService())->reorder($normalized);

			return JsonResponse::toast(__('Порядок элементов сохранён'));
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
