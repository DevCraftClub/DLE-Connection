(function (window) {
	'use strict';

	if (!window.DevCraft) {
		// Админский скрипт: на публичной оболочке есть DevCraftPublic, не DevCraft.
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

	/**
	 * DevCraft ConnectionsAutomation: AJAX через host Connections
	 * (сателлит extends → методы вливаются в dle_connections).
	 */
	function postAuto(method, data) {
		if (window.DevCraftConnectionsAutomation && typeof window.DevCraftConnectionsAutomation.post === 'function') {
			return window.DevCraftConnectionsAutomation.post(method, data);
		}

		var payload = data || {};
		var loaderText = typeof payload.__loaderText === 'string'
			? payload.__loaderText
			: t('Выполняем запрос, пожалуйста подождите…');
		var silent = !!payload.__silent;
		if (payload.__loaderText !== undefined || payload.__silent !== undefined) {
			payload = Object.assign({}, payload);
			delete payload.__loaderText;
			delete payload.__silent;
		}

		const params = {
			controller: 'admin',
			method: method,
			mod: (document.body && document.body.dataset.mod) || 'dle_connections',
		};
		const url = Ajax.url(Ajax.baseUrl(), params);
		const body = new URLSearchParams({
			user_hash: Ajax.getUserHash(),
			data: JSON.stringify(payload),
		}).toString();

		var run = fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body,
		}).then(Ajax.parseResponse).then(function (res) {
			if (Ajax.handleNotice) {
				Ajax.handleNotice(res);
			}
			return res;
		});

		if (silent || !window.DevCraft || !DevCraft.Loader) {
			return run;
		}
		DevCraft.Loader.show(loaderText);
		return run.finally(function () {
			DevCraft.Loader.hide();
		});
	}

	function payloadData(payload) {
		if (!payload) return {};
		if (payload.data && typeof payload.data === 'object') return payload.data;
		return payload;
	}

	function showAutomationConflict(payload, collectionId) {
		var data = payloadData(payload);
		if (!data.automation_conflict) return;
		confirmDialog(
			t('Конфликт правил'),
			t('Для категории активно несколько правил автоматизации. Запустить Automate вручную?')
		).then(function (ok) {
			if (!ok || !collectionId) return;
			var ui = window.__dcConnLastUi;
			if (ui && typeof ui.automateCollection === 'function') {
				ui.automateCollection(collectionId);
			}
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
		if (typeof created.item === 'function') {
			var first = created.item(0);
			if (first && first.nodeType === 1) return first;
		}
		if (created.element && created.element[0]) return created.element[0];
		if (created.elem && created.elem.nodeType === 1) return created.elem;
		if (created.elem && created.elem[0]) return created.elem[0];
		return null;
	}

	/**
	 * Явное закрытие диалога.
	 * Metro hide() асинхронен; #dc-filter-dialog не трогаем — иначе залипает runtime-диалог.
	 */
	function closeDialog(createdOrEl) {
		var el = dialogNode(createdOrEl);
		if (el && el.id === 'dc-filter-dialog') {
			el = null;
		}
		if (!el || el.nodeType !== 1) {
			el = Array.prototype.find.call(document.querySelectorAll('body > .dialog'), function (d) {
				return d.id !== 'dc-filter-dialog' && getComputedStyle(d).visibility === 'visible';
			}) || null;
		}
		if (!el) return;

		try {
			var lib = window.Metro;
			var plugin = lib && typeof lib.getPlugin === 'function' ? lib.getPlugin(el, 'dialog') : null;
			if (plugin && typeof plugin.close === 'function') {
				plugin.close();
			} else if (Metro && typeof Metro.dialogClose === 'function') {
				Metro.dialogClose(el);
			}
		} catch (e) {
			/* ниже — форс-снятие */
		}

		// ponytail: Metro hide() через setTimeout — UI может «залипнуть»; форс через 0ms.
		setTimeout(function () {
			if (!el || !el.isConnected || el.id === 'dc-filter-dialog') {
				return;
			}
			if (getComputedStyle(el).visibility !== 'hidden') {
				el.style.visibility = 'hidden';
				el.style.top = '100%';
			}
			el.remove();
			var anyOpen = Array.prototype.some.call(document.querySelectorAll('body > .dialog'), function (d) {
				return d.id !== 'dc-filter-dialog' && getComputedStyle(d).visibility === 'visible';
			});
			if (!anyOpen) {
				document.querySelectorAll('body > .overlay').forEach(function (ov) {
					ov.remove();
				});
			}
		}, 0);
	}

	/** Крестик: не stopPropagation — иначе блокируется Metro `.js-dialog-close`. */
	function bindDialogCloser(created, onClick) {
		var root = dialogNode(created);
		if (!root) return;
		var closer = root.querySelector('span.closer');
		if (!closer || closer.getAttribute('data-dc-conn-close-bound')) return;
		closer.setAttribute('data-dc-conn-close-bound', '1');
		closer.addEventListener('click', function () {
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
			var contentHtml;
			if (frag) {
				var labelEl = frag.querySelector('[data-dc-conn-label]');
				var input = frag.querySelector('#dc-conn-ask-text');
				if (labelEl) labelEl.textContent = label || t('Название');
				if (input) input.value = initial || '';
				contentHtml = fragmentToHtml(frag);
			} else {
				contentHtml =
					'<label class="d-block mb-1">' + (label || t('Название')) + '</label>' +
					'<input type="text" id="dc-conn-ask-text" class="metro-input" style="width:100%" value="' +
					String(initial || '').replace(/"/g, '&quot;') + '">';
			}
			var dlg = Metro.dialogCreate({
				title: title,
				content: contentHtml,
				closeButton: true,
				defaultActions: false,
				removeOnClose: true,
				onClose: function () {
					done(pending === undefined ? null : pending);
				},
				customButtons: [
					{
						text: t('Сохранить'),
						cls: 'js-dialog-close primary',
						onclick: function (_btn, dialogEl) {
							var root = dialogNode(dialogEl || dlg);
							var el = root ? root.querySelector('#dc-conn-ask-text') : document.getElementById('dc-conn-ask-text');
							pending = el ? String(el.value).trim() : '';
							closeDialog(dialogEl || dlg);
							done(pending || null);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function (_btn, dialogEl) {
							pending = null;
							closeDialog(dialogEl || dlg);
							done(null);
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
			var contentHtml;
			if (frag) {
				var msgEl = frag.querySelector('[data-dc-conn-message]');
				if (msgEl) msgEl.textContent = message;
				contentHtml = fragmentToHtml(frag);
			} else {
				contentHtml = '<p>' + String(message || '').replace(/</g, '&lt;') + '</p>';
			}
			var dlg = Metro.dialogCreate({
				title: title,
				content: contentHtml,
				closeButton: true,
				defaultActions: false,
				removeOnClose: true,
				onClose: function () {
					done(pending);
				},
				customButtons: [
					{
						text: t('Удалить'),
						cls: 'js-dialog-close alert',
						onclick: function (_btn, dialogEl) {
							pending = true;
							closeDialog(dialogEl || dlg);
							done(true);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function (_btn, dialogEl) {
							pending = false;
							closeDialog(dialogEl || dlg);
							done(false);
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
						cls: 'js-dialog-close primary',
						onclick: function (_btn, dialogEl) {
							var root = dialogNode(dialogEl || dlg);
							var sid = selectId || 'dc-conn-select-collection';
							var el = root ? root.querySelector('#' + sid) : document.getElementById(sid);
							pending = el ? String(el.value) : '';
							closeDialog(dialogEl || dlg);
							done(pending);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function (_btn, dialogEl) {
							pending = null;
							closeDialog(dialogEl || dlg);
							done(null);
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
		this.automationEnabled = root.getAttribute('data-automation-enabled') === '1';
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
		window.__dcConnLastUi = this;
		if (this.automationEnabled) {
			this.showAutomationControls();
			this.refreshAutomationChips();
		}
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
			if (action === 'automate-collection' && self.automationEnabled) {
				self.automateCollection(colId);
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
		if (this.automationEnabled) {
			this.showAutomationControls();
			this.refreshAutomationChips();
		}
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
		var self = this;
		postSilent('reorder_items', { items: payload }).then(function (res) {
			var data = payloadData(res);
			if (data.automation_conflict) {
				var cid = payload[0] && payload[0].collection_id;
				showAutomationConflict(res, cid);
			}
			if (self.automationEnabled) {
				self.refreshAutomationChips();
			}
		});
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
			if (payload) {
				showAutomationConflict(payload, collectionId);
				return self.reload();
			}
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
		}).then(function (payload) {
			showAutomationConflict(payload, found.collectionId);
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
			var found = self.findItem(id);
			var cid = found ? found.collectionId : 0;
			return post('delete_item', { id: id }).then(function (payload) {
				showAutomationConflict(payload, cid);
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.toggleItem = function (id) {
		var self = this;
		var found = this.findItem(id);
		var cid = found ? found.collectionId : 0;
		post('toggle_item_visibility', { id: id }).then(function (payload) {
			showAutomationConflict(payload, cid);
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
					var root = dialogNode(dlg);
					var input = root ? root.querySelector('#dc-conn-news-q') : document.getElementById('dc-conn-news-q');
					var box = root ? root.querySelector('#dc-conn-news-results') : document.getElementById('dc-conn-news-results');
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
							closeDialog(dlg);
							done(pending);
						});
					}
				},
				onClose: function () {
					done(pending || null);
				},
				customButtons: [
					{
						text: t('Закрыть'),
						cls: '',
						onclick: function () {
							pending = null;
							closeDialog(dlg);
							done(null);
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

	/* DevCraft ConnectionsAutomation: start */
	ConnectionsUI.prototype.showAutomationControls = function () {
		if (!this.root) return;
		this.root.querySelectorAll('[data-dc-conn-auto-chip], [data-dc-conn-automate]').forEach(function (el) {
			el.hidden = false;
		});
	};

	ConnectionsUI.prototype.refreshAutomationChips = function () {
		if (!this.automationEnabled || !this.root) return;
		this.root.querySelectorAll('[data-dc-conn-auto-chip]').forEach(function (chip) {
			var cid = parseInt(chip.getAttribute('data-collection-id') || '0', 10);
			if (!cid) return;
			postAuto('list_rules_for_collection', { collection_id: cid }).then(function (payload) {
				var data = payloadData(payload);
				var rules = data.rules || [];
				var label = t('Нет правил');
				if (data.conflict || rules.length > 1) {
					label = t('Конфликт правил');
				} else if (rules.length === 1) {
					label = rules[0].pattern_type || rules[0].rule_name || label;
				}
				chip.textContent = label;
				chip.classList.toggle('alert', !!(data.conflict || rules.length > 1));
			}).catch(function () {
				chip.textContent = '—';
			});
		});
	};

	ConnectionsUI.prototype.automateCollection = function (collectionId) {
		var self = this;
		if (!collectionId || !this.automationEnabled) return;

		postAuto('list_rules_for_collection', { collection_id: collectionId }).then(function (payload) {
			var data = payloadData(payload);
			var rules = data.rules || [];
			if (!rules.length) {
				if (Metro && typeof Metro.dialogCreate === 'function') {
					Metro.dialogCreate({
						title: t('Automate'),
						content: '<p>' + t('Нет активных правил для категории этой сборки.') + '</p>',
						actions: [{ caption: t('OK'), cls: 'js-dialog-close primary' }],
					});
				}
				return;
			}

			var ruleOptions = rules.map(function (r, idx) {
				return '<option value="' + r.id + '"' + (idx === 0 ? ' selected' : '') + '>' +
					(r.rule_name || r.pattern_type) + ' (' + r.pattern_type + ')</option>';
			}).join('');

			var html =
				'<div class="mb-2"><label class="text-small">' + t('Правило') + '</label>' +
				'<select class="metro-input w-100" data-dc-auto-rule>' + ruleOptions + '</select></div>' +
				'<div><label class="text-small">' + t('Режим') + '</label>' +
				'<select class="metro-input w-100" data-dc-auto-mode>' +
				'<option value="preserve">' + t('Preserve (сохранить ручные типы)') + '</option>' +
				'<option value="full_reset">' + t('Full reset (перезаписать всё)') + '</option>' +
				'</select></div>';

			if (!Metro || typeof Metro.dialogCreate !== 'function') {
				return;
			}

			var dlg = Metro.dialogCreate({
				title: t('⚡ Automate'),
				content: html,
				actions: [
					{ caption: t('Отмена'), cls: 'js-dialog-close' },
					{
						caption: t('Запустить'),
						cls: 'js-dialog-close primary',
						onclick: function () {
							var node = dialogNode(dlg);
							var ruleSel = node ? node.querySelector('[data-dc-auto-rule]') : null;
							var modeSel = node ? node.querySelector('[data-dc-auto-mode]') : null;
							var ruleId = ruleSel ? parseInt(ruleSel.value, 10) : (rules[0] && rules[0].id) || 0;
							var mode = modeSel ? modeSel.value : 'preserve';
							postAuto('run_automation', {
								collection_id: collectionId,
								rule_id: ruleId,
								mode: mode,
							}).then(function () {
								return self.reload();
							});
						},
					},
				],
			});
		});
	};
	/* DevCraft ConnectionsAutomation: end */

	function CollectionTypesUI(root) {
		this.root = root;
		this.automationEnabled = root.getAttribute('data-automation-enabled') === '1';
		this.bind();
	}

	CollectionTypesUI.prototype.bind = function () {
		var self = this;
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
			var autoBtn = e.target.closest('[data-dc-conn-ctype-auto-rules]');
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
			if (autoBtn && self.automationEnabled) {
				self.openRulesDialog(id, row);
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

	CollectionTypesUI.prototype.openRulesDialog = function (categoryId, row) {
		var nameEl = row ? row.querySelector('.dc-conn-ctype-name') : null;
		var title = (nameEl ? nameEl.textContent.trim() : '') || ('#' + categoryId);
		var editBase = '?mod=' + encodeURIComponent((document.body && document.body.dataset.mod) || 'dle_connections')
			+ '&action=rule_edit&category_id=' + encodeURIComponent(String(categoryId));
		var html =
			'<div data-dc-conn-auto-rules data-category-id="' + categoryId + '">' +
			'<p class="text-small mb-2">' + t('Правила автоматизации для категории') + ': <strong>' + title + '</strong></p>' +
			'<div class="mb-2"><button type="button" class="button small primary" data-dc-auto-add-linear">' +
			t('Добавить линейное') + '</button> ' +
			'<a class="button small" href="' + editBase + '&id=0">' + t('Добавить правило') + '</a></div>' +
			'<div data-dc-auto-list class="text-small">' + t('Загрузка…') + '</div></div>';

		if (!Metro || typeof Metro.dialogCreate !== 'function') return;

		var dlg = Metro.dialogCreate({
			title: t('Правила авто'),
			content: html,
			width: 560,
			actions: [{ caption: t('Закрыть'), cls: 'js-dialog-close primary' }],
		});

		var node = dialogNode(dlg);
		if (!node) return;

		function reloadList() {
			var box = node.querySelector('[data-dc-auto-list]');
			if (!box) return;
			postAuto('auto_rules', { action: 'list', category_id: categoryId }).then(function (payload) {
				var items = (payloadData(payload).items) || [];
				if (!items.length) {
					box.innerHTML = '<p class="text-muted">' + t('Правил пока нет') + '</p>';
					return;
				}
				box.innerHTML = items.map(function (r) {
					return '<div class="d-flex flex-justify-between flex-align-center mb-1" data-rule-id="' + r.id + '">' +
						'<span>' + (r.rule_name || '') + ' · ' + r.pattern_type +
						(r.is_active ? '' : ' (' + t('выкл') + ')') + '</span>' +
						'<span>' +
						'<a class="button small" href="' + editBase + '&id=' + r.id + '">' + t('Изменить') + '</a> ' +
						'<button type="button" class="button small" data-dc-auto-toggle data-active="' + (r.is_active ? '1' : '0') + '">' +
						(r.is_active ? t('Выкл') : t('Вкл')) + '</button> ' +
						'<button type="button" class="button alert small" data-dc-auto-del">' + t('Удалить') + '</button>' +
						'</span></div>';
				}).join('');
			});
		}

		reloadList();

		node.addEventListener('click', function (e) {
			if (e.target.closest('[data-dc-auto-add-linear]')) {
				askText(t('Линейное правило'), t('Название'), t('Линейная')).then(function (name) {
					if (!name) return;
					return postAuto('auto_rules', {
						action: 'create_linear',
						category_id: categoryId,
						rule_name: name,
					}).then(function (payload) {
						var rule = (payloadData(payload).rule) || null;
						reloadList();
						if (rule && rule.id && Metro && typeof Metro.dialogCreate === 'function') {
							Metro.dialogCreate({
								title: t('Линейное правило создано'),
								content: '<p>' + t('Открыть редактор условий?') + '</p>',
								actions: [
									{ caption: t('Позже'), cls: 'js-dialog-close' },
									{
										caption: t('Открыть'),
										cls: 'js-dialog-close primary',
										onclick: function () {
											window.location.href = editBase + '&id=' + rule.id;
										},
									},
								],
							});
						}
					});
				});
			}
			var rowEl = e.target.closest('[data-rule-id]');
			if (!rowEl) return;
			var rid = parseInt(rowEl.getAttribute('data-rule-id'), 10);
			if (e.target.closest('[data-dc-auto-del]')) {
				confirmDialog(t('Удалить правило'), t('Удалить правило и его условия?')).then(function (ok) {
					if (!ok) return;
					return postAuto('auto_rules', {
						action: 'delete',
						id: rid,
						__loaderText: t('Удаляем правило…'),
					}).then(reloadList);
				});
			}
			var toggleBtn = e.target.closest('[data-dc-auto-toggle]');
			if (toggleBtn) {
				var currentlyOn = toggleBtn.getAttribute('data-active') === '1';
				postAuto('auto_rules', {
					action: 'set_active',
					id: rid,
					is_active: currentlyOn ? 0 : 1,
				}).then(reloadList);
			}
		});
	};

	/**
	 * Панель правил на странице Rules / embed (`data-dc-conn-auto-rules`).
	 * Диалог категорий использует отдельную разметку — см. openRulesDialog.
	 */
	function AutoRulesPanel(root) {
		this.root = root;
		this.categoryId = parseInt(root.getAttribute('data-category-id') || '0', 10);
		if (!this.categoryId) return;
		this.bind();
		this.reloadList();
	}

	AutoRulesPanel.prototype.bind = function () {
		var self = this;
		this.root.addEventListener('click', function (e) {
			if (e.target.closest('[data-dc-conn-auto-rule-add-linear]')) {
				askText(t('Линейное правило'), t('Название'), t('Линейная')).then(function (name) {
					if (!name) return;
					return postAuto('auto_rules', {
						action: 'create_linear',
						category_id: self.categoryId,
						rule_name: name,
					}).then(function (payload) {
						self.reloadList();
						var rule = (payloadData(payload).rule) || null;
						if (rule && rule.id && Metro && typeof Metro.dialogCreate === 'function') {
							Metro.dialogCreate({
								title: t('Линейное правило создано'),
								content: '<p>' + t('Открыть редактор условий?') + '</p>',
								actions: [
									{ caption: t('Позже'), cls: 'js-dialog-close' },
									{
										caption: t('Открыть'),
										cls: 'js-dialog-close primary',
										onclick: function () {
											window.location.href = self.editorHref(rule.id);
										},
									},
								],
							});
						}
					});
				});
				return;
			}
			if (e.target.closest('[data-dc-conn-auto-rule-add]')) {
				window.location.href = self.editorHref(0);
				return;
			}
			var rowEl = e.target.closest('[data-rule-id]');
			if (!rowEl) return;
			var rid = parseInt(rowEl.getAttribute('data-rule-id'), 10);
			if (e.target.closest('[data-dc-conn-auto-rule-edit]')) {
				window.location.href = self.editorHref(rid);
				return;
			}
			if (e.target.closest('[data-dc-conn-auto-rule-del]')) {
				confirmDialog(t('Удалить правило'), t('Удалить правило и его условия?')).then(function (ok) {
					if (!ok) return;
					return postAuto('auto_rules', {
						action: 'delete',
						id: rid,
						__loaderText: t('Удаляем правило…'),
					}).then(function () {
						self.reloadList();
					});
				});
				return;
			}
			var toggleBtn = e.target.closest('[data-dc-conn-auto-rule-toggle]');
			if (toggleBtn) {
				var currentlyOn = toggleBtn.getAttribute('data-active') === '1';
				postAuto('auto_rules', {
					action: 'set_active',
					id: rid,
					is_active: currentlyOn ? 0 : 1,
				}).then(function () {
					self.reloadList();
				});
			}
		});
	};

	AutoRulesPanel.prototype.editorHref = function (ruleId) {
		return '?mod=' + encodeURIComponent((document.body && document.body.dataset.mod) || 'dle_connections')
			+ '&action=rule_edit'
			+ '&category_id=' + encodeURIComponent(String(this.categoryId))
			+ '&id=' + encodeURIComponent(String(ruleId || 0));
	};

	AutoRulesPanel.prototype.reloadList = function () {
		var tbody = this.root.querySelector('[data-dc-conn-auto-rule-list]');
		if (!tbody) return;
		var self = this;
		tbody.innerHTML = '<tr><td colspan="4">' + t('Загрузка…') + '</td></tr>';
		postAuto('auto_rules', { action: 'list', category_id: this.categoryId }).then(function (payload) {
			var items = (payloadData(payload).items) || [];
			if (!items.length) {
				tbody.innerHTML =
					'<tr data-dc-conn-auto-rule-empty><td colspan="4">' + t('Правил пока нет') + '</td></tr>';
				return;
			}
			tbody.innerHTML = items.map(function (r) {
				var active = !!r.is_active;
				return '<tr data-rule-id="' + r.id + '">' +
					'<td>' + (r.rule_name || '') + '</td>' +
					'<td>' + (r.pattern_type || '') + '</td>' +
					'<td>' + (active ? t('Да') : t('Нет')) + '</td>' +
					'<td>' +
					'<a class="button small" href="' + self.editorHref(r.id) + '" data-dc-conn-auto-rule-edit>' +
					t('Изменить') + '</a> ' +
					'<button type="button" class="button small" data-dc-conn-auto-rule-toggle data-active="' +
					(active ? '1' : '0') + '">' + (active ? t('Выкл') : t('Вкл')) + '</button> ' +
					'<button type="button" class="button alert small" data-dc-conn-auto-rule-del>' +
					t('Удалить') + '</button>' +
					'</td></tr>';
			}).join('');
		}).catch(function () {
			tbody.innerHTML =
				'<tr><td colspan="4" class="fg-red">' + t('Не удалось загрузить правила') + '</td></tr>';
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
		document.querySelectorAll('[data-dc-conn-auto-rules]').forEach(function (el) {
			if (el.getAttribute('data-dc-auto-bound') === '1') return;
			el.setAttribute('data-dc-auto-bound', '1');
			new AutoRulesPanel(el);
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
