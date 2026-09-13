<?php

declare(strict_types=1);

namespace DevCraft\Modules\ConnectionsAutomation\Pages;

use DevCraft\Core\Abstracts\AbstractPage;

/**
 * Журнал изменений сателлита.
 */
final class ChangelogPage extends AbstractPage {

	public function handle(): array {
		$pageName = __('Журнал изменений');
		$this->addBreadcrumb($pageName);

		return [
			'view' => 'pages/changelog.twig',
			'data' => [
				'page_title' => $pageName,
			],
		];
	}

}
