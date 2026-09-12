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
 * Порядок сборок.
 */
final class ReorderCollectionsHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$ids = $request->data['ids'] ?? [];

			if(!is_array($ids)) {
				$ids = [];
			}

			(new CollectionService())->reorder(array_map('intval', $ids));

			return JsonResponse::toast(__('Порядок сборок сохранён'));
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
