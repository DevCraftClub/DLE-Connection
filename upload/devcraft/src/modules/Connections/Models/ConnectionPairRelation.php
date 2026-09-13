<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionPairRelationRepository;

/**
 * Направленная пара типов связи внутри сборки (контекст → цель).
 *
 * `from_news_id` / `to_news_id` — id новостей DLE (вне ORM); тип — строка каталога, не FK.
 *
 * @see https://cycle-orm.dev/docs/relation-belongs-to/current/en
 */
#[Entity(
	role: 'dc_connections_pair_relation',
	repository: ConnectionPairRelationRepository::class,
	table: 'dc_connections_pair_relations',
)]
#[Index(
	columns: ['collection_id', 'from_news_id', 'to_news_id'],
	unique: true,
	name: 'idx_dc_conn_pair_unique',
)]
#[Index(columns: ['collection_id', 'from_news_id'], name: 'idx_dc_conn_pair_from')]
class ConnectionPairRelation extends AbstractEntity {

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $collection_id = 0;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $from_news_id = 0;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $to_news_id = 0;

	/**
	 * Имя из каталога типов либо '' = явный пустой override (без auto-бейджа).
	 */
	#[Column(type: 'string', size: 100, default: '')]
	public string $relation_type = '';

	#[Column(type: 'text', default: '')]
	public string $comment = '';

	/**
	 * Ручная правка типа связи редактором (защита при Automate preserve).
	 *
	 * // DevCraft ConnectionsAutomation: start|end — колонка для сателлита автоматизации
	 */
	#[Column(type: 'boolean', default: false)]
	public bool $is_manual_override = false;

	#[BelongsTo(
		target: ConnectionCollection::class,
		innerKey: 'collection_id',
		cascade: false,
		fkCreate: false,
		indexCreate: false,
	)]
	public ?ConnectionCollection $collection = null;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
