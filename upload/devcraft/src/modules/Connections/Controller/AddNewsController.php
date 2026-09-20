<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Controller;

/**
 * Виджет связей на публичной форме добавления / правки новости.
 *
 * Не трогает глобальный `$tpl`: иначе `load_template` снесёт шаблон `addnews.tpl` темы.
 * Оболочка темы: `templates/{skin}/devcraft/connections/addnews.tpl` (`{widget}`).
 */
final class AddNewsController {

	/**
	 * Тот же черновик, что вкладка «Связи» в админке (`NewsFormController`).
	 */
	public function render(int $newsId = 0): string {
		$widget = (new NewsFormController())->render($newsId);

		if($widget === '') {
			return '';
		}

		return $this->wrapWithTheme($widget);
	}

	/**
	 * Оборачивает виджет в шаблон темы (Air → Default).
	 */
	private function wrapWithTheme(string $widget): string {
		global $config;

		if(!class_exists('dle_template', false)) {
			require_once \DLEPlugins::Check(ENGINE_DIR . '/classes/templates.class.php');
		}

		$skin = (string) ($config['skin'] ?? 'Air');
		$dirs = [];

		foreach([$skin, 'Air', 'Default'] as $candidate) {
			$dir = ROOT_DIR . '/templates/' . $candidate;
			if(is_dir($dir) && !in_array($dir, $dirs, true)) {
				$dirs[] = $dir;
			}
		}

		$local = new \dle_template();

		foreach($dirs as $dir) {
			if(!is_file($dir . '/devcraft/connections/addnews.tpl')) {
				continue;
			}

			$local->dir = $dir;
			$local->result['dc_conn_addnews'] = '';
			$local->load_template('devcraft/connections/addnews.tpl');
			$local->set('{widget}', $widget);
			$local->compile('dc_conn_addnews', true);

			$wrapped = (string) ($local->result['dc_conn_addnews'] ?? '');

			return $wrapped !== '' ? $wrapped : $widget;
		}

		return $widget;
	}

}
