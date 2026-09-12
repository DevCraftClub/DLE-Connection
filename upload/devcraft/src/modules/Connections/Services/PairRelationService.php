<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\Connections\Models\ConnectionPairRelation;
use DevCraft\Modules\Connections\Models\ConnectionRelationType;
use DevCraft\Modules\Connections\Repositories\ConnectionPairRelationRepository;
use DevCraft\Modules\Connections\Repositories\ConnectionRelationTypeRepository;

/**
 * CRUD направленных пар (контекст → цель) внутри сборки.
 */
final class PairRelationService {

	public function __construct(
		private readonly CollectionService $collections = new CollectionService(),
	) {}

	public function repo(): ConnectionPairRelationRepository {
		/** @var ConnectionPairRelationRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionPairRelation::class);

		return $repo;
	}

	/**
	 * @return list<array{to_news_id: int, relation_type: string, comment: string}>
	 */
	public function listForContext(int $collectionId, int $fromNewsId): array {
		$rows = [];

		foreach($this->repo()->findByCollectionFrom($collectionId, $fromNewsId) as $pair) {
			$rows[] = [
				'to_news_id'    => $pair->to_news_id,
				'relation_type' => $pair->relation_type,
				'comment'       => $pair->comment,
			];
		}

		return $rows;
	}

	/**
	 * Upsert пары. Не пишет обратное направление.
	 * Пустой relation_type сохраняет строку (явный empty override).
	 */
	public function upsert(
		int $collectionId,
		int $fromNewsId,
		int $toNewsId,
		string $relationType,
		string $comment = '',
	): ConnectionPairRelation {
		$relationType = trim($relationType);
		$comment      = trim($comment);

		if($fromNewsId <= 0 || $toNewsId <= 0) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Некорректные id новостей для пары'),
				'validation_failed',
				422,
			);
		}

		if($fromNewsId === $toNewsId) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Нельзя задать тип связи новости самой к себе'),
				'validation_failed',
				422,
			);
		}

		$this->assertMembers($collectionId, $fromNewsId, $toNewsId);
		$this->assertCatalogType($relationType);

		$pair = $this->repo()->findByCollectionFromTo($collectionId, $fromNewsId, $toNewsId);

		if($pair === null) {
			$pair                 = new ConnectionPairRelation();
			$pair->collection_id  = $collectionId;
			$pair->from_news_id   = $fromNewsId;
			$pair->to_news_id     = $toNewsId;
		}

		$pair->relation_type = $relationType;
		$pair->comment       = $comment;
		$this->repo()->saveEntity($pair);

		return $pair;
	}

	private function assertMembers(int $collectionId, int $fromNewsId, int $toNewsId): void {
		$items = $this->collections->itemsRepo();

		if($items->findByCollectionAndNews($collectionId, $fromNewsId) === null) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Контекстная новость не входит в сборку'),
				'validation_failed',
				422,
			);
		}

		if($items->findByCollectionAndNews($collectionId, $toNewsId) === null) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Целевая новость не входит в сборку'),
				'validation_failed',
				422,
			);
		}
	}

	private function assertCatalogType(string $relationType): void {
		if($relationType === '') {
			return;
		}

		/** @var ConnectionRelationTypeRepository $repo */
		$repo = Application::instance()->database()->repository(ConnectionRelationType::class);

		if($repo->findOneByName($relationType) === null) {
			JsonResponse::abort(
				__('Ошибка'),
				__('Тип связи должен быть из каталога'),
				'validation_failed',
				422,
			);
		}
	}

}
