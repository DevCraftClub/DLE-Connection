<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Pages;

use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\RelationTypeService;

/**
 * Панель дерева сборок связей.
 */
final class DashboardPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Панель'));

		$collections = new CollectionService();
		$types       = new RelationTypeService();
		$assetsBase  = rtrim(
			Application::instance()->modulePublicAssetUrl(dirname(__DIR__)),
			'/',
		);

		return [
			'view' => 'connections/dashboard.twig',
			'data' => [
				'page_title'     => __('Связи'),
				'tree'           => $collections->tree(),
				'relation_types' => $types->list(),
				'assets_base'    => $assetsBase,
			],
		];
	}

}
