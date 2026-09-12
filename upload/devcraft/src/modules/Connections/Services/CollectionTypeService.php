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
	 * Slug: lowercase latin/digits/hyphen, unique.
	 */
	public function validateSlug(string $slug): string {
		$slug = strtolower(trim($slug));

		if($slug === '') {
			throw new RuntimeException(__('Slug категории не может быть пустым'));
		}

		if(mb_strlen($slug) > 100) {
			throw new RuntimeException(__('Slug категории слишком длинный'));
		}

		if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
			throw new RuntimeException(__('Slug: только латиница, цифры и дефис'));
		}

		return $slug;
	}

	/**
	 * Транслитерация имени в slug-кандидат (для backfill / default).
	 */
	public function slugFromName(string $name): string {
		$map = [
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
			'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
			'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
			'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
			'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
		];
		$lower = mb_strtolower(trim($name));
		$out   = '';

		$len = mb_strlen($lower);

		for($i = 0; $i < $len; $i++) {
			$ch = mb_substr($lower, $i, 1);

			if(isset($map[$ch])) {
				$out .= $map[$ch];
			} elseif(preg_match('/[a-z0-9]/', $ch)) {
				$out .= $ch;
			} elseif($ch === ' ' || $ch === '_' || $ch === '-') {
				$out .= '-';
			}
		}

		$out = preg_replace('/-+/', '-', $out) ?? '';
		$out = trim($out, '-');

		return $out !== '' ? $out : 'category';
	}

	/**
	 * Уникальный slug; при конфликте добавляет -2, -3…
	 */
	public function uniqueSlug(string $base, ?int $exceptId = null): string {
		$base = $this->validateSlug($base !== '' ? $base : 'category');
		$slug = $base;
		$n    = 2;

		while(true) {
			$found = $this->repo()->findOneBySlug($slug);

			if($found === null || ($exceptId !== null && $found->id() === $exceptId)) {
				return $slug;
			}

			$slug = $base . '-' . $n;
			$n++;
		}
	}

	/**
	 * Заполняет пустые slug у существующих категорий.
	 */
	public function backfillMissingSlugs(): void {
		foreach($this->repo()->findAllOrdered() as $type) {
			if(trim($type->slug) !== '') {
				continue;
			}

			$type->slug = $this->uniqueSlug($this->slugFromName($type->name), $type->id());
			$this->repo()->saveEntity($type);
		}
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
	 * @return list<array{id:int, name:string, slug:string, sort_order:int}>
	 */
	public function list(): array {
		$this->backfillMissingSlugs();
		$rows = [];

		foreach($this->repo()->findAllOrdered() as $type) {
			$rows[] = [
				'id'         => $type->id(),
				'name'       => $type->name,
				'slug'       => $type->slug,
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

	/**
	 * Карта id → slug.
	 *
	 * @return array<int, string>
	 */
	public function slugMap(): array {
		$this->backfillMissingSlugs();
		$map = [];

		foreach($this->repo()->findAllOrdered() as $type) {
			$map[$type->id()] = $type->slug;
		}

		return $map;
	}

	public function create(string $name, ?string $slug = null): ConnectionCollectionType {
		$name = $this->validateName($name);

		if($this->repo()->findOneByName($name) !== null) {
			throw new RuntimeException(__('Категория сборки с таким именем уже есть'));
		}

		$slugCandidate = $slug !== null && trim($slug) !== ''
			? trim($slug)
			: $this->slugFromName($name);

		$type             = new ConnectionCollectionType();
		$type->name       = $name;
		$type->slug       = $this->uniqueSlug($slugCandidate);
		$type->sort_order = $this->repo()->nextSortOrder();
		$this->repo()->saveEntity($type);

		return $type;
	}

	public function update(
		ConnectionCollectionType $type,
		string $name,
		?string $slug = null,
	): ConnectionCollectionType {
		$name  = $this->validateName($name);
		$other = $this->repo()->findOneByName($name);

		if($other !== null && $other->id() !== $type->id()) {
			throw new RuntimeException(__('Категория сборки с таким именем уже есть'));
		}

		$type->name = $name;

		if($slug !== null) {
			$type->slug = $this->uniqueSlug(
				trim($slug) !== '' ? trim($slug) : $this->slugFromName($name),
				$type->id(),
			);
		} elseif(trim($type->slug) === '') {
			$type->slug = $this->uniqueSlug($this->slugFromName($name), $type->id());
		}

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
