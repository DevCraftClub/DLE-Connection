<?php

declare(strict_types=1);

/**
 * Публичный include Connections (канон Controller/).
 *
 * Примеры:
 * {include file="devcraft/src/modules/Connections/Controller/show_connections.php?news_id={news-id}"}
 * {include file="devcraft/src/modules/Connections/Controller/show_connections.php?news_id={news-id}&type=1,3"}
 * {include file="devcraft/src/modules/Connections/Controller/show_connections.php?news_id={news-id}&type_exclude=2"}
 */

if(!defined('DATALIFEENGINE')) {
	header('HTTP/1.1 403 Forbidden');

	exit('Hacking attempt!');
}

if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
	require_once DLEPlugins::Check(ROOT_DIR . '/devcraft/init.php');
}

if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
	return;
}

$newsId = 0;

if(isset($news_id)) {
	$newsId = (int) $news_id;
} elseif(isset($newsid)) {
	$newsId = (int) $newsid;
}

$typeInclude = isset($type) ? (string) $type : null;
$typeExclude = isset($type_exclude) ? (string) $type_exclude : null;

echo (new DevCraft\Modules\Connections\Controller\FullstoryController())->render(
	$newsId,
	$typeInclude,
	$typeExclude,
);
