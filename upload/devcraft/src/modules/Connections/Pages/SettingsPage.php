<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Pages;

use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Core\Application;
use DevCraft\Core\Interfaces\SettingsPageInterface;
use DevCraft\Modules\Connections\Services\RelationTypeService;

/**
 * Настройки модуля Connections.
 */
final class SettingsPage extends AbstractPage implements SettingsPageInterface {

	public function handle(): array {
		$this->addBreadcrumb(__('Настройки'));

		$assetsBase = rtrim(
			Application::instance()->modulePublicAssetUrl(dirname(__DIR__)),
			'/',
		);

		return [
			'view' => 'connections/settings.twig',
			'data' => [
				'page_title'     => __('Настройки'),
				'relation_types' => (new RelationTypeService())->list(),
				'assets_base'    => $assetsBase,
			],
		];
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function supplementFormData(): array {
		return [];
	}

}
