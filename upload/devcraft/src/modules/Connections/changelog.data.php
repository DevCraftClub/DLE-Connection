<?php

declare(strict_types=1);

use DevCraft\Builders\ChangelogBuilder;

return [
	ChangelogBuilder::create('210.1.0')
		->date('2026-09-12')
		->added([
			__('Админ-дерево сборок и элементов связей'),
			__('Типы связей в настройках'),
			__('Виджет связей в форме новости админки'),
			__('Публичный блок на полной новости и при добавлении с сайта'),
		])
		->build(),
];
