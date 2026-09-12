<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Exception\JsonResponseException;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\PairRelationService;
use Throwable;

/**
 * Список / upsert направленных пар связей.
 */
final class PairRelationHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$service = new PairRelationService();
			$action  = (string) ($request->data['action'] ?? 'list');

			return match($action) {
				'list' => $this->listPairs($service, $request),
				'upsert' => $this->upsert($service, $request),
				default => JsonResponse::fail(__('Ошибка'), __('Неизвестное действие'), 'error', 400),
			};
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

	private function listPairs(PairRelationService $service, AjaxRequest $request): ResponseInterface {
		$collectionId = (int) ($request->data['collection_id'] ?? 0);
		$fromNewsId   = (int) ($request->data['from_news_id'] ?? 0);

		return JsonResponse::ok([
			'items' => $service->listForContext($collectionId, $fromNewsId),
		]);
	}

	private function upsert(PairRelationService $service, AjaxRequest $request): ResponseInterface {
		$pair = $service->upsert(
			(int) ($request->data['collection_id'] ?? 0),
			(int) ($request->data['from_news_id'] ?? 0),
			(int) ($request->data['to_news_id'] ?? 0),
			(string) ($request->data['relation_type'] ?? ''),
			(string) ($request->data['comment'] ?? ''),
		);

		return JsonResponse::toast(__('Тип связи сохранён'), [
			'id'            => $pair->id(),
			'collection_id' => $pair->collection_id,
			'from_news_id'  => $pair->from_news_id,
			'to_news_id'    => $pair->to_news_id,
			'relation_type' => $pair->relation_type,
			'comment'       => $pair->comment,
		]);
	}

}
