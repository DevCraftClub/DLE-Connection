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
	 * Набор шаблонов: `templates/Air/devcraft/connections/` (по умолчанию, DLE 21)
	 * или `templates/Air/devcraft/connections/{template}/` при параметре template.
	 *
	 * @param string|null $typeInclude       whitelist id типов сборок через `,`
	 * @param string|null $typeExclude       blacklist id типов сборок через `,`
	 * @param string|null $categorySlug      фильтр по ярлыку категории (param `category` в include)
	 * @param string|null $template          подпапка набора tpl (param `template`)
	 * @param string|null $categoryExclude   blacklist ярлыков категорий через `,` (param `category_exclude`)
	 */
	public function render(
		int $newsId,
		?string $typeInclude = null,
		?string $typeExclude = null,
		?string $categorySlug = null,
		?string $template = null,
		?string $categoryExclude = null,
	): string {
		global $tpl, $config;

		if($newsId <= 0) {
			return '';
		}

		$tree = (new PublicTreeService())->forNews(
			$newsId,
			self::parseIdList($typeInclude),
			self::parseIdList($typeExclude),
			$categorySlug,
			self::parseSlugList($categoryExclude),
		);

		if($tree === []) {
			return '';
		}

		if(!isset($tpl) || !is_object($tpl)) {
			if(!class_exists('dle_template', false)) {
				require_once \DLEPlugins::Check(ENGINE_DIR . '/classes/templates.class.php');
			}
			$tpl = new \dle_template();
			$skin = (string) ($config['skin'] ?? 'Air');
			$tpl->dir = ROOT_DIR . '/templates/' . $skin;
		}

		$context = self::resolveTemplateContext($tpl, $template);
		$base = $context['base'];
		$restoreDir = $context['restore'];

		if($restoreDir !== null) {
			$tpl->dir = $context['dir'];
		}

		$itemsHtml = '';

		try {
			foreach($tree as $collection) {
				$collectionItems = '';

				foreach($collection['items'] as $item) {
					$tpl->result['dc_conn_item'] = '';
					$tpl->load_template($base . 'item.tpl');
					$tpl->set('{title}', htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'));
					$tpl->set('{news-id}', (string) (int) $item['news_id']);
					$tpl->set('{relation-type}', htmlspecialchars((string) $item['relation_type'], ENT_QUOTES, 'UTF-8'));
					$tpl->set('{comment}', htmlspecialchars((string) ($item['comment'] ?? ''), ENT_QUOTES, 'UTF-8'));
					$tpl->set('{alt-name}', htmlspecialchars((string) $item['alt_name'], ENT_QUOTES, 'UTF-8'));
					$tpl->set('{category}', htmlspecialchars((string) $item['category'], ENT_QUOTES, 'UTF-8'));
					$tpl->set('{date}', htmlspecialchars((string) $item['date'], ENT_QUOTES, 'UTF-8'));
					$tpl->compile('dc_conn_item');
					$collectionItems .= (string) ($tpl->result['dc_conn_item'] ?? '');
				}

				$tpl->result['dc_conn_list'] = '';
				$tpl->load_template($base . 'list.tpl');
				$tpl->set('{collection-title}', htmlspecialchars((string) $collection['title'], ENT_QUOTES, 'UTF-8'));
				$tpl->set(
					'{collection-description}',
					htmlspecialchars((string) ($collection['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
				);
				$tpl->set(
					'{category-slug}',
					htmlspecialchars((string) ($collection['category_slug'] ?? ''), ENT_QUOTES, 'UTF-8'),
				);
				$tpl->set('{is-sequential}', !empty($collection['is_sequential']) ? '1' : '0');
				$tpl->set('{sort-order}', (string) (int) ($collection['sort_order'] ?? 0));
				$tpl->set('{items}', $collectionItems);
				$tpl->compile('dc_conn_list');
				$itemsHtml .= (string) ($tpl->result['dc_conn_list'] ?? '');
			}
		} finally {
			if($restoreDir !== null) {
				$tpl->dir = $restoreDir;
			}
		}

		return $itemsHtml;
	}

	/**
	 * @return array{base: string, dir: string, restore: string|null}
	 */
	private static function resolveTemplateContext(object $tpl, ?string $template): array {
		$slug = self::normalizeTemplateSlug($template);
		$currentDir = rtrim((string) $tpl->dir, '/');
		$dirs = [$currentDir];

		// DLE 21.0: штатный скин Air; Default — запасной путь пакета.
		foreach(['Air', 'Default'] as $fallbackSkin) {
			$fallbackDir = ROOT_DIR . '/templates/' . $fallbackSkin;

			if($currentDir !== $fallbackDir && is_dir($fallbackDir)) {
				$dirs[] = $fallbackDir;
			}
		}

		$candidates = [];

		if($slug !== '') {
			$candidates[] = 'devcraft/connections/' . $slug . '/';
		}

		$candidates[] = 'devcraft/connections/';

		foreach($dirs as $dir) {
			if($dir === '' || !is_dir($dir)) {
				continue;
			}

			foreach($candidates as $base) {
				if(
					is_file($dir . '/' . $base . 'list.tpl')
					&& is_file($dir . '/' . $base . 'item.tpl')
				) {
					return [
						'base'    => $base,
						'dir'     => $dir,
						'restore' => $dir !== $currentDir ? $currentDir : null,
					];
				}
			}
		}

		return [
			'base'    => 'devcraft/connections/',
			'dir'     => $currentDir,
			'restore' => null,
		];
	}

	/**
	 * Нормализует имя набора шаблонов (подпапка).
	 */
	private static function normalizeTemplateSlug(?string $raw): string {
		if($raw === null) {
			return '';
		}

		$slug = strtolower(trim($raw));

		if($slug === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $slug)) {
			return '';
		}

		return $slug;
	}

	/**
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

	/**
	 * @return list<string>
	 */
	private static function parseSlugList(?string $raw): array {
		if($raw === null || trim($raw) === '') {
			return [];
		}

		$slugs = [];

		foreach(preg_split('/[\s,]+/', $raw) ?: [] as $part) {
			$slug = trim((string) $part);

			if($slug !== '') {
				$slugs[$slug] = $slug;
			}
		}

		return array_values($slugs);
	}

}
