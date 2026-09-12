<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository;

/**
 * Тип связи между новостями.
 */
#[Entity(
	role: 'dc_connections_relation_type',
	repository: ConnectionRelationTypeRepository::class,
	table: 'dc_connections_relation_types',
)]
#[Index(columns: ['sort_order'], name: 'idx_dc_conn_rtype_sort')]
#[Index(columns: ['name'], unique: true, name: 'idx_dc_conn_rtype_name')]
class ConnectionRelationType extends AbstractEntity {

	#[Column(type: 'string', size: 100)]
	public string $name = '';

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
