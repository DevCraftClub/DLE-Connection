<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\NewsLookupService;
use Throwable;

/**
 * Поиск новостей для добавления в сборку.
 */
final class SearchNewsHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$query = (string) ($request->data['q'] ?? $request->data['query'] ?? '');
			$limit = (int) ($request->data['limit'] ?? 20);

			return JsonResponse::ok([
				'items' => NewsLookupService::search($query, $limit),
			]);
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
