<?php

declare(strict_types=1);

use DevCraft\Core\Application;
use DevCraft\Form\FormSchemaBuilder;
use DevCraft\Modules\Connections\ConnectionsIdentity;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;

$options = [0 => '—'];

try {
	/** @var \DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository $repo */
	$repo = Application::instance()->database()->repository(ConnectionRelationType::class);

	foreach($repo->findAllOrdered() as $type) {
		$options[$type->id()] = $type->name;
	}
} catch(\Throwable) {
}

return FormSchemaBuilder::create(ConnectionsIdentity::code())
	->section(__('Авто-подписи (без пары)'))
		->select('auto_label_before_id', __('Раньше focus (последовательная сборка)'))
			->description(__('Тип из каталога, если у пары нет override и target.sort_order < focus. «—» — без подписи.'))
			->options($options)
			->default(0)
		->select('auto_label_after_id', __('Позже focus (последовательная сборка)'))
			->description(__('Тип из каталога, если у пары нет override и target.sort_order > focus. «—» — без подписи.'))
			->options($options)
			->default(0)
		->select('auto_label_related_id', __('Произвольная сборка'))
			->description(__('Тип из каталога для не-последовательной сборки без пары. «—» — без подписи.'))
			->options($options)
			->default(0)
	->build();
