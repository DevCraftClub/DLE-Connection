<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\Annotated\Annotation\Relation\HasMany;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Models\ConnectionCollectionType;
use DevCraft\Modules\ConnectionsAutomation\Repositories\ConnectionAutoRuleRepository;

/**
 * Правило автоматизации пар связей для категории сборки.
 *
 * @see https://cycle-orm.dev/docs/relation-has-many/current/en
 * @see https://cycle-orm.dev/docs/relation-belongs-to/current/en
 */
#[Entity(
	role: 'dc_connections_auto_rule',
	repository: ConnectionAutoRuleRepository::class,
	table: 'dc_connections_auto_rules',
)]
#[Index(columns: ['category_id'], name: 'idx_dc_conn_auto_rule_cat')]
#[Index(columns: ['category_id', 'is_active'], name: 'idx_dc_conn_auto_rule_cat_active')]
class ConnectionAutoRule extends AbstractEntity {

	/** Id категории сборки Connections (`dc_connections_collection_types`). */
	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $category_id = 0;

	#[Column(type: 'string', size: 255)]
	public string $rule_name = '';

	/** linear | hub_spoke | symmetric | custom */
	#[Column(type: 'string', size: 32, default: 'linear')]
	public string $pattern_type = 'linear';

	#[Column(type: 'boolean', default: true)]
	public bool $is_active = true;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	/**
	 * Категория сборки. `fkCreate=false`: колонка уже есть; `0` не используем как FK.
	 */
	#[BelongsTo(
		target: ConnectionCollectionType::class,
		innerKey: 'category_id',
		nullable: true,
		cascade: false,
		fkCreate: false,
		indexCreate: false,
	)]
	public ?ConnectionCollectionType $category = null;

	/**
	 * @var list<ConnectionAutoCondition>
	 */
	#[HasMany(
		target: ConnectionAutoCondition::class,
		outerKey: 'rule_id',
		orderBy: ['sort_order' => 'ASC'],
		fkCreate: false,
		indexCreate: false,
	)]
	public array $conditions = [];

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
