<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionRepository;

/**
 * Сборка связанных новостей.
 */
#[Entity(
	role: 'dc_connections_collection',
	repository: ConnectionCollectionRepository::class,
	table: 'dc_connections_collections',
)]
#[Index(columns: ['sort_order'], name: 'idx_dc_conn_col_sort')]
#[Index(columns: ['type_id'], name: 'idx_dc_conn_col_type')]
class ConnectionCollection extends AbstractEntity {

	#[Column(type: 'string', size: 255)]
	public string $title = '';

	#[Column(type: 'text', nullable: true, default: null)]
	public ?string $description = null;

	/** Id категории/типа сборки (`dc_connections_collection_types`); 0 = без типа. */
	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $type_id = 0;

	/** Порядок → авто-метки Предыстория/Продолжение при отсутствии пары. */
	#[Column(type: 'boolean', default: true)]
	public bool $is_sequential = true;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
