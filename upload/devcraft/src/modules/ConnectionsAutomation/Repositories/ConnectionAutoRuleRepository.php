<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\ConnectionsAutomation\Models\ConnectionAutoRule;

/**
 * Репозиторий правил автоматизации.
 */
final class ConnectionAutoRuleRepository extends AbstractRepository {

	public function findOneById(int $id): ?ConnectionAutoRule {
		/** @var ConnectionAutoRule|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<ConnectionAutoRule>
	 */
	public function findByCategory(int $categoryId): array {
		if($categoryId <= 0) {
			return [];
		}

		/** @var list<ConnectionAutoRule> $rows */
		$rows = $this->select()
			->where('category_id', $categoryId)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $rows;
	}

	/**
	 * @return list<ConnectionAutoRule>
	 */
	public function findActiveByCategory(int $categoryId): array {
		if($categoryId <= 0) {
			return [];
		}

		/** @var list<ConnectionAutoRule> $rows */
		$rows = $this->select()
			->where('category_id', $categoryId)
			->where('is_active', true)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $rows;
	}

}
