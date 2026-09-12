<?php

declare(strict_types=1);

/*
=====================================================
 Connections — DLE 21.0 admin entry (DevCraft)
=====================================================
*/

if (!defined('DATALIFEENGINE') || !defined('LOGGED_IN')) {
	header('HTTP/1.1 403 Forbidden');
	header('Location: ../../');

	exit('Hacking attempt!');
}

require_once DLEPlugins::Check(ROOT_DIR . '/devcraft/init.php');

if (!defined('DEVCRAFT_BOOTSTRAPPED')) {
	return;
}

DevCraft\Core\Application::instance()->runAdmin(moduleDir: 'Connections', mod: 'dle_connections');
