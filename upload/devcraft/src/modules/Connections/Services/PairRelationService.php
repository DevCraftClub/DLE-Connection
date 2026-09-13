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
	 * @return list<array{to_news_id: int, relation_type: string, comment: string, is_manual_override: bool}>
	 */
	public function listForContext(int $collectionId, int $fromNewsId): array {
		$rows = [];

		foreach($this->repo()->findByCollectionFrom($collectionId, $fromNewsId) as $pair) {
			$rows[] = [
				'to_news_id'          => $pair->to_news_id,
				'relation_type'       => $pair->relation_type,
				'comment'             => $pair->comment,
				'is_manual_override'  => $pair->is_manual_override,
			];
		}

		return $rows;
	}

	/**
	 * Upsert пары из редактора. Не пишет обратное направление.
	 * Пустой relation_type сохраняет строку (явный empty override).
	 * Смена/очистка типа → is_manual_override=true; только комментарий → флаг не трогаем.
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
			// DevCraft ConnectionsAutomation: start
			$pair->is_manual_override = true;
			// DevCraft ConnectionsAutomation: end
			$pair->relation_type = $relationType;
			$pair->comment       = $comment;
			$this->repo()->saveEntity($pair);

			return $pair;
		}

		// DevCraft ConnectionsAutomation: start
		$typeChanged = $pair->relation_type !== $relationType;

		if($typeChanged) {
			$pair->is_manual_override = true;
		}
		// DevCraft ConnectionsAutomation: end

		$pair->relation_type = $relationType;
		$pair->comment       = $comment;
		$this->repo()->saveEntity($pair);

		return $pair;
	}

	/**
	 * Upsert из автоматизации.
	 *
	 * @param bool $clearOverride   full_reset: сбросить is_manual_override
	 * @param bool $skipIfOverride  preserve: не трогать тип у override-строк
	 *
	 * @return ConnectionPairRelation|null null если пропущено из‑за override
	 */
	public function upsertFromAutomation(
		int $collectionId,
		int $fromNewsId,
		int $toNewsId,
		string $relationType,
		string $comment = '',
		bool $clearOverride = true,
		bool $skipIfOverride = false,
	): ?ConnectionPairRelation {
		$relationType = trim($relationType);
		$comment      = trim($comment);

		if($fromNewsId <= 0 || $toNewsId <= 0 || $fromNewsId === $toNewsId) {
			return null;
		}

		$pair = $this->repo()->findByCollectionFromTo($collectionId, $fromNewsId, $toNewsId);

		if($pair !== null && $skipIfOverride && $pair->is_manual_override) {
			return null;
		}

		if($pair === null) {
			$pair                 = new ConnectionPairRelation();
			$pair->collection_id  = $collectionId;
			$pair->from_news_id   = $fromNewsId;
			$pair->to_news_id     = $toNewsId;
		}

		$pair->relation_type = $relationType;
		$pair->comment       = $comment;

		if($clearOverride || !$skipIfOverride) {
			$pair->is_manual_override = false;
		}

		$this->repo()->saveEntity($pair);

		return $pair;
	}

	/**
	 * Удаляет пары не из desired и с невалидными endpoint'ами.
	 *
	 * @param list<array{from_news_id:int, to_news_id:int, relation_type?:string}> $desired
	 * @param list<int> $visibleNewsIds
	 */
	public function deletePairsNotInDesired(
		int $collectionId,
		array $desired,
		array $visibleNewsIds,
		bool $preserveOverrides,
	): int {
		$desiredKeys = [];

		foreach($desired as $edge) {
			$from = (int) ($edge['from_news_id'] ?? 0);
			$to   = (int) ($edge['to_news_id'] ?? 0);

			if($from > 0 && $to > 0) {
				$desiredKeys[$from . ':' . $to] = true;
			}
		}

		$visible = [];

		foreach($visibleNewsIds as $newsId) {
			$newsId = (int) $newsId;

			if($newsId > 0) {
				$visible[$newsId] = true;
			}
		}

		$deleted = 0;

		foreach($this->repo()->findByCollection($collectionId) as $pair) {
			$fromOk = isset($visible[$pair->from_news_id]);
			$toOk   = isset($visible[$pair->to_news_id]);
			$key    = $pair->from_news_id . ':' . $pair->to_news_id;

			if(!$fromOk || !$toOk) {
				$this->repo()->deleteEntity($pair);
				$deleted++;
				continue;
			}

			if(isset($desiredKeys[$key])) {
				continue;
			}

			if($preserveOverrides && $pair->is_manual_override && $fromOk && $toOk) {
				continue;
			}

			$this->repo()->deleteEntity($pair);
			$deleted++;
		}

		return $deleted;
	}

	/**
	 * @return list<ConnectionPairRelation>
	 */
	public function findAllByCollection(int $collectionId): array {
		return $this->repo()->findByCollection($collectionId);
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
