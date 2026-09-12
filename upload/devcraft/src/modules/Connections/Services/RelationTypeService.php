<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;
use DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository;

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
			JsonResponse::abort(
				__('Ошибка'),
				__('Название типа связи не может быть пустым'),
				'validation_failed',
				422,
			);
		}

		if(mb_strlen($name) > 100) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Название типа связи слишком длинное'),
				'validation_failed',
				422,
			);
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
			JsonResponse::abort(
				__('Ошибка'),
				__('Тип связи с таким именем уже есть'),
				'duplicate',
				409,
			);
		}

		$type             = new ConnectionRelationType();
		$type->name       = $name;
		$type->sort_order = $this->repo()->nextSortOrder();
		$this->repo()->saveEntity($type);

		return $type;
	}

	public function update(ConnectionRelationType $type, string $name): ConnectionRelationType {
		$name  = $this->validateName($name);
		$other = $this->repo()->findOneByName($name);

		if($other !== null && $other->id() !== $type->id()) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Тип связи с таким именем уже есть'),
				'duplicate',
				409,
			);
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
