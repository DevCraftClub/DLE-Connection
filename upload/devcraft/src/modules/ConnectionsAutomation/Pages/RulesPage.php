<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Pages;

use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Services\CollectionTypeService;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use DevCraft\Modules\ConnectionsAutomation\Services\AutoRuleService;
use DevCraft\Modules\ConnectionsAutomation\Services\FeatureGateService;

/**
 * Управление правилами автоматизации по категориям сборок.
 */
final class RulesPage extends AbstractPage {

	public function handle(): array {
		$pageName = __('Правила автоматизации');
		$this->addBreadcrumb($pageName);

		$categoryId = (int) ($_GET['category_id'] ?? 0);
		$types      = (new CollectionTypeService())->list();
		$rulesSvc   = new AutoRuleService();
		$rules      = $categoryId > 0 ? $rulesSvc->listByCategory($categoryId) : [];
		$assetsBase = rtrim(
			Application::instance()->modulePublicAssetUrl(dirname(__DIR__)),
			'/',
		);

		return [
			'view' => 'connectionsautomation/rules.twig',
			'data' => [
				'page_title'         => $pageName,
				'automation_enabled' => FeatureGateService::isEnabled(),
				'collection_types'   => $types,
				'category_id'        => $categoryId,
				'rules'              => $rules,
				'relation_types'     => (new RelationTypeService())->list(),
				'assets_base'        => $assetsBase,
			],
		];
	}

}
