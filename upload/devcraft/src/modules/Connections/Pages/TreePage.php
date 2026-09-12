<?php

declare(strict_types=1);

namespace DevCraft\Modules\Connections\Pages;

use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\Connections\Services\CollectionService;
use DevCraft\Modules\Connections\Services\CollectionTypeService;
use DevCraft\Modules\Connections\Services\RelationTypeService;
use DevCraft\Modules\Connections\Services\TreeViewService;

/**
 * Дерево сборок и элементов связей.
 */
final class TreePage extends AbstractPage {

	public function handle(): array {
		$pageName = __('Сборки');
		$this->addBreadcrumb($pageName);

		$view     = new TreeViewService();
		$assets   = $view->assetsBase();
		$tree     = (new CollectionService())->tree();
		$relationTypes = (new RelationTypeService())->list();
		$collectionTypes = (new CollectionTypeService())->list();

		return [
			'view' => 'connections/tree.twig',
			'data' => [
				'page_title'       => $pageName,
				'tree'             => $tree,
				'tree_html'        => $view->renderTree($tree, $assets),
				'relation_types'   => $relationTypes,
				'collection_types' => $collectionTypes,
				'assets_base'      => $assets,
			],
		];
	}

}
