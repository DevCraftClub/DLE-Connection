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

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
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

	function icon(base, name) {
		return '<img src="' + escapeHtml(base + '/' + name) + '" alt="" width="16" height="16">';
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
			Metro.dialogCreate({
				title: title,
				content: '<label class="d-block mb-1">' + escapeHtml(label || t('Название')) + '</label>'
					+ '<input type="text" id="dc-conn-ask-text" class="metro-input" style="width:100%" value="'
					+ escapeHtml(initial || '') + '" />',
				closeButton: true,
				defaultActions: false,
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
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = null;
							done(null);
						},
					},
				],
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
			Metro.dialogCreate({
				title: title,
				content: '<p>' + escapeHtml(message) + '</p>',
				closeButton: true,
				defaultActions: false,
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
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = false;
							done(false);
						},
					},
				],
			});
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

	function ConnectionsUI(root) {
		this.root = root;
		this.assets = root.getAttribute('data-assets-base') || '';
		this.tree = parseJsonAttr(root, 'data-dc-conn-tree', []);
		this.types = parseJsonAttr(root, 'data-dc-conn-types', []);
		this.collectionsEl = root.querySelector('[data-dc-conn-collections]');
		this.bind();
		this.render();
	}

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
			var action = btn.getAttribute('data-action');
			var col = btn.closest('[data-collection-id]');
			var item = btn.closest('[data-item-id]');
			var colId = col ? parseInt(col.getAttribute('data-collection-id'), 10) : 0;
			var itemId = item ? parseInt(item.getAttribute('data-item-id'), 10) : 0;
			if (action === 'rename-collection') self.renameCollection(colId);
			if (action === 'delete-collection') self.deleteCollection(colId);
			if (action === 'copy-collection') self.copyCollection(colId);
			if (action === 'add-item') self.addItem(colId);
			if (action === 'delete-item') self.deleteItem(itemId);
			if (action === 'toggle-item') self.toggleItem(itemId);
			if (action === 'edit-item-type') self.editItemType(itemId);
		});
	};

	ConnectionsUI.prototype.reload = function () {
		var self = this;
		return post('tree', {}).then(function (payload) {
			var data = payload && payload.data ? payload.data : {};
			self.tree = data.tree || [];
			self.types = data.relation_types || self.types;
			self.render();
		});
	};

	ConnectionsUI.prototype.render = function () {
		if (!this.collectionsEl) return;
		if (!this.tree.length) {
			this.collectionsEl.innerHTML = '<div class="dc-conn-empty">' + escapeHtml(t('Сборок пока нет')) + '</div>';
			return;
		}
		var self = this;
		var html = this.tree.map(function (col) {
			return self.renderCollection(col);
		}).join('');
		this.collectionsEl.innerHTML = html;
		this.bindDnD();
	};

	ConnectionsUI.prototype.renderCollection = function (col) {
		var self = this;
		var items = (col.items || []).map(function (item) {
			return self.renderItem(item);
		}).join('');
		return '<div class="dc-conn-collection" data-collection-id="' + col.id + '" draggable="true">'
			+ '<div class="dc-conn-collection-head">'
			+ '<span class="dc-conn-drag" data-drag="collection">' + icon(this.assets, 'drag-handle.svg') + '</span>'
			+ '<span class="dc-conn-title">' + escapeHtml(col.title) + '</span>'
			+ '<span class="dc-conn-actions">'
			+ '<button type="button" class="button cycle outline" data-action="add-item" title="' + escapeHtml(t('Добавить новость')) + '">' + icon(this.assets, 'add-item.svg') + '</button>'
			+ '<button type="button" class="button cycle outline" data-action="rename-collection" title="' + escapeHtml(t('Переименовать')) + '">' + icon(this.assets, 'rename.svg') + '</button>'
			+ '<button type="button" class="button cycle outline" data-action="copy-collection" title="' + escapeHtml(t('Копировать')) + '">' + icon(this.assets, 'copy.svg') + '</button>'
			+ '<button type="button" class="button cycle outline alert" data-action="delete-collection" title="' + escapeHtml(t('Удалить')) + '">' + icon(this.assets, 'delete.svg') + '</button>'
			+ '</span></div>'
			+ '<ul class="dc-conn-items" data-items-for="' + col.id + '">' + (items || '') + '</ul>'
			+ '</div>';
	};

	ConnectionsUI.prototype.renderItem = function (item) {
		var cls = 'dc-conn-item';
		if (!item.is_visible) cls += ' is-hidden';
		if (item.is_focus) cls += ' is-focus';
		var visIcon = item.is_visible ? 'visibility-on.svg' : 'visibility-off.svg';
		var meta = item.relation_type
			? '<span class="dc-conn-item-meta">' + escapeHtml(item.relation_type) + '</span>'
			: '';
		return '<li class="' + cls + '" data-item-id="' + item.id + '" data-news-id="' + item.news_id + '" draggable="true">'
			+ '<span class="dc-conn-drag" data-drag="item">' + icon(this.assets, 'drag-handle.svg') + '</span>'
			+ '<span class="dc-conn-item-title flex-fill">' + escapeHtml(item.news_title || ('#' + item.news_id)) + '</span>'
			+ meta
			+ '<span class="dc-conn-actions">'
			+ '<button type="button" class="button cycle outline" data-action="edit-item-type" title="' + escapeHtml(t('Тип связи')) + '">' + icon(this.assets, 'relation-type.svg') + '</button>'
			+ '<button type="button" class="button cycle outline" data-action="toggle-item" title="' + escapeHtml(t('Видимость')) + '">' + icon(this.assets, visIcon) + '</button>'
			+ '<button type="button" class="button cycle outline alert" data-action="delete-item" title="' + escapeHtml(t('Удалить')) + '">' + icon(this.assets, 'delete.svg') + '</button>'
			+ '</span></li>';
	};

	ConnectionsUI.prototype.bindDnD = function () {
		var self = this;
		var collections = Array.from(this.collectionsEl.querySelectorAll('.dc-conn-collection'));
		collections.forEach(function (el) {
			el.addEventListener('dragstart', function (e) {
				if (e.target.closest('[data-item-id]')) return;
				e.dataTransfer.setData('text/dc-conn-collection', el.getAttribute('data-collection-id'));
				e.dataTransfer.effectAllowed = 'move';
			});
			el.addEventListener('dragover', function (e) {
				if (e.dataTransfer.types.indexOf('text/dc-conn-collection') !== -1) {
					e.preventDefault();
					el.classList.add('dc-conn-drop-target');
				}
			});
			el.addEventListener('dragleave', function () {
				el.classList.remove('dc-conn-drop-target');
			});
			el.addEventListener('drop', function (e) {
				el.classList.remove('dc-conn-drop-target');
				var fromId = e.dataTransfer.getData('text/dc-conn-collection');
				if (!fromId) return;
				e.preventDefault();
				var ids = Array.from(self.collectionsEl.querySelectorAll('.dc-conn-collection')).map(function (node) {
					return parseInt(node.getAttribute('data-collection-id'), 10);
				});
				var from = parseInt(fromId, 10);
				var to = parseInt(el.getAttribute('data-collection-id'), 10);
				var fromIdx = ids.indexOf(from);
				var toIdx = ids.indexOf(to);
				if (fromIdx < 0 || toIdx < 0 || fromIdx === toIdx) return;
				ids.splice(toIdx, 0, ids.splice(fromIdx, 1)[0]);
				postSilent('reorder_collections', { ids: ids }).then(function () {
					return self.reload();
				});
			});
		});

		this.collectionsEl.querySelectorAll('.dc-conn-items').forEach(function (list) {
			list.addEventListener('dragover', function (e) {
				if (e.dataTransfer.types.indexOf('text/dc-conn-item') !== -1) {
					e.preventDefault();
					list.classList.add('dc-conn-drop-target');
				}
			});
			list.addEventListener('dragleave', function () {
				list.classList.remove('dc-conn-drop-target');
			});
			list.addEventListener('drop', function (e) {
				list.classList.remove('dc-conn-drop-target');
				var itemId = parseInt(e.dataTransfer.getData('text/dc-conn-item'), 10);
				if (!itemId) return;
				e.preventDefault();
				var targetCol = parseInt(list.getAttribute('data-items-for'), 10);
				var payload = [];
				self.collectionsEl.querySelectorAll('.dc-conn-items').forEach(function (ul) {
					var colId = parseInt(ul.getAttribute('data-items-for'), 10);
					ul.querySelectorAll('[data-item-id]').forEach(function (li) {
						var id = parseInt(li.getAttribute('data-item-id'), 10);
						payload.push({
							id: id,
							collection_id: id === itemId ? targetCol : colId,
						});
					});
				});
				if (!payload.some(function (row) { return row.id === itemId; })) {
					payload.push({ id: itemId, collection_id: targetCol });
				}
				postSilent('reorder_items', { items: payload }).then(function () {
					return self.reload();
				});
			});
		});

		this.collectionsEl.querySelectorAll('[data-item-id]').forEach(function (li) {
			li.addEventListener('dragstart', function (e) {
				e.stopPropagation();
				e.dataTransfer.setData('text/dc-conn-item', li.getAttribute('data-item-id'));
				e.dataTransfer.effectAllowed = 'move';
			});
		});
	};

	ConnectionsUI.prototype.createCollection = function () {
		var self = this;
		askText(t('Новая сборка'), t('Название')).then(function (title) {
			if (!title) return;
			return post('save_collection', { title: title }).then(function () {
				return self.reload();
			});
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

	ConnectionsUI.prototype.deleteCollection = function (id) {
		var self = this;
		confirmDialog(t('Удалить сборку'), t('Удалить сборку и все её элементы?')).then(function (ok) {
			if (!ok) return;
			return post('delete_collection', { id: id }).then(function () {
				return self.reload();
			});
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
			return self.pickRelationType().then(function (rtype) {
				return post('save_item', {
					collection_id: collectionId,
					news_id: news.id,
					relation_type: rtype || '',
					is_visible: 1,
				});
			});
		}).then(function (payload) {
			if (payload) return self.reload();
		});
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
		this.pickRelationType().then(function (rtype) {
			if (rtype === null) return;
			return post('save_item', { id: id, relation_type: rtype }).then(function () {
				return self.reload();
			});
		});
	};

	ConnectionsUI.prototype.pickNews = function () {
		var self = this;
		return new Promise(function (resolve) {
			var settled = false;
			var pending;
			function done(value) {
				if (settled) return;
				settled = true;
				resolve(value);
			}
			Metro.dialogCreate({
				title: t('Поиск новости'),
				content: '<input type="search" id="dc-conn-news-q" class="metro-input mb-2" style="width:100%" placeholder="'
					+ escapeHtml(t('Название или ID')) + '" />'
					+ '<div id="dc-conn-news-results" class="list-group" style="max-height:240px;overflow:auto"></div>',
				closeButton: true,
				defaultActions: false,
				onOpen: function () {
					var input = document.getElementById('dc-conn-news-q');
					var box = document.getElementById('dc-conn-news-results');
					var timer = null;
					function runSearch() {
						var q = input ? input.value.trim() : '';
						if (q.length < 1) {
							box.innerHTML = '';
							return;
						}
						postSilent('search_news', { q: q }).then(function (payload) {
							var items = (payload && payload.data && payload.data.items) || [];
							box.innerHTML = items.map(function (row) {
								return '<button type="button" class="list-group-item list-group-item-action" data-news-id="'
									+ row.id + '" data-news-title="' + escapeHtml(row.title) + '">#'
									+ row.id + ' — ' + escapeHtml(row.title) + '</button>';
							}).join('') || '<div class="text-muted p-2">' + escapeHtml(t('Ничего не найдено')) + '</div>';
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
							var closeBtn = document.querySelector('.js-dialog-close');
							if (closeBtn) closeBtn.click();
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
						},
					},
				],
			});
		});
	};

	ConnectionsUI.prototype.pickRelationType = function () {
		var self = this;
		return new Promise(function (resolve) {
			var options = (self.types || []).map(function (type) {
				return '<option value="' + escapeHtml(type.name) + '">' + escapeHtml(type.name) + '</option>';
			}).join('');
			var pending;
			var settled = false;
			function done(value) {
				if (settled) return;
				settled = true;
				resolve(value);
			}
			Metro.dialogCreate({
				title: t('Тип связи'),
				content: '<label class="d-block mb-1">' + escapeHtml(t('Тип')) + '</label>'
					+ '<select id="dc-conn-rtype" class="metro-input" style="width:100%">'
					+ '<option value="">' + escapeHtml(t('— без типа —')) + '</option>'
					+ options + '</select>',
				closeButton: true,
				defaultActions: false,
				onClose: function () {
					done(pending === undefined ? null : pending);
				},
				customButtons: [
					{
						text: t('Выбрать'),
						cls: 'primary js-dialog-close',
						onclick: function () {
							var el = document.getElementById('dc-conn-rtype');
							pending = el ? String(el.value) : '';
							done(pending);
						},
					},
					{
						text: t('Отмена'),
						cls: 'js-dialog-close',
						onclick: function () {
							pending = null;
							done(null);
						},
					},
				],
			});
		});
	};

	function RelationTypesUI(root) {
		this.root = root;
		this.list = root.querySelector('[data-dc-conn-rtype-list]');
		this.bind();
	}

	RelationTypesUI.prototype.bind = function () {
		var self = this;
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

	function NewsFormUI(root) {
		this.root = root;
		this.newsId = parseInt(root.getAttribute('data-news-id') || '0', 10);
		this.box = root.querySelector('[data-dc-conn-news-tree]');
		this.assets = root.getAttribute('data-assets-base') || '';
		this.bind();
		this.reload();
	}

	NewsFormUI.prototype.bind = function () {
		var self = this;
		var add = this.root.querySelector('[data-dc-conn-news-add]');
		if (add) {
			add.addEventListener('click', function () {
				self.quickAdd();
			});
		}
	};

	NewsFormUI.prototype.reload = function () {
		var self = this;
		if (!this.box || this.newsId <= 0) return;
		postSilent('tree', { news_id: this.newsId }).then(function (payload) {
			var tree = (payload && payload.data && payload.data.tree) || [];
			var related = tree.filter(function (col) {
				return (col.items || []).some(function (item) {
					return item.news_id === self.newsId;
				});
			});
			if (!related.length) {
				self.box.innerHTML = '<p class="text-muted">' + escapeHtml(t('Новость пока не входит ни в одну сборку')) + '</p>';
				return;
			}
			self.box.innerHTML = related.map(function (col) {
				return '<div class="mb-2"><strong>' + escapeHtml(col.title) + '</strong></div>';
			}).join('');
		});
	};

	NewsFormUI.prototype.quickAdd = function () {
		var self = this;
		postSilent('tree', {}).then(function (payload) {
			var tree = (payload && payload.data && payload.data.tree) || [];
			var options = tree.map(function (col) {
				return '<option value="' + col.id + '">' + escapeHtml(col.title) + '</option>';
			}).join('');
			if (!options) {
				Metro.dialogCreate({
					title: t('Связи'),
					content: '<p>' + escapeHtml(t('Сначала создайте сборку в модуле Connections')) + '</p>',
					closeButton: true,
				});
				return;
			}
			var pending = null;
			Metro.dialogCreate({
				title: t('Добавить в сборку'),
				content: '<select id="dc-conn-pick-col" class="metro-input" style="width:100%">' + options + '</select>',
				closeButton: true,
				defaultActions: false,
				customButtons: [
					{
						text: t('Добавить'),
						cls: 'primary js-dialog-close',
						onclick: function () {
							var el = document.getElementById('dc-conn-pick-col');
							pending = el ? parseInt(el.value, 10) : 0;
							if (pending) {
								post('save_item', {
									collection_id: pending,
									news_id: self.newsId,
									is_visible: 1,
								}).then(function () {
									self.reload();
								});
							}
						},
					},
					{ text: t('Отмена'), cls: 'js-dialog-close' },
				],
			});
		});
	};

	function boot() {
		document.querySelectorAll('#dc-conn-dashboard').forEach(function (el) {
			new ConnectionsUI(el);
		});
		document.querySelectorAll('[data-dc-conn-settings]').forEach(function (el) {
			new RelationTypesUI(el);
		});
		document.querySelectorAll('[data-dc-conn-embed]').forEach(function (el) {
			document.body.dataset.mod = document.body.dataset.mod || 'Connections';
			new NewsFormUI(el);
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
