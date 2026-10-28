<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Support;

use DevCraft\Modules\Connections\Dto\NewsFormSnapshot;
use DevCraft\Modules\Connections\Services\NewsFormSyncService;
use RuntimeException;
use Throwable;

/**
 * Glue: чтение POST снимка (не domain Service).
 */
final class NewsFormSnapshotRequest {

	/**
	 * Применяет снимок из $_POST, если поле присутствует.
	 * Отсутствующее поле = no-op. Невалидный JSON/схема → исключение (ловить в патче).
	 */
	public static function applyFromPost(int $newsId): void {
		if($newsId <= 0) {
			return;
		}

		if(!array_key_exists('dc_connections_snapshot', $_POST)) {
			return;
		}

		$raw = $_POST['dc_connections_snapshot'];

		if(!is_string($raw) || trim($raw) === '') {
			throw new RuntimeException(__('Пустой снимок связей'));
		}

		$decoded = json_decode($raw, true);

		if(!is_array($decoded)) {
			throw new RuntimeException(__('Снимок связей: невалидный JSON'));
		}

		$snapshot = NewsFormSnapshot::fromArray($decoded);
		(new NewsFormSyncService())->applySnapshot($newsId, $snapshot);
	}

	/**
	 * Безопасный вызов для патчей addnews/editnews (не ломает Save новости).
	 */
	public static function tryApplyFromPost(int $newsId): void {
		try {
			self::applyFromPost($newsId);
		} catch(Throwable $e) {
			try {
				\DevCraft\Core\Logging\LogGenerator::for(self::class)
					->log($e->getMessage(), 'error');
			} catch(Throwable) {
				error_log('[Connections] NewsFormSnapshot: ' . $e->getMessage());
			}
		}
	}

}
