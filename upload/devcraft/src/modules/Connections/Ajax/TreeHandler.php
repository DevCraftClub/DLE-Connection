<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Exception\JsonResponseException;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\CollectionTypeService;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use DevCraft\Modules\Connections\Services\TreeViewService;
use Throwable;

/**
 * Дерево сборок (+ HTML + типы) для админки / формы новости.
 */
final class TreeHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		try {
			$focusNewsId = (int) ($request->data['news_id'] ?? 0);
			$contextsRaw = $request->data['contexts'] ?? null;
			$contexts    = [];

			if(is_array($contextsRaw)) {
				foreach($contextsRaw as $cid => $nid) {
					$contexts[(int) $cid] = (int) $nid;
				}
			}

			$view        = new TreeViewService();
			$assets      = $view->assetsBase();
			$tree        = (new CollectionService())->tree(
				$focusNewsId > 0 ? $focusNewsId : null,
				$contexts !== [] ? $contexts : null,
			);
			$relationTypes = (new RelationTypeService())->list();
			$collectionTypes = (new CollectionTypeService())->list();

			return JsonResponse::ok([
				'tree'             => $tree,
				'html'             => $view->renderTree($tree, $assets),
				'relation_types'   => $relationTypes,
				'collection_types' => $collectionTypes,
			]);
		} catch(JsonResponseException $e) {
			return $e->response();
		} catch(Throwable $e) {
			return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'error', 400);
		}
	}

}
