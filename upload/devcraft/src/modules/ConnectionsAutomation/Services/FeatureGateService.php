<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Services;

use DevCraft\Builders\QueryBuilder;
use DevCraft\Modules\ConnectionsAutomation\ConnectionsAutomationIdentity;

/**
 * Проверка доступности сателлита автоматизации.
 *
 * Включается только если в Plugin Manager установлен и active=1
 * плагин «Connections Automation» (отдельный ZIP).
 */
final class FeatureGateService {

	/** Имя плагина в таблице plugins (install.xml &lt;name&gt;). */
	public const string PLUGIN_NAME = 'Connections Automation';

	/**
	 * Включён, если Identity на диске и плагин Automation активен.
	 */
	public static function isEnabled(): bool {
		if(!class_exists(ConnectionsAutomationIdentity::class)) {
			return false;
		}

		return self::isPluginActive();
	}

	/**
	 * Активен ли плагин «Connections Automation» (QueryBuilder → plugins).
	 */
	public static function isPluginActive(): bool {
		try {
			$row = QueryBuilder::create('plugins')
				->withConditionsItem('name', self::PLUGIN_NAME)
				->withLimit(1)
				->first();
		} catch(\Throwable) {
			return false;
		}

		if($row === [] || (int) ($row['id'] ?? 0) <= 0) {
			return false;
		}

		return (int) ($row['active'] ?? 0) === 1;
	}

}
