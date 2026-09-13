<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionRepository;

/**
 * Сборка связанных новостей.
 *
 * @see https://cycle-orm.dev/docs/relation-has-many/current/en
 * @see https://cycle-orm.dev/docs/relation-belongs-to/current/en
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

	/** Порядок → авто-метки из настроек модуля (auto_label_*_id) при отсутствии пары. */
	#[Column(type: 'boolean', default: true)]
	public bool $is_sequential = true;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	/**
	 * Категория сборки. `type_id = 0` — без категории (не SQL NULL).
	 *
	 * @see https://cycle-orm.dev/docs/relation-belongs-to/current/en
	 */
	#[BelongsTo(
		target: ConnectionCollectionType::class,
		innerKey: 'type_id',
		nullable: true,
		cascade: false,
		fkCreate: false,
		indexCreate: false,
	)]
	public ?ConnectionCollectionType $collectionType = null;

	/**
	 * Элементы сборки (членства новостей).
	 *
	 * @var list<ConnectionItem>
	 */
	#[HasMany(
		target: ConnectionItem::class,
		outerKey: 'collection_id',
		orderBy: ['sort_order' => 'ASC'],
		fkCreate: false,
		indexCreate: false,
	)]
	public array $items = [];

	/**
	 * Направленные пары типов внутри сборки.
	 *
	 * @var list<ConnectionPairRelation>
	 */
	#[HasMany(
		target: ConnectionPairRelation::class,
		outerKey: 'collection_id',
		fkCreate: false,
		indexCreate: false,
	)]
	public array $pairRelations = [];

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
