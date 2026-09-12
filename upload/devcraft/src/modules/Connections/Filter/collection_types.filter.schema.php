<?php

declare(strict_types=1);

/**
 * Схема фильтрации страницы категорий сборок Connections.
 *
 * @return array{
 *     sort: array{default: string, columns: array<string, string>},
 *     sections: list<array{title: string, fields: list<array{id: string, type: string, label: string, metro?: array<string, mixed>}>}>,
 * }
 */
return [
	'sort'     => [
		'default' => 'sort_order',
		'columns' => [
			'id'         => '#',
			'name'       => __('Название'),
			'sort_order' => __('Порядок'),
		],
	],
	'sections' => [
		[
			'title'  => __('Фильтр'),
			'fields' => [
				[
					'id'    => 'name',
					'type'  => 'text',
					'label' => __('Название'),
					'metro' => ['db_column' => 'name'],
				],
				[
					'id'    => 'id',
					'type'  => 'text',
					'label' => 'ID',
					'metro' => ['db_column' => 'id'],
				],
			],
		],
	],
];
