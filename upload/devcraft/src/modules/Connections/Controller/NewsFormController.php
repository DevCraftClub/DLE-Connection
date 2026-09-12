<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Controller;

use DevCraft\Core\Application;
use DevCraft\Core\Config\Paths;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\RelationTypeService;

/**
 * Рендер Twig-виджета связей в форме новости админки DLE.
 */
final class NewsFormController {

	public function render(int $newsId = 0): string {
		global $dle_login_hash;

		if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
			require_once \DLEPlugins::Check(ROOT_DIR . '/devcraft/init.php');
		}

		if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
			return '';
		}

		$app        = Application::instance();
		$assetsBase = rtrim($app->modulePublicAssetUrl(dirname(__DIR__)), '/');
		$coreJs     = rtrim(Paths::base(), '/') . '/devcraft/src/templates/core/assets/js/devcraft.js';
		$vJs        = @filemtime(dirname(__DIR__) . '/Public/connections.js') ?: time();
		$vCss       = @filemtime(dirname(__DIR__) . '/Public/connections.css') ?: time();
		$vCore      = @filemtime(Paths::templates() . '/core/assets/js/devcraft.js') ?: time();
		$hash       = htmlspecialchars((string) ($dle_login_hash ?? ''), ENT_QUOTES, 'UTF-8');
		$ajaxBase   = htmlspecialchars(Paths::ajaxBase(), ENT_QUOTES, 'UTF-8');

		$html = $app->twig()->render('@connections/news_form.twig', [
			'news_id'        => $newsId,
			'assets_base'    => $assetsBase,
			'tree'           => (new CollectionService())->tree($newsId > 0 ? $newsId : null),
			'relation_types' => (new RelationTypeService())->list(),
		]);

		$boot = '<script>document.body.dataset.mod="Connections";'
			. 'document.body.dataset.ajaxBase=' . json_encode(Paths::ajaxBase()) . ';'
			. '</script>'
			. '<input type="hidden" name="user_hash" value="' . $hash . '">'
			. '<script src="' . htmlspecialchars($coreJs . '?v=' . $vCore, ENT_QUOTES, 'UTF-8') . '"></script>';

		$assets = '<link rel="stylesheet" href="' . htmlspecialchars($assetsBase . '/connections.css?v=' . $vCss, ENT_QUOTES, 'UTF-8') . '">'
			. $boot
			. '<script src="' . htmlspecialchars($assetsBase . '/connections.js?v=' . $vJs, ENT_QUOTES, 'UTF-8') . '"></script>';

		return $assets . $html;
	}

}
