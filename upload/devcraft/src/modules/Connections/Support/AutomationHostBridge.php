<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Support;

use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\ResponseInterface;

/**
 * Мост к сателлиту ConnectionsAutomation (мягкая зависимость).
 *
 * // DevCraft ConnectionsAutomation: host bridge
 */
final class AutomationHostBridge {

	/**
	 * Вызывает хук после изменения состава/порядка и мержит результат в JSON.
	 */
	public static function afterMembershipSaved(int $collectionId, ResponseInterface $response): ResponseInterface {
		if($collectionId <= 0) {
			return $response;
		}

		// DevCraft ConnectionsAutomation: start
		$gateClass = '\\DevCraft\\Modules\\ConnectionsAutomation\\Services\\FeatureGateService';
		$hookClass = '\\DevCraft\\Modules\\ConnectionsAutomation\\Services\\AutomationHookService';

		if(!class_exists($gateClass) || !class_exists($hookClass)) {
			return $response;
		}

		/** @var class-string $gateClass */
		if(!$gateClass::isEnabled()) {
			return $response;
		}

		try {
			/** @var object $hook */
			$hook  = new $hookClass();
			$extra = $hook->onCollectionMembershipSaved($collectionId);
		} catch(\Throwable) {
			return $response;
		}

		if($extra === [] || !($response instanceof JsonResponse)) {
			return $response;
		}

		return $response->withData($extra);
		// DevCraft ConnectionsAutomation: end
	}

	public static function isEnabled(): bool {
		// DevCraft ConnectionsAutomation: start
		$gateClass = '\\DevCraft\\Modules\\ConnectionsAutomation\\Services\\FeatureGateService';

		return class_exists($gateClass) && $gateClass::isEnabled();
		// DevCraft ConnectionsAutomation: end
	}

}
