<?php

declare(strict_types=1);

use DevCraft\Builders\ChangelogBuilder;

return [
	ChangelogBuilder::create('1.0.0')
		->date('2026-09-12')
		->added([
			__('Правила автоматизации пар связей по категории сборки'),
			__('Пресеты linear / hub_spoke / symmetric и кастомные условия'),
			__('Запуск Automate (preserve / full_reset) и автозапуск при одном активном правиле'),
			__('Сателлит Connections (`extends`): пункт «Правила автоматизации» в меню Connections'),
			__('Embed UI в Connections (категории / дерево сборок) при установленном аддоне'),
		])
		->build(),
];
