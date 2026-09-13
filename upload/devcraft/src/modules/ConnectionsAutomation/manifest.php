<?php

declare(strict_types=1);

use DevCraft\Types\AdminLink;
use DevCraft\Types\ModuleManifest;
use DevCraft\Builders\ModuleManifestBuilder;
use DevCraft\Builders\ModuleAjaxConfigBuilder;
use DevCraft\Builders\ModuleAssetsBuilder;
use DevCraft\Modules\Connections\ConnectionsIdentity;
use DevCraft\Modules\ConnectionsAutomation\ConnectionsAutomationIdentity;
use DevCraft\Modules\ConnectionsAutomation\Pages\RulesPage;
use DevCraft\Modules\ConnectionsAutomation\Ajax\RunAutomationHandler;
use DevCraft\Modules\ConnectionsAutomation\Ajax\AutoRulesHandler;
use DevCraft\Modules\ConnectionsAutomation\Services\FeatureGateService;

/**
 * Манифест сателлита «Связи: Автоматизация».
 *
 * Встраивается в Connections (`extends`), без отдельного пункта DLE admin_sections.
 * AJAX всегда объявлен здесь и при активном плагине вливается в host.
 *
 * @return ModuleManifest
 */
$builder = ModuleManifestBuilder::create()
	->mod(ConnectionsAutomationIdentity::mod())
	->code(ConnectionsAutomationIdentity::code())
	->name('Связи: Автоматизация')
	->version('1.0.0')
	->description(__('Автоматическая генерация направленных пар связей по правилам категории'))
	->icon('mif-magic-wand')
	->docsLink('https://readme.devcraft.club/dev/dle/connections_automation/')
	->siteLink('https://devcraft.club/')
	->ajax(
		ModuleAjaxConfigBuilder::create('admin')
			->methods([
				'list_rules_for_collection' => RunAutomationHandler::class,
				'run_automation'            => RunAutomationHandler::class,
				'auto_rules'                => AutoRulesHandler::class,
			])
	)
	->changelog(require DLEPlugins::Check(__DIR__ . '/changelog.data.php'))
	->assets(
		ModuleAssetsBuilder::create()
			->js('connections_automation.js')
	);

if(FeatureGateService::isPluginActive()) {
	$builder
		->extends(ConnectionsIdentity::mod())
		->menu([
			AdminLink::page(
				__('Правила автоматизации'),
				'rules',
				RulesPage::class,
				'mif-magic-wand',
				ConnectionsIdentity::mod(),
			),
		]);
} else {
	$builder->menu([]);
}

return $builder->build(__DIR__);
