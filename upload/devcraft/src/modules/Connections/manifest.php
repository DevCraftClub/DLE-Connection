<?php

declare(strict_types=1);

use DevCraft\Types\AdminLink;
use DevCraft\Types\ModuleManifest;
use DevCraft\Builders\ModuleManifestBuilder;
use DevCraft\Builders\ModuleAjaxConfigBuilder;
use DevCraft\Builders\ModuleAssetsBuilder;
use DevCraft\Modules\Connections\ConnectionsIdentity;
use DevCraft\Modules\Connections\Pages\SettingsPage;
use DevCraft\Modules\Connections\Pages\ChangelogPage;
use DevCraft\Modules\Connections\Pages\DashboardPage;
use DevCraft\Modules\Connections\Ajax\SettingsHandler;
use DevCraft\Modules\Connections\Ajax\TreeHandler;
use DevCraft\Modules\Connections\Ajax\SaveCollectionHandler;
use DevCraft\Modules\Connections\Ajax\DeleteCollectionHandler;
use DevCraft\Modules\Connections\Ajax\ReorderCollectionsHandler;
use DevCraft\Modules\Connections\Ajax\DuplicateCollectionHandler;
use DevCraft\Modules\Connections\Ajax\SaveItemHandler;
use DevCraft\Modules\Connections\Ajax\DeleteItemHandler;
use DevCraft\Modules\Connections\Ajax\ReorderItemsHandler;
use DevCraft\Modules\Connections\Ajax\ToggleItemVisibilityHandler;
use DevCraft\Modules\Connections\Ajax\SearchNewsHandler;
use DevCraft\Modules\Connections\Ajax\RelationTypesHandler;

/**
 * Манифест модуля Connections.
 *
 * @return ModuleManifest
 */
return ModuleManifestBuilder::create()
	->mod(ConnectionsIdentity::mod())
	->code(ConnectionsIdentity::code())
	->name('Connections')
	->version('210.1.0')
	->description(__('Взаимосвязь между новостями'))
	->icon('mif-blockchain')
	->docsLink('https://readme.devcraft.club/dev/connections/')
	->siteLink('https://devcraft.club/')
	->menu([
		AdminLink::page(__('Панель'), 'dashboard', DashboardPage::class, 'mif-blockchain', ConnectionsIdentity::mod()),
		AdminLink::page(__('Настройки'), 'settings', SettingsPage::class, 'mif-cog', ConnectionsIdentity::mod()),
		AdminLink::page(__('История изменений'), 'changelog', ChangelogPage::class, 'mif-library', ConnectionsIdentity::mod()),
	])
	->ajax(
		ModuleAjaxConfigBuilder::create('admin')
			->methods([
				'settings'             => SettingsHandler::class,
				'tree'                 => TreeHandler::class,
				'save_collection'      => SaveCollectionHandler::class,
				'delete_collection'    => DeleteCollectionHandler::class,
				'reorder_collections'  => ReorderCollectionsHandler::class,
				'duplicate_collection' => DuplicateCollectionHandler::class,
				'save_item'            => SaveItemHandler::class,
				'delete_item'          => DeleteItemHandler::class,
				'reorder_items'        => ReorderItemsHandler::class,
				'toggle_item_visibility' => ToggleItemVisibilityHandler::class,
				'search_news'          => SearchNewsHandler::class,
				'relation_types'       => RelationTypesHandler::class,
			])
	)
	->changelog(require DLEPlugins::Check(__DIR__ . '/changelog.data.php'))
	->assets(
		ModuleAssetsBuilder::create()
			->js('connections.js')
			->css('connections.css')
	)
	->build(__DIR__);
