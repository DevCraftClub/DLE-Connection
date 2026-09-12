<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionTypeRepository;

/**
 * Категория сборки связей (`ConnectionCollection.type_id`).
 */
#[Entity(
	role: 'dc_connections_collection_type',
	repository: ConnectionCollectionTypeRepository::class,
	table: 'dc_connections_collection_types',
)]
#[Index(columns: ['sort_order'], name: 'idx_dc_conn_ctype_sort')]
#[Index(columns: ['name'], unique: true, name: 'idx_dc_conn_ctype_name')]
#[Index(columns: ['slug'], unique: true, name: 'idx_dc_conn_ctype_slug')]
class ConnectionCollectionType extends AbstractEntity {

	#[Column(type: 'string', size: 100)]
	public string $name = '';

	/** Публичный идентификатор для фильтра include (`category_slug`). */
	#[Column(type: 'string', size: 100, default: '')]
	public string $slug = '';

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
