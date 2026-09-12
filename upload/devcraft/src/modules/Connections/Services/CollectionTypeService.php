<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Models\ConnectionCollectionType;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionTypeRepository;
use RuntimeException;

/**
 * CRUD категорий сборок (`type_id`).
 */
final class CollectionTypeService {

	public function repo(): ConnectionCollectionTypeRepository {
		/** @var ConnectionCollectionTypeRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionCollectionType::class);

		return $repo;
	}

	public function validateName(string $name): string {
		$name = trim($name);

		if($name === '') {
			throw new RuntimeException(__('Название категории сборки не может быть пустым'));
		}

		if(mb_strlen($name) > 100) {
			throw new RuntimeException(__('Название категории сборки слишком длинное'));
		}

		return $name;
	}

	/**
	 * Проверяет type_id: 0 допустим; иначе тип должен существовать.
	 */
	public function normalizeTypeId(int $typeId): int {
		if($typeId <= 0) {
			return 0;
		}

		if($this->repo()->findOneById($typeId) === null) {
			throw new RuntimeException(__('Категория сборки не найдена'));
		}

		return $typeId;
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

	/**
	 * Карта id → name для дерева сборок.
	 *
	 * @return array<int, string>
	 */
	public function nameMap(): array {
		$map = [];

		foreach($this->repo()->findAllOrdered() as $type) {
			$map[$type->id()] = $type->name;
		}

		return $map;
	}

	public function create(string $name): ConnectionCollectionType {
		$name = $this->validateName($name);

		if($this->repo()->findOneByName($name) !== null) {
			throw new RuntimeException(__('Категория сборки с таким именем уже есть'));
		}

		$type             = new ConnectionCollectionType();
		$type->name       = $name;
		$type->sort_order = $this->repo()->nextSortOrder();
		$this->repo()->saveEntity($type);

		return $type;
	}

	public function update(ConnectionCollectionType $type, string $name): ConnectionCollectionType {
		$name  = $this->validateName($name);
		$other = $this->repo()->findOneByName($name);

		if($other !== null && $other->id() !== $type->id()) {
			throw new RuntimeException(__('Категория сборки с таким именем уже есть'));
		}

		$type->name = $name;
		$this->repo()->saveEntity($type);

		return $type;
	}

	public function delete(ConnectionCollectionType $type): void {
		$id = $type->id();
		(new CollectionService())->clearTypeId($id);
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
