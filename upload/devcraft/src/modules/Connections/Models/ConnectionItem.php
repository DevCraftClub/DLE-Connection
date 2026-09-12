<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\Connections\Repositories\ConnectionItemRepository;

/**
 * Новость внутри сборки связей.
 */
#[Entity(
	role: 'dc_connections_item',
	repository: ConnectionItemRepository::class,
	table: 'dc_connections_items',
)]
#[Index(columns: ['collection_id', 'sort_order'], name: 'idx_dc_conn_item_col_sort')]
#[Index(columns: ['news_id'], name: 'idx_dc_conn_item_news')]
#[Index(columns: ['collection_id', 'news_id'], unique: true, name: 'idx_dc_conn_item_unique')]
class ConnectionItem extends AbstractEntity {

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $collection_id = 0;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $news_id = 0;

	#[Column(type: 'string', size: 100, default: '')]
	public string $relation_type = '';

	#[Column(type: 'boolean', default: true)]
	public bool $is_visible = true;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $sort_order = 0;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

}
