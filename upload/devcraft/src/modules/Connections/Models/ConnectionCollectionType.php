<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionTypeRepository;

/**
 * Категория сборки связей (`ConnectionCollection.type_id`).
 *
 * @see https://cycle-orm.dev/docs/relation-has-many/current/en
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

	/** Публичный идентификатор для фильтра include (`category`). */
	#[Column(type: 'string', size: 100, default: '')]
	public string $slug = '';

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	/**
	 * Сборки этой категории. FK уже в `collections.type_id` (0 = без типа — не грузить через FK).
	 *
	 * @var list<ConnectionCollection>
	 */
	#[HasMany(
		target: ConnectionCollection::class,
		outerKey: 'type_id',
		orderBy: ['sort_order' => 'ASC'],
		fkCreate: false,
		indexCreate: false,
	)]
	public array $collections = [];

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
