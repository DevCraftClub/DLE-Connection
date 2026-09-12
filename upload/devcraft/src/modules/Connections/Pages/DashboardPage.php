<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Pages;

use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Core\Application;
use DevCraft\Modules\Connections\ConnectionsIdentity;

/**
 * Стандартная панель модуля (pages/dashboard.twig).
 */
final class DashboardPage extends AbstractPage {

	public function handle(): array {
		$registry  = Application::instance()->registry();
		$plugin    = $registry->forMod(ConnectionsIdentity::mod());
		$meta      = $plugin?->meta() ?? [];
		$context   = $this->adminContext();
		$changelog = $plugin?->changelog() ?? [];
		$latest    = isset($changelog[0]) ? $changelog[0]->toArray() : null;
		$mod       = $plugin?->mod() ?? ConnectionsIdentity::mod();
		$menu      = [];

		if($latest !== null) {
			$latest['teaser_items'] = $changelog[0]->teaserItems(3);
		}

		foreach($context->menu() as $link) {
			if($link->type !== 'link' || $link->action === null || $link->action === 'dashboard') {
				continue;
			}

			$menu[] = [
				'name'   => $link->name,
				'link'   => $link->link,
				'icon'   => $link->extra,
				'action' => $link->action,
			];
		}

		return [
			'view' => 'pages/dashboard.twig',
			'data' => [
				'page_title' => (string) ($meta['name'] ?? 'Connections'),
				'dashboard'  => [
					'app'              => [
						'name'        => (string) ($meta['name'] ?? 'Connections'),
						'version'     => (string) ($meta['version'] ?? '0.0.0'),
						'description' => (string) ($meta['description'] ?? ''),
						'icon'        => (string) ($meta['icon'] ?? ''),
						'docs_link'   => (string) ($meta['docsLink'] ?? ''),
						'site_link'   => (string) ($meta['siteLink'] ?? ''),
						'site_id'     => (int) ($meta['siteId'] ?? 0),
						'code'        => (string) ($meta['module_code'] ?? ConnectionsIdentity::code()),
					],
					'author'           => $context->author()->toArray(),
					'lic_link'         => $context->licLink(),
					'menu'             => $menu,
					'changelog_latest' => $latest,
					'changelog_url'    => '?mod=' . $mod . '&action=changelog',
					'show_assets'      => false,
					'show_update'      => false,
				],
			],
		];
	}

}
