<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\ConnectionsAutomation\Repositories\ConnectionAutoConditionRepository;

/**
 * Условие (слот) правила автоматизации.
 *
 * `target_relation_type` — строка каталога Connections, не FK.
 *
 * @see https://cycle-orm.dev/docs/relation-belongs-to/current/en
 */
#[Entity(
	role: 'dc_connections_auto_condition',
	repository: ConnectionAutoConditionRepository::class,
	table: 'dc_connections_auto_conditions',
)]
#[Index(
	columns: ['rule_id', 'condition_type'],
	unique: true,
	name: 'idx_dc_conn_auto_cond_unique',
)]
#[Index(columns: ['rule_id'], name: 'idx_dc_conn_auto_cond_rule')]
class ConnectionAutoCondition extends AbstractEntity {

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $rule_id = 0;

	/** Идентификатор слота: index_before, from_hub, peer, … */
	#[Column(type: 'string', size: 64)]
	public string $condition_type = '';

	/** Имя типа из каталога Connections или '' (слот пуст → нет ребра). */
	#[Column(type: 'string', size: 100, default: '')]
	public string $target_relation_type = '';

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	#[BelongsTo(
		target: ConnectionAutoRule::class,
		innerKey: 'rule_id',
		cascade: false,
		fkCreate: true,
		fkAction: 'CASCADE',
		indexCreate: false,
	)]
	public ?ConnectionAutoRule $rule = null;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
