<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;
use DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository;
use RuntimeException;

/**
 * CRUD типов связей.
 */
final class RelationTypeService {

	public function repo(): ConnectionRelationTypeRepository {
		/** @var ConnectionRelationTypeRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionRelationType::class);

		return $repo;
	}

	public function validateName(string $name): string {
		$name = trim($name);

		if($name === '') {
			throw new RuntimeException(__('Название типа связи не может быть пустым'));
		}

		if(mb_strlen($name) > 100) {
			throw new RuntimeException(__('Название типа связи слишком длинное'));
		}

		return $name;
	}

	/**
	 * @return list<array{id:int, name:string, sort_order:int}>
	 */
	public function list(): array {
		$rows = [];

		foreach($this->repo()->findAllOrdered() as $type) {
			$rows[] = [
				'id'         => $type->id(),
				'name'       => $type->name,
				'sort_order' => $type->sort_order,
			];
		}

		return $rows;
	}

	public function create(string $name): ConnectionRelationType {
		$name = $this->validateName($name);

		if($this->repo()->findOneByName($name) !== null) {
			throw new RuntimeException(__('Тип связи с таким именем уже есть'));
		}

		$type             = new ConnectionRelationType();
		$type->name       = $name;
		$type->sort_order = $this->repo()->nextSortOrder();
		$this->repo()->saveEntity($type);

		return $type;
	}

	public function update(ConnectionRelationType $type, string $name): ConnectionRelationType {
		$name = $this->validateName($name);
		$other = $this->repo()->findOneByName($name);

		if($other !== null && $other->id() !== $type->id()) {
			throw new RuntimeException(__('Тип связи с таким именем уже есть'));
		}

		$type->name = $name;
		$this->repo()->saveEntity($type);

		return $type;
	}

	public function delete(ConnectionRelationType $type): void {
		$this->repo()->deleteEntity($type);
	}

	/**
	 * @param list<int> $ids
	 */
	public function reorder(array $ids): void {
		$order = 1;

		foreach($ids as $id) {
			$type = $this->repo()->findOneById((int) $id);

			if($type === null) {
				continue;
			}

			$type->sort_order = $order++;
			$this->repo()->saveEntity($type);
		}
	}

}
