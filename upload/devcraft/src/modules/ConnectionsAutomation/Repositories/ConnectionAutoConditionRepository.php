<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\ConnectionsAutomation\Models\ConnectionAutoCondition;

/**
 * Репозиторий условий (слотов) правил автоматизации.
 */
final class ConnectionAutoConditionRepository extends AbstractRepository {

	public function findOneById(int $id): ?ConnectionAutoCondition {
		/** @var ConnectionAutoCondition|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<ConnectionAutoCondition>
	 */
	public function findByRuleId(int $ruleId): array {
		if($ruleId <= 0) {
			return [];
		}

		/** @var list<ConnectionAutoCondition> $rows */
		$rows = $this->select()
			->where('rule_id', $ruleId)
			->orderBy('sort_order', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();

		return $rows;
	}

	/**
	 * Удаляет все условия правила (перед replace-all или cascade delete).
	 */
	public function deleteByRuleId(int $ruleId): void {
		foreach($this->findByRuleId($ruleId) as $row) {
			$this->deleteEntity($row);
		}
	}

}
