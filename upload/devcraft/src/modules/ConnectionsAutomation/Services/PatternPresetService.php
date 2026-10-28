<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Services;

/**
 * Пресеты слотов условий для pattern_type.
 */
final class PatternPresetService {

	public const string PATTERN_LINEAR = 'linear';

	public const string PATTERN_HUB_SPOKE = 'hub_spoke';

	public const string PATTERN_SYMMETRIC = 'symmetric';

	public const string PATTERN_CUSTOM = 'custom';

	/**
	 * @return list<string>
	 */
	public function allowedPatterns(): array {
		return [
			self::PATTERN_LINEAR,
			self::PATTERN_HUB_SPOKE,
			self::PATTERN_SYMMETRIC,
			self::PATTERN_CUSTOM,
		];
	}

	public function isAllowedPattern(string $pattern): bool {
		return in_array($pattern, $this->allowedPatterns(), true);
	}

	/**
	 * Слоты пресета: condition_type → target_relation_type (по умолчанию пусто).
	 *
	 * @return array<string, string>
	 */
	public function defaultSlots(string $patternType): array {
		return match($patternType) {
			self::PATTERN_LINEAR => [
				'index_before' => '',
				'index_after'  => '',
			],
			self::PATTERN_HUB_SPOKE => [
				'from_hub' => '',
				'to_hub'   => '',
				'peer'     => '',
			],
			self::PATTERN_SYMMETRIC => [
				'all_pairs' => '',
			],
			default => [],
		};
	}

	/**
	 * Слот для пары индексов (data-model matrix). null = нет ребра из паттерна.
	 */
	public function slotForPair(string $patternType, int $fromIndex, int $toIndex): ?string {
		if($fromIndex === $toIndex) {
			return null;
		}

		return match($patternType) {
			self::PATTERN_LINEAR => $toIndex < $fromIndex
				? 'index_before'
				: 'index_after',
			self::PATTERN_HUB_SPOKE => $this->hubSpokeSlot($fromIndex, $toIndex),
			self::PATTERN_SYMMETRIC => 'all_pairs',
			default => null,
		};
	}

	private function hubSpokeSlot(int $fromIndex, int $toIndex): ?string {
		if($fromIndex === 0 && $toIndex > 0) {
			return 'from_hub';
		}

		if($fromIndex > 0 && $toIndex === 0) {
			return 'to_hub';
		}

		if($fromIndex > 0 && $toIndex > 0) {
			return 'peer';
		}

		return null;
	}

	/**
	 * Человекочитаемая метка паттерна для UI.
	 */
	public function patternLabel(string $patternType): string {
		return match($patternType) {
			self::PATTERN_LINEAR    => __('Линейная'),
			self::PATTERN_HUB_SPOKE => __('Звезда (hub-spoke)'),
			self::PATTERN_SYMMETRIC => __('Симметричная'),
			self::PATTERN_CUSTOM    => __('Своя'),
			default                 => $patternType,
		};
	}

}
