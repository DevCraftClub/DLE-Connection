<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Controller;

/**
 * Виджет связей на публичной форме добавления новости.
 */
final class AddNewsController {

	/**
	 * Рендерит шаблон addnews.tpl (подсказка / UI без news_id до сохранения).
	 */
	public function render(): string {
		global $tpl, $config;

		if(!isset($tpl) || !is_object($tpl)) {
			if(!class_exists('dle_template', false)) {
				require_once \DLEPlugins::Check(ENGINE_DIR . '/classes/templates.class.php');
			}
			$tpl = new \dle_template();
		}

		$skin = (string) ($config['skin'] ?? 'Air');
		$base = 'devcraft/connections/';

		if(!is_file(ROOT_DIR . '/templates/' . $skin . '/' . $base . 'addnews.tpl')) {
			$skin = is_file(ROOT_DIR . '/templates/Air/' . $base . 'addnews.tpl') ? 'Air' : 'Default';
		}

		$tpl->result['dc_conn_addnews'] = '';
		$tpl->load_template($base . 'addnews.tpl');
		$tpl->set('{module-url}', '?do=static&page=connections');
		$tpl->compile('dc_conn_addnews');

		return $tpl->result['dc_conn_addnews'] ?? '';
	}

}
