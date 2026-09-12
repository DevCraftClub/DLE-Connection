<?php

declare(strict_types=1);

use DevCraft\Form\FormSchemaBuilder;
use DevCraft\Modules\Connections\ConnectionsIdentity;

return FormSchemaBuilder::create(ConnectionsIdentity::code())
	->section(__('Общие'))
		->checkbox('enabled', __('Включено'))
	->build();
