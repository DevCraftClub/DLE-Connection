<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Controller;

/**
 * Виджет связей на публичной форме добавления / правки новости.
 *
 * Не трогает глобальный `$tpl`: иначе `load_template` снесёт шаблон `addnews.tpl` темы.
 */
final class AddNewsController {

	/**
	 * Тот же черновик, что вкладка «Связи» в админке (`NewsFormController`).
	 */
	public function render(int $newsId = 0): string {
		return (new NewsFormController())->render($newsId);
	}

}
