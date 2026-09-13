<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation;

use DevCraft\Core\Abstracts\AbstractModuleIdentity;

/**
 * Identity сателлита «Связи: Автоматизация».
 *
 * MODULE/CODE = AJAX mod (`mod=dle_connections_auto`).
 * Каталог: `devcraft/src/modules/ConnectionsAutomation/`.
 * UI встраивается в Connections (`extends`); отдельного `admin_sections` / `engine/inc` нет.
 * Код поставляется пакетом Connections; включение — плагин «Connections Automation».
 */
final class ConnectionsAutomationIdentity extends AbstractModuleIdentity {

	public const string MODULE = 'dle_connections_auto';

	public const string CODE = 'dle_connections_auto';

}
