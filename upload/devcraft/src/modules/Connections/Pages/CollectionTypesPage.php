<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Pages;

use DLEPlugins;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Core\Admin\FilterFormService;
use DevCraft\Core\Application;
use DevCraft\Modules\Connections\Models\ConnectionCollectionType;
use DevCraft\Modules\Connections\Repositories\ConnectionCollectionTypeRepository;
use DevCraft\Types\FilterSchema;

/**
 * CRUD категорий сборок (`type_id`) со списком FilterFormService.
 */
final class CollectionTypesPage extends AbstractPage {

	public function handle(): array {
		$pageName = __('Категории сборок');
		$this->addBreadcrumb($pageName);

		$filterService = new FilterFormService();
		$query         = $filterService->parseRequestQuery();
		$schema        = $this->loadFilterSchema();
		$order         = FilterFormService::normalizeOrder(
			(string) ($query['order'] ?? $schema->defaultOrder),
			$schema,
		);
		$sort          = strtoupper((string) ($query['sort'] ?? 'ASC'));
		$perPage       = FilterFormService::resolveListCount();
		$rules         = $filterService->parseRules($query);

		/** @var ConnectionCollectionTypeRepository $repository */
		$repository = Application::instance()->database()->repository(ConnectionCollectionType::class);
		$criteria   = $filterService->rulesToCriteria($rules, $schema);
		$result     = $repository->findFiltered(
			$criteria,
			max(1, (int) ($query['page'] ?? 1)),
			$perPage,
			$order,
			$sort,
			$schema->sortColumnKeys(),
			$schema->defaultOrder,
		);

		$assetsBase = rtrim(
			Application::instance()->modulePublicAssetUrl(dirname(__DIR__)),
			'/',
		);

		return [
			'view' => 'connections/collection_types.twig',
			'data' => [
				'page_title'       => $pageName,
				'collection_types' => $result['items'],
				'total'            => $result['total'],
				'per_page'         => $perPage,
				'order'            => $order,
				'sort'             => $sort,
				'query'            => $query,
				'filter_rules'     => $rules,
				'filter_chips'     => $filterService->buildChipViewModel($rules, $schema),
				'filter_catalog'   => $filterService->buildCatalogViewModel($schema, $repository),
				'assets_base'      => $assetsBase,
			],
		];
	}

	private function loadFilterSchema(): FilterSchema {
		/** @var array<string, mixed> $raw */
		$raw = require DLEPlugins::Check(
			DEVCRAFT_MODULES . '/Connections/Filter/collection_types.filter.schema.php',
		);

		return FilterSchema::fromArray($raw);
	}

}
