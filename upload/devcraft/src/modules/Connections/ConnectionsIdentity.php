<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections;

use DevCraft\Core\Abstracts\AbstractModuleIdentity;

/**
 * Identity модуля Connections.
 *
 * MODULE/CODE = DLE mod (`engine/inc/dle_connections.php` → `?mod=dle_connections`).
 * Каталог модуля: `devcraft/src/modules/Connections/` (не путать с MODULE).
 */
final class ConnectionsIdentity extends AbstractModuleIdentity {

	public const string MODULE = 'dle_connections';

	public const string CODE = 'dle_connections';

}
