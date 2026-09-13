<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Models\ConnectionCollection;
use DevCraft\Modules\Connections\Models\ConnectionCollectionType;
use DevCraft\Modules\Connections\Models\ConnectionItem;
use DevCraft\Modules\Connections\Models\ConnectionPairRelation;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;
use DevCraft\Modules\Connections\Support\AutomationHostBridge;

/**
 * Сводка счётчиков для панели Connections.
 */
final class DashboardStatsService {

	/**
	 * @return list<array{key: string, label: string, value: int, icon: string, url: string|null}>
	 */
	public function cards(): array {
		$db = Application::instance()->database();

		$cards = [
			[
				'key'   => 'collections',
				'label' => __('Сборки'),
				'value' => $db->count(ConnectionCollection::class),
				'icon'  => 'mif-blockchain',
				'url'   => '?mod=dle_connections&action=tree',
			],
			[
				'key'   => 'items',
				'label' => __('Элементы'),
				'value' => $db->count(ConnectionItem::class),
				'icon'  => 'mif-list',
				'url'   => '?mod=dle_connections&action=tree',
			],
			[
				'key'   => 'pairs',
				'label' => __('Пары связей'),
				'value' => $db->count(ConnectionPairRelation::class),
				'icon'  => 'mif-link',
				'url'   => '?mod=dle_connections&action=tree',
			],
			[
				'key'   => 'collection_types',
				'label' => __('Категории'),
				'value' => $db->count(ConnectionCollectionType::class),
				'icon'  => 'mif-folder',
				'url'   => '?mod=dle_connections&action=collection_types',
			],
			[
				'key'   => 'relation_types',
				'label' => __('Типы связей'),
				'value' => $db->count(ConnectionRelationType::class),
				'icon'  => 'mif-tag',
				'url'   => '?mod=dle_connections&action=relation_types',
			],
		];

		if(AutomationHostBridge::isEnabled()) {
			$ruleClass = '\\DevCraft\\Modules\\ConnectionsAutomation\\Models\\ConnectionAutoRule';

			if(class_exists($ruleClass)) {
				try {
					$cards[] = [
						'key'   => 'auto_rules',
						'label' => __('Правила авто'),
						'value' => $db->count($ruleClass),
						'icon'  => 'mif-magic-wand',
						'url'   => '?mod=dle_connections&action=rules',
					];
				} catch(\Throwable) {
					// таблица ещё не создана — пропускаем карточку
				}
			}
		}

		return $cards;
	}

}
