<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Controller;

use DevCraft\Modules\Connections\Services\PublicTreeService;

/**
 * Публичный блок связей на полной новости.
 */
final class FullstoryController {

	/**
	 * Рендерит HTML через DLE $tpl (list.tpl / item.tpl).
	 *
	 * @param string|null $typeInclude  whitelist id типов сборок через `,` (сырая строка include)
	 * @param string|null $typeExclude  blacklist id типов сборок через `,`
	 */
	public function render(int $newsId, ?string $typeInclude = null, ?string $typeExclude = null): string {
		global $tpl, $config;

		if($newsId <= 0) {
			return '';
		}

		$tree = (new PublicTreeService())->forNews(
			$newsId,
			self::parseIdList($typeInclude),
			self::parseIdList($typeExclude),
		);

		if($tree === []) {
			return '';
		}

		if(!isset($tpl) || !is_object($tpl)) {
			if(!class_exists('dle_template', false)) {
				require_once \DLEPlugins::Check(ENGINE_DIR . '/classes/templates.class.php');
			}
			$tpl = new \dle_template();
		}

		$skin = (string) ($config['skin'] ?? 'Default');
		$base = 'devcraft/connections/';

		if(!is_file(ROOT_DIR . '/templates/' . $skin . '/' . $base . 'list.tpl')) {
			$skin = 'Default';
		}

		$itemsHtml = '';

		foreach($tree as $collection) {
			$collectionItems = '';

			foreach($collection['items'] as $item) {
				$tpl->result['dc_conn_item'] = '';
				$tpl->load_template($base . 'item.tpl');
				$tpl->set('{title}', htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'));
				$tpl->set('{news-id}', (string) (int) $item['news_id']);
				$tpl->set('{relation-type}', htmlspecialchars((string) $item['relation_type'], ENT_QUOTES, 'UTF-8'));
				$tpl->set('{alt-name}', htmlspecialchars((string) $item['alt_name'], ENT_QUOTES, 'UTF-8'));
				$tpl->set('{category}', htmlspecialchars((string) $item['category'], ENT_QUOTES, 'UTF-8'));
				$tpl->set('{date}', htmlspecialchars((string) $item['date'], ENT_QUOTES, 'UTF-8'));
				$tpl->compile('dc_conn_item');
				$collectionItems .= (string) ($tpl->result['dc_conn_item'] ?? '');
			}

			$tpl->result['dc_conn_list'] = '';
			$tpl->load_template($base . 'list.tpl');
			$tpl->set('{collection-title}', htmlspecialchars((string) $collection['title'], ENT_QUOTES, 'UTF-8'));
			$tpl->set('{collection-description}', htmlspecialchars((string) ($collection['description'] ?? ''), ENT_QUOTES, 'UTF-8'));
			$tpl->set('{items}', $collectionItems);
			$tpl->compile('dc_conn_list');
			$itemsHtml .= (string) ($tpl->result['dc_conn_list'] ?? '');
		}

		return $itemsHtml;
	}

	/**
	 * Разбирает список id через запятую / пробел.
	 *
	 * @return list<int>
	 */
	private static function parseIdList(?string $raw): array {
		if($raw === null || trim($raw) === '') {
			return [];
		}

		$ids = [];

		foreach(preg_split('/[\s,]+/', $raw) ?: [] as $part) {
			$id = (int) $part;

			if($id > 0) {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

}
