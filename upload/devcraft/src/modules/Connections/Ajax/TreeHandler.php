<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use Throwable;

/**
 * Дерево сборок (+ типы связей) для админки / формы новости.
 */
final class TreeHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$focusNewsId = (int) ($request->data['news_id'] ?? 0);

			return JsonResponse::ok([
				'tree'           => (new CollectionService())->tree($focusNewsId > 0 ? $focusNewsId : null),
				'relation_types' => (new RelationTypeService())->list(),
			]);
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
