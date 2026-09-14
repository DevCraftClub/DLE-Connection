<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Controller;

use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\CollectionTypeService;
use DevCraft\Modules\Connections\Services\NewsLookupService;
use DevCraft\Modules\Connections\Services\PairRelationService;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use DevCraft\Modules\Connections\Services\TreeViewService;
use DevCraft\Modules\Connections\Support\AutomationHostBridge;

/**
 * Рендер Twig-виджета связей в форме новости (админка и публичное добавление).
 * UI: jQuery + application.js (DLEprompt/DLEconfirm) + jqueryui (DLEPush); без Metro.
 */
final class NewsFormController {

	public function render(int $newsId = 0): string {
		if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
			require_once \DLEPlugins::Check(ROOT_DIR . '/devcraft/init.php');
		}

		if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
			return '';
		}

		$collections = new CollectionService();
		$view        = new TreeViewService();
		$app         = Application::instance();
		$assetsBase  = $view->assetsBase();
		$publicJs    = dirname(__DIR__) . '/Public/connections_admin.js';
		$cssFile     = dirname(__DIR__) . '/Public/connections_admin.css';
		$vAdmin      = @filemtime($publicJs) ?: time();
		$vCss        = @filemtime($cssFile) ?: time();

		$snapshot = $this->buildSnapshot($newsId, $collections);

		$fullTree = $collections->tree($newsId > 0 ? $newsId : null);
		$treesById = [];
		$collectionList = [];

		foreach($fullTree as $col) {
			$colId = (int) $col['id'];
			$collectionList[] = [
				'id'    => $colId,
				'title' => (string) $col['title'],
			];
			/* Полное дерево для вкладки: соседи видны и после «Добавить в сборку». */
			$treesById[$colId] = $col;
		}

		$newsTitle = $newsId > 0
			? (NewsLookupService::titleById($newsId) ?: (__('Новость') . ' #' . $newsId))
			: __('Эта новость');
		$blocks    = $this->membershipBlocks($snapshot, $treesById, $newsTitle, $newsId);

		return $app->twig()->render('@connections/news_form_embed.twig', [
			'news_id'             => $newsId,
			'assets_base'         => $assetsBase,
			'admin_edit_news_url' => $view->adminEditNewsUrlTemplate(),
			'snapshot'            => $snapshot,
			'collections'         => $collectionList,
			'trees_by_id'         => $treesById,
			'relation_types'      => (new RelationTypeService())->list(),
			'collection_types'    => (new CollectionTypeService())->list(),
			'memberships_html'    => $view->renderNewsMemberships(
				$blocks,
				$assetsBase,
				AutomationHostBridge::isEnabled(),
			),
			'automation_enabled'  => AutomationHostBridge::isEnabled(),
			'v_js'                => $vAdmin,
			'v_css'               => $vCss,
		]);
	}

	/**
	 * Блоки сборок для вкладки: все элементы сборки, текущая новость видна и выделена.
	 *
	 * @param array{version:int, memberships:list<array<string, mixed>>, new_collections:list<array<string, mixed>>} $snapshot
	 * @param array<int, array<string, mixed>> $treesById
	 * @return list<array{row_key:string, collection_title:string, news_title:string, items:list<array<string, mixed>>}>
	 */
	public function membershipBlocks(
		array $snapshot,
		array $treesById,
		string $newsTitle,
		int $newsId,
	): array {
		$blocks    = [];
		$newTitles = [];
		$typeNames = (new CollectionTypeService())->nameMap();

		foreach($snapshot['new_collections'] ?? [] as $nc) {
			if(!is_array($nc)) {
				continue;
			}
			$key = (string) ($nc['temp_key'] ?? '');
			if($key !== '') {
				$newTitles[$key] = (string) ($nc['title'] ?? $key);
			}
		}

		foreach($snapshot['memberships'] ?? [] as $membership) {
			if(!is_array($membership)) {
				continue;
			}

			$tempKey = $membership['temp_key'] ?? null;
			$isTemp  = is_string($tempKey) && $tempKey !== '';
			$rowKey  = $isTemp
				? 't:' . $tempKey
				: 'c:' . (int) ($membership['collection_id'] ?? 0);

			$colId = (int) ($membership['collection_id'] ?? 0);
			$col   = !$isTemp ? ($treesById[$colId] ?? null) : null;

			$collectionTitle = $isTemp
				? ($newTitles[$tempKey] ?? $tempKey)
				: (string) ($col['title'] ?? ('#' . $colId));

			$typeId = 0;

			if(array_key_exists('type_id', $membership)) {
				$typeId = (int) $membership['type_id'];
			} elseif($isTemp) {
				foreach($snapshot['new_collections'] ?? [] as $ncRow) {
					if(is_array($ncRow) && (string) ($ncRow['temp_key'] ?? '') === $tempKey) {
						$typeId = (int) ($ncRow['type_id'] ?? 0);
						break;
					}
				}
			} else {
				$typeId = (int) ($col['type_id'] ?? 0);
			}

			$typeName = $typeId > 0
				? (string) ($col['type_name'] ?? $typeNames[$typeId] ?? '')
				: '';

			$items = [];

			if($isTemp) {
				$draftItems = $membership['items'] ?? null;
				if(is_array($draftItems) && $draftItems !== []) {
					foreach($draftItems as $di) {
						if(!is_array($di)) {
							continue;
						}
						$nid = (int) ($di['news_id'] ?? 0);
						$items[] = [
							'news_id'       => $nid,
							'news_title'    => ($nid === $newsId || $nid === 0)
								? $newsTitle
								: (string) ($di['news_title'] ?? ('#' . $nid)),
							'relation_type' => ($nid === $newsId || $nid === 0)
								? ''
								: (string) ($di['relation_type'] ?? ''),
							'is_visible'    => (bool) ($di['is_visible'] ?? true),
							'is_current'    => $nid === $newsId || $nid === 0,
						];
					}
				} else {
					$items[] = [
						'news_id'       => $newsId > 0 ? $newsId : 0,
						'news_title'    => $newsTitle,
						'relation_type' => '',
						'is_visible'    => (bool) ($membership['is_visible'] ?? true),
						'is_current'    => true,
					];
				}
			} else {
				$found = false;
				$draftItems = is_array($membership['items'] ?? null) ? $membership['items'] : null;
				$byNews = [];

				if(is_array($draftItems)) {
					foreach($draftItems as $di) {
						if(!is_array($di)) {
							continue;
						}
						$byNews[(int) ($di['news_id'] ?? 0)] = $di;
					}
				}

				foreach(($col['items'] ?? []) as $item) {
					if(!is_array($item)) {
						continue;
					}
					$nid       = (int) ($item['news_id'] ?? 0);
					$isCurrent = $newsId > 0 && $nid === $newsId;
					$override  = $byNews[$nid] ?? null;

					if($isCurrent) {
						$found = true;
					}

					$items[] = [
						'news_id'       => $nid,
						'news_title'    => $isCurrent && $newsTitle !== ''
							? $newsTitle
							: (string) ($item['news_title'] ?? ''),
						'relation_type' => $isCurrent
							? ''
							: (string) (
								(is_array($override) ? ($override['relation_type'] ?? null) : null)
								?? $item['relation_type']
								?? ''
							),
						'comment'       => $isCurrent
							? ''
							: (string) (
								(is_array($override) ? ($override['comment'] ?? null) : null)
								?? $item['comment']
								?? ''
							),
						'is_visible'    => (bool) (
							(is_array($override) && array_key_exists('is_visible', $override)
								? $override['is_visible']
								: null)
							?? ($isCurrent && array_key_exists('is_visible', $membership)
								? $membership['is_visible']
								: null)
							?? $item['is_visible']
							?? true
						),
						'is_current'    => $isCurrent,
					];
				}

				if(!$found) {
					$override = $byNews[$newsId] ?? $byNews[0] ?? null;
					$items[]  = [
						'news_id'       => $newsId,
						'news_title'    => $newsTitle,
						'relation_type' => '',
						'is_visible'    => (bool) (
							(is_array($override) && array_key_exists('is_visible', $override)
								? $override['is_visible']
								: null)
							?? $membership['is_visible']
							?? true
						),
						'is_current'    => true,
					];
				}
			}

			$blocks[] = [
				'row_key'          => $rowKey,
				'collection_title' => $collectionTitle,
				'type_id'          => $typeId,
				'type_name'        => $typeName !== '' ? $typeName : null,
				'news_title'       => $newsTitle,
				'is_draft'         => $isTemp,
				'items'            => $items,
			];
		}

		return $blocks;
	}

	/**
	 * @return array{version:int, memberships:list<array<string, mixed>>, new_collections:list<array<string, mixed>>}
	 */
	private function buildSnapshot(int $newsId, CollectionService $collections): array {
		$memberships = [];

		if($newsId > 0) {
			$typeByCollection = [];

			foreach($collections->collectionsRepo()->findAllOrdered() as $collection) {
				$typeByCollection[$collection->id()] = (int) ($collection->type_id ?? 0);
			}

			foreach($collections->itemsRepo()->findByNewsId($newsId) as $item) {
				$colItems = [];

				foreach($collections->itemsRepo()->findByCollection($item->collection_id) as $row) {
					$pair = null;

					if($row->news_id !== $newsId) {
						$pair = (new PairRelationService($collections))->repo()->findByCollectionFromTo(
							$item->collection_id,
							$newsId,
							$row->news_id,
						);
					}

					$colItems[] = [
						'news_id'       => $row->news_id,
						'relation_type' => $row->news_id === $newsId
							? ''
							: ($pair !== null ? $pair->relation_type : ''),
						'comment'       => $row->news_id === $newsId
							? ''
							: ($pair !== null ? $pair->comment : ''),
						'is_visible'    => $row->is_visible,
					];
				}

				$memberships[] = [
					'collection_id' => $item->collection_id,
					'temp_key'      => null,
					'type_id'       => $typeByCollection[$item->collection_id] ?? 0,
					'relation_type' => '',
					'is_visible'    => $item->is_visible,
					'items'         => $colItems,
				];
			}
		}

		return [
			'version'         => 1,
			'memberships'     => $memberships,
			'new_collections' => [],
		];
	}

}
