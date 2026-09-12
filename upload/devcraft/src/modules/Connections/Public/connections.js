(function (window) {
	'use strict';

	if (!window.DevCraft) {
		console.error('[Connections] Сначала должен быть загружен DevCraft core.');
		return;
	}

	const Ajax = window.DevCraft.Ajax;
	const Metro = window.DevCraft.Metro;

	function t(key) {
		return window.__ ? window.__(key) : key;
	}

	function post(method, data) {
		return Ajax.post(method, data || {}).then(function (payload) {
			if (Ajax.handleNotice) {
				Ajax.handleNotice(payload);
			}
			return payload;
		});
	}

	function postSilent(method, data) {
		const params = { controller: 'admin', method: method };
		const mod = document.body.dataset.mod;
		if (mod) {
			params.mod = mod;
		}
		const url = Ajax.url(Ajax.baseUrl(), params);
		const body = new URLSearchParams({
			user_hash: Ajax.getUserHash(),
			data: JSON.stringify(data || {}),
		}).toString();

		return fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body,
		}).then(Ajax.parseResponse).then(function (payload) {
			if (Ajax.handleNotice) {
				Ajax.handleNotice(payload);
			}
			return payload;
		});
	}

	function parseJsonAttr(el, name, fallback) {
		try {
			var raw = el.getAttribute(name);
			if (!raw) return fallback;
			return JSON.parse(raw);
		} catch (e) {
			return fallback;
		}
	}

	/** Клон Twig-<template>; опции/тексты — через DOM API. */
	function cloneTemplate(id) {
		var tpl = document.getElementById(id);
		if (!tpl || !tpl.content) {
			return null;
		}
		return tpl.content.cloneNode(true);
	}

	function fragmentToHtml(fragment) {
		var wrap = document.createElement('div');
		wrap.appendChild(fragment);
		return wrap.innerHTML;
	}

	/** DOM-элемент диалога из ответа Metro.dialogCreate / dialogApi().create. */
	function dialogNode(created) {
		if (!created) return null;
		if (created.nodeType === 1) return created;
		if (created[0] && created[0].nodeType === 1) return created[0];
		if (created.element && created.element[0]) return created.element[0];
		if (created.elem) return created.elem;
		return null;
	}

	/**
	 * Явное закрытие: у customButtons с onclick делегат `.js-dialog-close` часто не срабатывает.
	 */
	function closeDialog(createdOrEl) {
		var el = dialogNode(createdOrEl) || createdOrEl;
		if (!el) return;
		if (Metro && typeof Metro.dialogClose === 'function') {
			Metro.dialogClose(el);
			return;
		}
		var api = Metro && typeof Metro.dialogApi === 'function' ? Metro.dialogApi() : null;
		if (api && typeof api.close === 'function') {
			api.close(el);
			return;
		}
		var plugin = Metro && typeof Metro.getPlugin === 'function' ? Metro.getPlugin(el, 'dialog') : null;
		if (plugin && typeof plugin.close === 'function') {
			plugin.close();
		}
	}

	/** Крестик Metro: делегат `.js-dialog-close` ненадёжен вместе с customButtons.onclick. */
	function bindDialogCloser(created, onClick) {
		var root = dialogNode(created);
		if (!root) return;
		var closer = root.querySelector('span.closer');
		if (!closer || closer.getAttribute('data-dc-conn-close-bound')) return;
		closer.setAttribute('data-dc-conn-close-bound', '1');
		closer.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if (typeof onClick === 'function') onClick();
			closeDialog(created);
		});
	}

	function fillSelect(select, options, selected) {
		if (!select) return;
		select.innerHTML = '';
		(options || []).forEach(function (opt) {
			var el = document.createElement('option');
			el.value = String(opt.value);
			el.textContent = String(opt.label);
			if (String(opt.value) === String(selected)) {
				el.selected = true;
			}
			select.appendChild(el);
		});
	}

	function askText(title, label, initial) {
		return new Promise(function (resolve) {
			var pending;
			var settled = false;
			function done(value) {
				if (settled) return;
				settled = true;
				resolve(value);
			}
			if (!Metro || typeof Metro.dialogCreate !== 'function') {
				var fallback = window.prompt(label || title, initial || '');
				done(fallback === null ? null : String(fallback).trim());
				return;
			}
			var frag = cloneTemplate('dc-conn-tpl-ask-text');
			if (!frag) {
				var missing = window.prompt(label || title, initial || '');
				done(missing === null ? null : String(missing).trim());
				return;
			}
			var labelEl = frag.querySelector('[data-dc-conn-label]');
			var input = frag.querySelector('#dc-conn-ask-text');
			if (labelEl) labelEl.textContent = label || t('Название');
			if (input) input.value = initial || '';
			var dlg = Metro.dialogCreate({
				title: title,
				content: fragmentToHtml(frag),
				closeButton: true,
				defaultActions: false,
				removeOnClose: true,
				onClose: function () {
					done(pending === undefined ? null : pending);
				},
				customButtons: [
					{
						text: t('Сохранить'),
						cls: 'primary js-dialog-close',
						onclick: function () {
							var el = document.getElementById('dc-conn-ask-text');
							pending = el ? String(el.value).trim() : '';
							done(pending || null);
							closeDialog(dlg);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = null;
							done(null);
							closeDialog(dlg);
						},
					},
				],
			});
			bindDialogCloser(dlg, function () {
				pending = null;
				done(null);
			});
		});
	}

	function confirmDialog(title, message) {
		return new Promise(function (resolve) {
			var pending = false;
			var settled = false;
			function done(value) {
				if (settled) return;
				settled = true;
				resolve(value);
			}
			if (!Metro || typeof Metro.dialogCreate !== 'function') {
				done(window.confirm(message));
				return;
			}
			var frag = cloneTemplate('dc-conn-tpl-confirm');
			if (!frag) {
				done(window.confirm(message));
				return;
			}
			var msgEl = frag.querySelector('[data-dc-conn-message]');
			if (msgEl) msgEl.textContent = message;
			var dlg = Metro.dialogCreate({
				title: title,
				content: fragmentToHtml(frag),
				closeButton: true,
				defaultActions: false,
				removeOnClose: true,
				onClose: function () {
					done(pending);
				},
				customButtons: [
					{
						text: t('Удалить'),
						cls: 'alert js-dialog-close',
						onclick: function () {
							pending = true;
							done(true);
							closeDialog(dlg);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = false;
							done(false);
							closeDialog(dlg);
						},
					},
				],
			});
			bindDialogCloser(dlg, function () {
				pending = false;
				done(false);
			});
		});
	}

	function selectDialog(title, label, options, selected, okText, templateId, selectId) {
		return new Promise(function (resolve) {
			var pending;
			var settled = false;
			function done(value) {
				if (settled) return;
				settled = true;
				resolve(value);
			}
			if (!Metro || typeof Metro.dialogCreate !== 'function') {
				done(selected);
				return;
			}
			var frag = cloneTemplate(templateId || 'dc-conn-tpl-select-collection');
			if (!frag) {
				done(null);
				return;
			}
			var labelEl = frag.querySelector('[data-dc-conn-label]');
			var select = frag.querySelector('#' + (selectId || 'dc-conn-select-collection'));
			if (labelEl) labelEl.textContent = label;
			fillSelect(select, options, selected);
			var dlg = Metro.dialogCreate({
				title: title,
				content: fragmentToHtml(frag),
				closeButton: true,
				defaultActions: false,
				removeOnClose: true,
				onClose: function () {
					done(pending === undefined ? null : pending);
				},
				customButtons: [
					{
						text: okText || t('Выбрать'),
						cls: 'primary js-dialog-close',
						onclick: function () {
							var el = document.getElementById(selectId || 'dc-conn-select-collection');
							pending = el ? String(el.value) : '';
							done(pending);
							closeDialog(dlg);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = null;
							done(null);
							closeDialog(dlg);
						},
					},
				],
			});
			bindDialogCloser(dlg, function () {
				pending = null;
				done(null);
			});
		});
	}

	function ConnectionsUI(root) {
		this.root = root;
		this.assets = root.getAttribute('data-assets-base') || '';
		this.tree = parseJsonAttr(root, 'data-dc-conn-tree', []);
		/* Категории сборок (type_id) и типы связей элементов — разные справочники. */
		this.relationTypes = parseJsonAttr(root, 'data-dc-conn-relation-types', []);
		this.collectionTypes = parseJsonAttr(root, 'data-dc-conn-collection-types', []);
		this.contexts = {};
		this.collectionsEl = root.querySelector('[data-dc-conn-collections]');
		this.syncContextsFromTree();
		this.bind();
		this.bindTreeview();
		this.bindDrag();
	}

	ConnectionsUI.prototype.syncContextsFromTree = function () {
		var self = this;
		(this.tree || []).forEach(function (col) {
			if (col.context_news_id) {
				self.contexts[col.id] = col.context_news_id;
			}
		});
	};

	ConnectionsUI.prototype.bind = function () {
		var self = this;
		var addBtn = this.root.querySelector('[data-dc-conn-add-collection]');
		if (addBtn) {
			addBtn.addEventListener('click', function () {
				self.createCollection();
			});
		}
		this.root.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-action]');
			if (!btn || !self.root.contains(btn)) return;
			e.preventDefault();
			var action = btn.getAttribute('data-action');
			var col = btn.closest('[data-collection-id]');
			var item = btn.closest('[data-item-id]');
			var colId = col ? parseInt(col.getAttribute('data-collection-id'), 10) : 0;
			var itemId = item ? parseInt(item.getAttribute('data-item-id'), 10) : 0;
			if (action === 'save-collection') self.saveCollection(colId);
			if (action === 'rename-collection') self.renameCollection(colId);
			if (action === 'set-collection-type') self.setCollectionType(colId);
			if (action === 'delete-collection') self.deleteCollection(colId);
			if (action === 'copy-collection') self.copyCollection(colId);
			if (action === 'add-item') self.addItem(colId);
			if (action === 'save-item') self.saveItem(itemId);
			if (action === 'replace-news') self.replaceItemNews(itemId);
			if (action === 'copy-item') self.copyItem(itemId);
			if (action === 'delete-item') self.deleteItem(itemId);
			if (action === 'toggle-item') self.toggleItem(itemId);
			if (action === 'edit-item-type') self.editItemType(itemId);
			if (action === 'edit-item-comment') self.editItemComment(itemId);
			if (action === 'set-context') self.setItemContext(itemId);
			if (action === 'toggle-sequential') {
				var colEl = e.target.closest('[data-collection-id]');
				var cid = colEl ? parseInt(colEl.getAttribute('data-collection-id'), 10) : 0;
				if (cid) self.toggleSequential(cid);
			}
		});
	};

	ConnectionsUI.prototype.applyTreeHtml = function (html) {
		if (!this.collectionsEl || typeof html !== 'string') return;
		this.unbindDrag();
		this.unbindTreeview();
		this.collectionsEl.innerHTML = html;
		this.bindTreeview();
		this.bindDrag();
	};

	ConnectionsUI.prototype.unbindTreeview = function () {
		if (!Metro || typeof Metro.getPlugin !== 'function' || !this.collectionsEl) {
			return;
		}
		this.collectionsEl.querySelectorAll('[data-role="treeview"]').forEach(function (el) {
			try {
				var plugin = Metro.getPlugin(el, 'treeview');
				if (plugin && typeof plugin.destroy === 'function') {
					plugin.destroy();
				}
			} catch (e) {
				/* ещё не инициализирован */
			}
		});
	};

	ConnectionsUI.prototype.bindTreeview = function () {
		if (!Metro || typeof Metro.makePlugin !== 'function' || !this.collectionsEl) {
			return;
		}
		this.collectionsEl.querySelectorAll('[data-role="treeview"]').forEach(function (el) {
			try {
				Metro.makePlugin(el, 'treeview', {
					showChildCount: false,
				});
			} catch (e) {
				/* уже инициализирован автозапуском Metro */
			}
		});
	};

	ConnectionsUI.prototype.reload = function () {
		var self = this;
		return post('tree', { contexts: this.contexts || {} }).then(function (payload) {
			var data = payload && payload.data ? payload.data : {};
			self.tree = data.tree || [];
			self.relationTypes = data.relation_types || self.relationTypes;
			self.collectionTypes = data.collection_types || self.collectionTypes;
			self.syncContextsFromTree();
			if (typeof data.html === 'string') {
				self.applyTreeHtml(data.html);
			}
		});
	};

	/**
	 * Metro drag-items на двух уровнях (сборки + новости).
	 * Оба вешают .drag-items-target → drop смотрит elementsFromPoint и может
	 * утащить li в чужой контейнер. На время drag снимаем target с «другого» уровня.
	 * Маркеры: data-drag=collection|item — иначе outer closest(collection) ловит item-handle.
	 */
	ConnectionsUI.prototype.unbindDrag = function () {
		if (!Metro || typeof Metro.getPlugin !== 'function' || !this.collectionsEl) {
			return;
		}
		var destroyList = function (list) {
			try {
				var plugin = Metro.getPlugin(list, 'drag-items');
				if (plugin && typeof plugin.destroy === 'function') {
					plugin.destroy();
				}
			} catch (e) {
				/* плагин ещё не создан */
			}
			list.classList.remove('drag-items-target');
			list.querySelectorAll('.drag-item-marker').forEach(function (marker) {
				marker.remove();
			});
		};
		this.collectionsEl.querySelectorAll('.dc-conn-treeview').forEach(destroyList);
		this.collectionsEl.querySelectorAll('.dc-conn-items').forEach(destroyList);
	};

	ConnectionsUI.prototype.bindDrag = function () {
		var self = this;
		if (!Metro || typeof Metro.makePlugin !== 'function' || !this.collectionsEl) {
			return;
		}

		this.unbindDrag();

		var stripMarkers = function (root) {
			root.querySelectorAll('.drag-item-marker').forEach(function (marker) {
				marker.remove();
			});
		};

		var setTargets = function (collectionsOn, itemsOn) {
			self.collectionsEl.querySelectorAll('.dc-conn-treeview').forEach(function (el) {
				el.classList.toggle('drag-items-target', collectionsOn);
			});
			self.collectionsEl.querySelectorAll('.dc-conn-items').forEach(function (el) {
				el.classList.toggle('drag-items-target', itemsOn);
			});
		};

		this.collectionsEl.querySelectorAll('.dc-conn-treeview').forEach(function (tree) {
			Metro.makePlugin(tree, 'drag-items', {
				dragItem: 'li.dc-conn-collection',
				drawDragMarker: true,
				dragMarker: '.dc-conn-drag[data-drag="collection"]',
				canDrag: true,
				clsDragItemAvatar: 'dc-conn-drag-avatar',
				onDragStartItem: function () {
					setTargets(true, false);
				},
				onDragDropItem: function () {
					setTargets(true, true);
					self.persistCollectionsOrder();
				},
			});
			stripMarkers(tree);
		});

		this.collectionsEl.querySelectorAll('.dc-conn-items').forEach(function (list) {
			Metro.makePlugin(list, 'drag-items', {
				dragItem: 'li.dc-conn-item',
				/* true → старт только с dragMarker; Metro ещё дорисует .drag-item-marker — снимаем. */
				drawDragMarker: true,
				dragMarker: '.dc-conn-drag[data-drag="item"]',
				canDrag: true,
				clsDragItemAvatar: 'dc-conn-drag-avatar',
				onDragStartItem: function () {
					setTargets(false, true);
				},
				onDragDropItem: function () {
					setTargets(true, true);
					self.persistItemsOrder();
				},
			});
			stripMarkers(list);
		});
	};

	ConnectionsUI.prototype.persistCollectionsOrder = function () {
		var ids = [];
		this.collectionsEl.querySelectorAll('li.dc-conn-collection[data-collection-id]').forEach(function (li) {
			ids.push(parseInt(li.getAttribute('data-collection-id'), 10));
		});
		if (!ids.length) return;
		postSilent('reorder_collections', { ids: ids });
	};

	ConnectionsUI.prototype.persistItemsOrder = function () {
		var payload = [];
		this.collectionsEl.querySelectorAll('.dc-conn-items').forEach(function (ul) {
			var colId = parseInt(ul.getAttribute('data-items-for'), 10);
			ul.querySelectorAll('li.dc-conn-item[data-item-id]').forEach(function (li) {
				payload.push({
					id: parseInt(li.getAttribute('data-item-id'), 10),
					collection_id: colId,
				});
			});
		});
		if (!payload.length) return;
		postSilent('reorder_items', { items: payload });
	};

	ConnectionsUI.prototype.createCollection = function () {
		var self = this;
		this.pickNews().then(function (news) {
			if (!news || !news.id) return null;
			return askText(t('Новая сборка'), t('Название'), news.title || '').then(function (title) {
				if (!title) return null;
				return self.pickCollectionType(0).then(function (typeId) {
					if (typeId === null) return null;
					return post('save_collection', {
						title: title,
						type_id: typeId,
						news_id: news.id,
					});
				});
			});
		}).then(function (payload) {
			if (payload) return self.reload();
		});
	};

	ConnectionsUI.prototype.renameCollection = function (id) {
		var self = this;
		var col = this.tree.find(function (c) { return c.id === id; });
		askText(t('Переименовать сборку'), t('Название'), col ? col.title : '').then(function (title) {
			if (!title) return;
			return post('save_collection', { id: id, title: title }).then(function () {
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.setCollectionType = function (id) {
		var self = this;
		var col = this.tree.find(function (c) { return c.id === id; });
		this.pickCollectionType(col ? (col.type_id || 0) : 0).then(function (typeId) {
			if (typeId === null || !col) return null;
			return post('save_collection', {
				id: id,
				title: col.title,
				type_id: typeId,
			});
		}).then(function (payload) {
			if (payload) return self.reload();
		});
	};

	ConnectionsUI.prototype.deleteCollection = function (id) {
		var self = this;
		confirmDialog(t('Удалить сборку'), t('Удалить сборку и все её элементы?')).then(function (ok) {
			if (!ok) return;
			return post('delete_collection', { id: id }).then(function () {
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.saveCollection = function (id) {
		var self = this;
		var col = this.tree.find(function (c) { return c.id === id; });
		if (!col) return;
		post('save_collection', {
			id: id,
			title: col.title,
			type_id: col.type_id || 0,
		}).then(function () {
			return self.reload();
		});
	};

	ConnectionsUI.prototype.copyCollection = function (id) {
		var self = this;
		post('duplicate_collection', { id: id }).then(function () {
			return self.reload();
		});
	};

	ConnectionsUI.prototype.addItem = function (collectionId) {
		var self = this;
		this.pickNews().then(function (news) {
			if (!news) return null;
			return post('save_item', {
				collection_id: collectionId,
				news_id: news.id,
				relation_type: '',
				is_visible: 1,
			});
		}).then(function (payload) {
			if (payload) return self.reload();
		});
	};

	ConnectionsUI.prototype.saveItem = function (id) {
		var self = this;
		var found = this.findItem(id);
		if (!found) return;
		post('save_item', {
			id: id,
			news_id: found.item.news_id,
			relation_type: found.item.relation_type || '',
			is_visible: found.item.is_visible ? 1 : 0,
		}).then(function () {
			return self.reload();
		});
	};

	ConnectionsUI.prototype.replaceItemNews = function (id) {
		var self = this;
		this.pickNews().then(function (news) {
			if (!news) return null;
			return post('save_item', { id: id, news_id: news.id });
		}).then(function (payload) {
			if (payload) return self.reload();
		});
	};

	ConnectionsUI.prototype.copyItem = function (id) {
		var self = this;
		var found = this.findItem(id);
		if (!found) return;
		this.pickTargetCollection(found.collectionId).then(function (targetId) {
			if (!targetId) return null;
			return post('save_item', {
				collection_id: targetId,
				news_id: found.item.news_id,
				relation_type: found.item.relation_type || '',
				is_visible: found.item.is_visible ? 1 : 0,
			});
		}).then(function (payload) {
			if (payload) return self.reload();
		});
	};

	ConnectionsUI.prototype.findItem = function (itemId) {
		var found = null;
		this.tree.forEach(function (col) {
			(col.items || []).forEach(function (item) {
				if (item.id === itemId) {
					found = { item: item, collectionId: col.id };
				}
			});
		});
		return found;
	};

	ConnectionsUI.prototype.deleteItem = function (id) {
		var self = this;
		confirmDialog(t('Удалить элемент'), t('Убрать новость из сборки?')).then(function (ok) {
			if (!ok) return;
			return post('delete_item', { id: id }).then(function () {
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.toggleItem = function (id) {
		var self = this;
		post('toggle_item_visibility', { id: id }).then(function () {
			return self.reload();
		});
	};

	ConnectionsUI.prototype.editItemType = function (id) {
		var self = this;
		var found = this.findItem(id);
		if (!found) return;
		var col = this.tree.find(function (c) { return c.id === found.collectionId; });
		var fromId = (col && col.context_news_id) || (this.contexts[found.collectionId]) || 0;
		if (!fromId) {
			fromId = found.item.news_id;
		}
		if (fromId === found.item.news_id) {
			return;
		}
		var currentComment = found.item.comment || '';
		this.pickRelationType(found.item.relation_type || '').then(function (rtype) {
			if (rtype === null) return;
			return post('pair_relations', {
				action: 'upsert',
				collection_id: found.collectionId,
				from_news_id: fromId,
				to_news_id: found.item.news_id,
				relation_type: rtype,
				comment: currentComment,
			}).then(function () {
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.editItemComment = function (id) {
		var self = this;
		var found = this.findItem(id);
		if (!found) return;
		var col = this.tree.find(function (c) { return c.id === found.collectionId; });
		var fromId = (col && col.context_news_id) || (this.contexts[found.collectionId]) || 0;
		if (!fromId || fromId === found.item.news_id) return;
		askText(t('Комментарий к связи'), t('Комментарий'), found.item.comment || '').then(function (comment) {
			if (comment === null) return;
			return post('pair_relations', {
				action: 'upsert',
				collection_id: found.collectionId,
				from_news_id: fromId,
				to_news_id: found.item.news_id,
				relation_type: found.item.relation_type || '',
				comment: comment,
			}).then(function () {
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.setItemContext = function (id) {
		var found = this.findItem(id);
		if (!found) return;
		this.contexts[found.collectionId] = found.item.news_id;
		return this.reload();
	};

	ConnectionsUI.prototype.toggleSequential = function (collectionId) {
		var self = this;
		var col = this.tree.find(function (c) { return c.id === collectionId; });
		if (!col) return;
		var next = !col.is_sequential;
		return post('save_collection', {
			id: collectionId,
			title: col.title,
			description: col.description || '',
			type_id: col.type_id || 0,
			is_sequential: next ? 1 : 0,
		}).then(function () {
			return self.reload();
		});
	};

	ConnectionsUI.prototype.pickTargetCollection = function (excludeId) {
		var options = this.tree.filter(function (col) {
			return col.id !== excludeId;
		}).map(function (col) {
			return { value: String(col.id), label: col.title };
		});
		if (!options.length) {
			if (window.DevCraft && DevCraft.Notify && typeof DevCraft.Notify.warning === 'function') {
				DevCraft.Notify.warning(t('Связи'), t('Нет другой сборки для копирования'));
			}
			return Promise.resolve(null);
		}
		return selectDialog(
			t('Копировать в сборку'),
			t('Сборка'),
			options,
			options[0].value,
			t('Копировать'),
			'dc-conn-tpl-select-collection',
			'dc-conn-select-collection'
		)
			.then(function (val) {
				if (val === null || val === '') return null;
				return parseInt(val, 10) || null;
			});
	};

	ConnectionsUI.prototype.pickNews = function () {
		return new Promise(function (resolve) {
			var settled = false;
			var pending;
			function done(value) {
				if (settled) return;
				settled = true;
				resolve(value);
			}
			var frag = cloneTemplate('dc-conn-tpl-search-news');
			if (!frag || !Metro || typeof Metro.dialogCreate !== 'function') {
				done(null);
				return;
			}
			var dlg = Metro.dialogCreate({
				title: t('Поиск новости'),
				content: fragmentToHtml(frag),
				closeButton: true,
				defaultActions: false,
				removeOnClose: true,
				onOpen: function () {
					var input = document.getElementById('dc-conn-news-q');
					var box = document.getElementById('dc-conn-news-results');
					var timer = null;
					function runSearch() {
						var q = input ? input.value.trim() : '';
						if (!box) return;
						if (q.length < 1) {
							box.textContent = '';
							return;
						}
						postSilent('search_news', { q: q }).then(function (payload) {
							var html = payload && payload.data && payload.data.html;
							box.innerHTML = typeof html === 'string' ? html : '';
						});
					}
					if (input) {
						input.addEventListener('input', function () {
							clearTimeout(timer);
							timer = setTimeout(runSearch, 250);
						});
						input.focus();
					}
					if (box) {
						box.addEventListener('click', function (e) {
							var btn = e.target.closest('[data-news-id]');
							if (!btn) return;
							pending = {
								id: parseInt(btn.getAttribute('data-news-id'), 10),
								title: btn.getAttribute('data-news-title') || '',
							};
							done(pending);
							closeDialog(dlg);
						});
					}
				},
				onClose: function () {
					done(pending || null);
				},
				customButtons: [
					{
						text: t('Закрыть'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = null;
							done(null);
							closeDialog(dlg);
						},
					},
				],
			});
			bindDialogCloser(dlg, function () {
				pending = null;
				done(null);
			});
		});
	};

	ConnectionsUI.prototype.pickRelationType = function (current) {
		var selected = current == null ? '' : String(current);
		var options = [{ value: '', label: t('— без типа (подавить auto) —') }].concat(
			(this.relationTypes || []).map(function (type) {
				return { value: type.name, label: type.name };
			})
		);
		return selectDialog(
			t('Тип связи'),
			t('Тип'),
			options,
			selected,
			t('Выбрать'),
			'dc-conn-tpl-select-relation',
			'dc-conn-select-relation'
		);
	};

	ConnectionsUI.prototype.pickCollectionType = function (currentId) {
		var selected = parseInt(currentId || 0, 10) || 0;
		var options = [{ value: '0', label: t('— без категории —') }].concat(
			(this.collectionTypes || []).map(function (type) {
				return { value: String(type.id), label: type.name };
			})
		);
		return selectDialog(
			t('Категория сборки'),
			t('Категория'),
			options,
			String(selected),
			t('Выбрать'),
			'dc-conn-tpl-select-collection',
			'dc-conn-select-collection'
		)
			.then(function (val) {
				if (val === null) return null;
				return parseInt(val, 10) || 0;
			});
	};

	function RelationTypesUI(root) {
		this.root = root;
		this.bind();
	}

	RelationTypesUI.prototype.bind = function () {
		var add = this.root.querySelector('[data-dc-conn-rtype-add]');
		if (add) {
			add.addEventListener('click', function () {
				askText(t('Новый тип связи'), t('Название')).then(function (name) {
					if (!name) return;
					return post('relation_types', { action: 'create', name: name }).then(function () {
						location.reload();
					});
				});
			});
		}
		this.root.addEventListener('click', function (e) {
			var rename = e.target.closest('[data-dc-conn-rtype-rename]');
			var del = e.target.closest('[data-dc-conn-rtype-delete]');
			var row = e.target.closest('[data-id]');
			if (!row) return;
			var id = parseInt(row.getAttribute('data-id'), 10);
			if (rename) {
				var current = row.querySelector('.dc-conn-rtype-name');
				askText(t('Переименовать тип'), t('Название'), current ? current.textContent : '').then(function (name) {
					if (!name) return;
					return post('relation_types', { action: 'update', id: id, name: name }).then(function () {
						location.reload();
					});
				});
			}
			if (del) {
				confirmDialog(t('Удалить тип'), t('Удалить тип связи?')).then(function (ok) {
					if (!ok) return;
					return post('relation_types', { action: 'delete', id: id }).then(function () {
						location.reload();
					});
				});
			}
		});
	};

	function CollectionTypesUI(root) {
		this.root = root;
		this.bind();
	}

	CollectionTypesUI.prototype.bind = function () {
		var add = this.root.querySelector('[data-dc-conn-ctype-add]');
		if (add) {
			add.addEventListener('click', function () {
				askText(t('Новая категория сборки'), t('Название')).then(function (name) {
					if (!name) return;
					return post('collection_types', { action: 'create', name: name }).then(function () {
						location.reload();
					});
				});
			});
		}
		this.root.addEventListener('click', function (e) {
			var rename = e.target.closest('[data-dc-conn-ctype-rename]');
			var slugBtn = e.target.closest('[data-dc-conn-ctype-slug]');
			var del = e.target.closest('[data-dc-conn-ctype-delete]');
			var row = e.target.closest('[data-id]');
			if (!row) return;
			var id = parseInt(row.getAttribute('data-id'), 10);
			if (rename) {
				var current = row.querySelector('.dc-conn-ctype-name');
				askText(t('Переименовать категорию'), t('Название'), current ? current.textContent : '').then(function (name) {
					if (!name) return;
					return post('collection_types', { action: 'update', id: id, name: name }).then(function () {
						location.reload();
					});
				});
			}
			if (slugBtn) {
				var curSlug = row.querySelector('.dc-conn-ctype-slug');
				askText(t('Slug категории'), t('Slug'), curSlug ? curSlug.textContent.trim() : '').then(function (slug) {
					if (slug === null) return;
					var nameEl = row.querySelector('.dc-conn-ctype-name');
					return post('collection_types', {
						action: 'update',
						id: id,
						name: nameEl ? nameEl.textContent.trim() : '',
						slug: slug,
					}).then(function () {
						location.reload();
					});
				});
			}
			if (del) {
				confirmDialog(
					t('Удалить категорию'),
					t('Удалить категорию? У сборок type_id сбросится в «без категории».')
				).then(function (ok) {
					if (!ok) return;
					return post('collection_types', { action: 'delete', id: id }).then(function () {
						location.reload();
					});
				});
			}
		});
	};

	function boot() {
		document.querySelectorAll('#dc-conn-dashboard').forEach(function (el) {
			new ConnectionsUI(el);
		});
		document.querySelectorAll('[data-dc-conn-rtype-page]').forEach(function (el) {
			new RelationTypesUI(el);
		});
		document.querySelectorAll('[data-dc-conn-ctype-page]').forEach(function (el) {
			new CollectionTypesUI(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.DevCraftConnections = {
		reload: function () {
			boot();
		},
	};
})(window);
