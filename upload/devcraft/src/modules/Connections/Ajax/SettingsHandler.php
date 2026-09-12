<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Ajax;

use DevCraft\Core\Abstracts\AbstractSettingsHandler;
use DevCraft\Modules\Connections\ConnectionsIdentity;

/**
 * Сохранение настроек модуля Connections.
 */
final class SettingsHandler extends AbstractSettingsHandler {

	protected function configName(): ?string {
		return ConnectionsIdentity::code();
	}

}
