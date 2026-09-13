<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Support\DataManager;

/**
 * Twig-разметка админки Connections (дерево, поиск, форма новости).
 */
final class TreeViewService {

	/**
	 * @param list<array<string, mixed>> $tree
	 */
	public function renderTree(array $tree, string $assetsBase): string {
		return Application::instance()->twig()->render('@connections/partials/tree_body.twig', [
			'tree'                => $tree,
			'assets_base'         => $assetsBase,
			'admin_edit_news_url' => $this->adminEditNewsUrlTemplate(),
		]);
	}

	/**
	 * Шаблон URL правки новости в DLE-админке (`__ID__` → news_id).
	 */
	public function adminEditNewsUrlTemplate(): string {
		return DataManager::normalizeUrl('?mod=editnews&action=editnews', [
			'id' => '__ID__',
		]);
	}

	/**
	 * @param list<array{id:int, title:string}> $items
	 */
	public function renderSearchResults(array $items): string {
		return Application::instance()->twig()->render('@connections/partials/search_news_results.twig', [
			'items' => $items,
		]);
	}

	/**
	 * @param list<array{
	 *     row_key: string,
	 *     collection_title: string,
	 *     news_title?: string,
	 *     items: list<array<string, mixed>>
	 * }> $blocks
	 */
	public function renderNewsMemberships(
		array $blocks,
		?string $assetsBase = null,
		bool $automationEnabled = false,
	): string {
		return Application::instance()->twig()->render('@connections/partials/news_form_memberships.twig', [
			'blocks'              => $blocks,
			'assets_base'         => $assetsBase ?? $this->assetsBase(),
			'admin_edit_news_url' => $this->adminEditNewsUrlTemplate(),
			'automation_enabled'  => $automationEnabled,
		]);
	}

	public function assetsBase(): string {
		return rtrim(
			Application::instance()->modulePublicAssetUrl(
				dirname(__DIR__),
			),
			'/',
		);
	}

}
