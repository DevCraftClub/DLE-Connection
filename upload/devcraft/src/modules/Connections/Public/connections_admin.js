/**
 * Вкладка «Связи» на DLE addnews/editnews.
 * Стек: jQuery 3 + jqueryui.js (DLEPush) + application.js (DLEprompt/DLEconfirm/DLEalert).
 * Черновик в hidden dc_connections_snapshot.
 */
(function (window, document, $) {
	'use strict';

	if (typeof $ === 'undefined') {
		console.error('[Connections] Нужен jQuery (DLE jquery_version=3).');
		return;
	}

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

	function parseJsonAttr(el, name, fallback) {
		try {
			var raw = el.getAttribute(name);
			if (!raw) {
				return fallback;
			}
			return JSON.parse(raw);
		} catch (e) {
			return fallback;
		}
	}

	function notify(message, type) {
		var title = t('Связи');
		if (window.DLEPush) {
			if (type === 'error' && typeof DLEPush.error === 'function') {
				DLEPush.error(message, title);
				return;
			}
			if (type === 'warning' && typeof DLEPush.warning === 'function') {
				DLEPush.warning(message, title);
				return;
			}
			if (typeof DLEPush.info === 'function') {
				DLEPush.info(message, title);
				return;
			}
		}
		if (typeof window.DLEalert === 'function') {
			DLEalert(message, title);
			return;
		}
		window.alert(message);
	}

	function promptText(message, initial, title, allowEmpty) {
		return new Promise(function (resolve) {
			if (typeof window.DLEprompt !== 'function') {
				var fallback = window.prompt(message, initial || '');
				resolve(fallback === null ? null : String(fallback));
				return;
			}
			DLEprompt(
				escapeHtml(message),
				initial || '',
				title || t('Связи'),
				function (value) {
					resolve(value === undefined || value === null ? null : String(value));
				},
				!!allowEmpty
			);
		});
	}

	function confirmAction(message, title) {
		return new Promise(function (resolve) {
			if (typeof window.DLEconfirm !== 'function') {
				resolve(window.confirm(message));
				return;
			}
			var done = false;
			function finish(ok) {
				if (done) {
					return;
				}
				done = true;
				resolve(ok);
			}
			DLEconfirm(escapeHtml(message), title || t('Подтверждение'), function () {
				finish(true);
			});
			var watch = setInterval(function () {
				if (!$('#dlepopup').length) {
					clearInterval(watch);
					finish(false);
				}
			}, 120);
		});
	}

	/**
	 * Выбор из <select> через jQuery UI dialog (разметка из Twig-<template>).
	 *
	 * @param {string} title
	 * @param {string} label
	 * @param {Array<{value:string, label:string}>} options
	 * @param {string} [selected]
	 * @returns {Promise<string|null>}
	 */
	function selectDialog(title, label, options, selected) {
		return new Promise(function (resolve) {
			var ww = typeof window.getBaseSize === 'function' ? 500 * getBaseSize() : 500;
			if (ww > $(window).width() * 0.95) {
				ww = $(window).width() * 0.95;
			}

			if ($('#dlepopup').length) {
				$('#dlepopup').dialog('close').remove();
			}

			var buttons = {};
			var cancelLabel = (window.dle_act_lang && dle_act_lang[3]) ? dle_act_lang[3] : t('Отмена');
			var okLabel = (window.dle_act_lang && dle_act_lang[0]) ? dle_act_lang[0] : t('OK');

			buttons[cancelLabel] = function () {
				$(this).dialog('close');
				$('#dlepopup').remove();
				resolve(null);
			};
			buttons[okLabel] = function () {
				var val = String($('#dc-conn-select').val() || '');
				$(this).dialog('close');
				$('#dlepopup').remove();
				resolve(val);
			};

			var tpl = document.getElementById('dc-conn-nf-tpl-select');
			var $popup = $('<div>', { id: 'dlepopup', 'class': 'dle-promt', title: title }).hide();
			if (tpl && tpl.content) {
				$popup.append(tpl.content.cloneNode(true));
			} else {
				$popup.append($('<label>', { 'class': 'control-label', text: label }));
				$popup.append($('<select>', { id: 'dc-conn-select', 'class': 'uniform form-control' }));
			}
			var $label = $popup.find('[data-dc-conn-label]');
			if ($label.length) {
				$label.text(label);
			}
			var $select = $popup.find('#dc-conn-select');
			$select.empty();
			(options || []).forEach(function (opt) {
				var $opt = $('<option>', { value: String(opt.value), text: String(opt.label) });
				if (String(opt.value) === String(selected || '')) {
					$opt.prop('selected', true);
				}
				$select.append($opt);
			});

			$('body').append($popup);

			$('#dlepopup').dialog({
				autoOpen: true,
				width: ww,
				resizable: false,
				buttons: buttons
			});
		});
	}

	function ajaxRoot() {
		return (typeof window.dle_root !== 'undefined' ? String(window.dle_root) : '/');
	}

	function userHash() {
		return String(window.dle_login_hash || '');
	}

	/**
	 * Read-only поиск новостей (не mutating Save).
	 *
	 * @param {string} mod
	 * @param {string} q
	 * @returns {Promise<{items:Array<{id:number,title:string}>, html:string}>}
	 */
	function searchNewsAjax(mod, q) {
		var url = ajaxRoot() + 'devcraft/ajax.php?controller=admin&mod='
			+ encodeURIComponent(mod || 'dle_connections')
			+ '&method=search_news';
		return new Promise(function (resolve, reject) {
			$.ajax({
				url: url,
				type: 'POST',
				dataType: 'json',
				data: {
					user_hash: userHash(),
					data: JSON.stringify({ q: q, limit: 20 }),
				},
			}).done(function (payload) {
				var data = payload && payload.data ? payload.data : {};
				resolve({
					items: Array.isArray(data.items) ? data.items : [],
					html: typeof data.html === 'string' ? data.html : '',
				});
			}).fail(function (xhr) {
				var msg = t('Ошибка поиска');
				try {
					var parsed = JSON.parse(xhr.responseText || '{}');
					if (parsed && parsed.message) {
						msg = String(parsed.message);
					}
				} catch (e) { /* ignore */ }
				reject(new Error(msg));
			});
		});
	}

	/**
	 * AJAX сателлита Automate через host mod (list_rules / run_automation).
	 *
	 * @param {string} mod
	 * @param {string} method
	 * @param {object} data
	 * @returns {Promise<object>}
	 */
	function postAutoAjax(mod, method, data) {
		var url = ajaxRoot() + 'devcraft/ajax.php?controller=admin&mod='
			+ encodeURIComponent(mod || 'dle_connections')
			+ '&method=' + encodeURIComponent(method);
		return new Promise(function (resolve, reject) {
			$.ajax({
				url: url,
				type: 'POST',
				dataType: 'json',
				data: {
					user_hash: userHash(),
					data: JSON.stringify(data || {}),
				},
			}).done(function (payload) {
				if (payload && payload.success === false) {
					reject(new Error(String(payload.message || t('Ошибка Automate'))));
					return;
				}
				resolve(payload && payload.data ? payload.data : (payload || {}));
			}).fail(function (xhr) {
				var msg = t('Ошибка Automate');
				try {
					var parsed = JSON.parse(xhr.responseText || '{}');
					if (parsed && parsed.message) {
						msg = String(parsed.message);
					}
				} catch (e) { /* ignore */ }
				reject(new Error(msg));
			});
		});
	}

	/**
	 * Диалог поиска новости (jQuery UI, без Metro).
	 *
	 * @param {string} mod
	 * @returns {Promise<{id:number, title:string}|null>}
	 */
	function pickNewsDialog(mod) {
		return new Promise(function (resolve) {
			var settled = false;
			function done(value) {
				if (settled) {
					return;
				}
				settled = true;
				resolve(value);
			}

			var ww = typeof window.getBaseSize === 'function' ? 560 * getBaseSize() : 560;
			if (ww > $(window).width() * 0.95) {
				ww = $(window).width() * 0.95;
			}

			if ($('#dlepopup').length) {
				$('#dlepopup').dialog('close').remove();
			}

			var tpl = document.getElementById('dc-conn-nf-tpl-search-news');
			var $popup = $('<div>', { id: 'dlepopup', 'class': 'dle-promt', title: t('Добавить новость') }).hide();
			if (tpl && tpl.content) {
				$popup.append(tpl.content.cloneNode(true));
			} else {
				$popup.append($('<input>', {
					type: 'text',
					id: 'dc-conn-nf-news-q',
					'class': 'form-control',
					placeholder: t('Название или ID'),
				}));
				$popup.append($('<div>', { id: 'dc-conn-nf-news-results', 'class': 'dc-conn-nf-search-results list-group' }));
			}

			$('body').append($popup);

			var timer = null;
			var $input = $popup.find('#dc-conn-nf-news-q');
			var $box = $popup.find('#dc-conn-nf-news-results');

			function runSearch() {
				var q = String($input.val() || '').trim();
				if (q.length < 1) {
					$box.empty();
					return;
				}
				searchNewsAjax(mod, q).then(function (res) {
					$box.html(res.html || '');
					if (!res.html && res.items.length) {
						$box.empty();
						res.items.forEach(function (row) {
							$box.append(
								$('<button>', {
									type: 'button',
									'class': 'list-group-item list-group-item-action',
									'data-news-id': row.id,
									'data-news-title': row.title,
									text: '#' + row.id + ' — ' + row.title,
								})
							);
						});
					}
				}).catch(function (err) {
					notify(err.message || t('Ошибка поиска'), 'error');
				});
			}

			$input.on('input', function () {
				clearTimeout(timer);
				timer = setTimeout(runSearch, 250);
			});

			$box.on('click', '[data-news-id]', function (e) {
				e.preventDefault();
				var $btn = $(this);
				var id = parseInt($btn.attr('data-news-id'), 10);
				if (!id) {
					return;
				}
				/* done() до close: jQuery UI close синхронно, иначе done(null) перебивает выбор. */
				done({
					id: id,
					title: String($btn.attr('data-news-title') || $btn.text() || ''),
				});
				$('#dlepopup').dialog('close').remove();
			});

			var buttons = {};
			var cancelLabel = (window.dle_act_lang && dle_act_lang[3]) ? dle_act_lang[3] : t('Отмена');
			buttons[cancelLabel] = function () {
				done(null);
				$(this).dialog('close');
				$('#dlepopup').remove();
			};

			$('#dlepopup').dialog({
				autoOpen: true,
				width: ww,
				resizable: false,
				buttons: buttons,
				close: function () {
					done(null);
					$(this).remove();
				},
			});

			setTimeout(function () {
				$input.trigger('focus');
			}, 50);
		});
	}

	function NewsFormDraft(root) {
		this.$root = $(root);
		this.root = root;
		this.newsId = parseInt(root.getAttribute('data-news-id') || '0', 10);
		this.assets = root.getAttribute('data-assets-base') || '';
		this.ajaxMod = root.getAttribute('data-ajax-mod') || 'dle_connections';
		this.editNewsUrl = root.getAttribute('data-admin-edit-news-url') || '';
		this.$input = this.$root.find('#dc_connections_snapshot, input[name="dc_connections_snapshot"]').first();
		this.$list = this.$root.find('[data-dc-conn-memberships]');
		this.collections = parseJsonAttr(root, 'data-dc-conn-collections', []);
		this.treesById = parseJsonAttr(root, 'data-dc-conn-trees', {});
		if (!this.treesById || typeof this.treesById !== 'object') {
			this.treesById = {};
		}
		this.types = parseJsonAttr(root, 'data-dc-conn-relation-types', []);
		if (!Array.isArray(this.types) || !this.types.length) {
			this.types = parseJsonAttr(root, 'data-dc-conn-types', []);
		}
		this.collectionTypes = parseJsonAttr(root, 'data-dc-conn-collection-types', []);
		if (!Array.isArray(this.collectionTypes)) {
			this.collectionTypes = [];
		}
		this.automationEnabled = root.getAttribute('data-automation-enabled') === '1';
		this.snapshot = parseJsonAttr(root, 'data-dc-conn-snapshot', null);
		if (!this.snapshot || typeof this.snapshot !== 'object') {
			this.snapshot = { version: 1, memberships: [], new_collections: [] };
		}
		if (!Array.isArray(this.snapshot.memberships)) {
			this.snapshot.memberships = [];
		}
		if (!Array.isArray(this.snapshot.new_collections)) {
			this.snapshot.new_collections = [];
		}
		this.snapshot.version = 1;
		var selfInit = this;
		this.snapshot.memberships.forEach(function (m) {
			if (m.type_id === undefined || m.type_id === null) {
				m.type_id = selfInit.resolveTypeId(m);
			} else {
				m.type_id = parseInt(m.type_id, 10) || 0;
			}
			selfInit.ensureMembershipItems(m);
		});
		this.snapshot.new_collections.forEach(function (nc) {
			nc.type_id = parseInt(nc.type_id, 10) || 0;
		});
		this.sortables = [];
		this.bind();
		this.syncInput();
		this.render();
	}

	NewsFormDraft.prototype.bind = function () {
		var self = this;
		this.$root.on('click', '[data-dc-conn-add-new]', function (e) {
			e.preventDefault();
			self.addNewCollection();
		});
		this.$root.on('click', '[data-action]', function (e) {
			var $btn = $(this);
			if (!$btn.closest(self.$root).length) {
				return;
			}
			e.preventDefault();
			var action = $btn.attr('data-action');
			var key = $btn.closest('[data-row-key]').attr('data-row-key') || '';
			var $item = $btn.closest('.dc-conn-nf-item');
			var newsId = $item.length
				? parseInt($item.attr('data-news-id') || '0', 10)
				: self.newsId;
			if (action === 'remove') {
				/* Шапка: убрать текущую новость из сборки (membership). */
				self.removeMembership(key);
			}
			if (action === 'remove-item') {
				self.removeItemFromCollection(key, newsId);
			}
			if (action === 'add-item') {
				self.addItemToCollection(key);
			}
			if (action === 'replace-news') {
				self.replaceItemNews(key, newsId);
			}
			if (action === 'copy-item') {
				self.copyItemToCollection(key, newsId);
			}
			if (action === 'copy-collection') {
				self.copyCollectionDraft(key);
			}
			if (action === 'toggle-vis') {
				self.toggleVisible(key, newsId);
			}
			if (action === 'edit-type') {
				self.editRelationType(key, newsId);
			}
			if (action === 'edit-comment') {
				self.editRelationComment(key, newsId);
			}
			if (action === 'rename') {
				self.renameCollection(key);
			}
			if (action === 'set-collection-type') {
				self.setCollectionType(key);
			}
			if (action === 'automate') {
				self.automateMembership(key);
			}
		});
	};

	NewsFormDraft.prototype.syncInput = function () {
		if (!this.$input.length) {
			return;
		}
		this.$input.val(JSON.stringify(this.snapshot));
	};

	NewsFormDraft.prototype.collectionTitle = function (membership) {
		if (membership.temp_key) {
			var nc = this.snapshot.new_collections.find(function (c) {
				return c.temp_key === membership.temp_key;
			});
			return nc ? nc.title : membership.temp_key;
		}
		var id = membership.collection_id;
		var col = this.collections.find(function (c) {
			return c.id === id;
		});
		return col ? col.title : ('#' + id);
	};

	NewsFormDraft.prototype.resolveTypeId = function (membership) {
		if (membership && membership.type_id !== undefined && membership.type_id !== null && membership.type_id !== '') {
			return parseInt(membership.type_id, 10) || 0;
		}
		if (membership && membership.temp_key) {
			var nc = this.snapshot.new_collections.find(function (c) {
				return c.temp_key === membership.temp_key;
			});
			return nc ? (parseInt(nc.type_id, 10) || 0) : 0;
		}
		var colId = membership ? membership.collection_id : 0;
		var col = this.treesById[colId] || this.treesById[String(colId)];
		return col ? (parseInt(col.type_id, 10) || 0) : 0;
	};

	NewsFormDraft.prototype.collectionTypeLabel = function (typeId) {
		var id = parseInt(typeId, 10) || 0;
		if (id <= 0) {
			return t('Без категории');
		}
		var found = (this.collectionTypes || []).find(function (row) {
			return parseInt(row.id, 10) === id;
		});
		return found && found.name ? found.name : ('#' + id);
	};

	NewsFormDraft.prototype.pickCollectionType = function (currentId) {
		var selected = parseInt(currentId || 0, 10) || 0;
		var options = [{ value: '0', label: t('— без категории —') }].concat(
			(this.collectionTypes || []).map(function (type) {
				return { value: String(type.id), label: type.name };
			})
		);
		return selectDialog(t('Категория сборки'), t('Категория'), options, String(selected)).then(function (val) {
			if (val === null) {
				return null;
			}
			return parseInt(val, 10) || 0;
		});
	};

	NewsFormDraft.prototype.setCollectionType = function (key) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		this.pickCollectionType(this.resolveTypeId(m)).then(function (typeId) {
			if (typeId === null) {
				return;
			}
			m.type_id = typeId;
			if (m.temp_key) {
				var nc = self.snapshot.new_collections.find(function (c) {
					return c.temp_key === m.temp_key;
				});
				if (nc) {
					nc.type_id = typeId;
				}
			} else if (m.collection_id) {
				var col = self.treesById[m.collection_id] || self.treesById[String(m.collection_id)];
				if (col) {
					col.type_id = typeId;
					col.type_name = typeId > 0 ? self.collectionTypeLabel(typeId) : null;
				}
			}
			self.commit();
		});
	};

	/**
	 * Automate только для одной сохранённой сборки (FR-001g).
	 * Без ссылок на редактор правил (FR-001b/f).
	 */
	NewsFormDraft.prototype.automateMembership = function (key) {
		var self = this;
		if (!this.automationEnabled) {
			return;
		}
		var m = this.findMembership(key);
		if (!m || m.temp_key || !(m.collection_id > 0)) {
			notify(t('Automate доступен после сохранения сборки.'), 'warning');
			return;
		}
		var collectionId = parseInt(m.collection_id, 10) || 0;
		if (!collectionId) {
			return;
		}

		postAutoAjax(this.ajaxMod, 'list_rules_for_collection', { collection_id: collectionId })
			.then(function (data) {
				var rules = Array.isArray(data.rules) ? data.rules : [];
				if (!rules.length) {
					notify(t('Нет активных правил для категории этой сборки.'), 'warning');
					return null;
				}

				var ruleOptions = rules.map(function (r) {
					return {
						value: String(r.id),
						label: (r.rule_name || r.pattern_type || ('#' + r.id)) + ' (' + r.pattern_type + ')',
					};
				});
				var modeOptions = [
					{ value: 'preserve', label: t('Preserve (сохранить ручные типы)') },
					{ value: 'full_reset', label: t('Full reset (перезаписать всё)') },
				];

				return selectDialog(t('Automate'), t('Правило'), ruleOptions, String(rules[0].id)).then(function (ruleVal) {
					if (ruleVal === null) {
						return null;
					}
					return selectDialog(t('Automate'), t('Режим'), modeOptions, 'preserve').then(function (modeVal) {
						if (modeVal === null) {
							return null;
						}
						return {
							ruleId: parseInt(ruleVal, 10) || 0,
							mode: modeVal || 'preserve',
						};
					});
				});
			})
			.then(function (choice) {
				if (!choice || !choice.ruleId) {
					return null;
				}
				return postAutoAjax(self.ajaxMod, 'run_automation', {
					collection_id: collectionId,
					rule_id: choice.ruleId,
					mode: choice.mode,
				});
			})
			.then(function (result) {
				if (!result) {
					return;
				}
				notify(t('Automate выполнен для этой сборки.'), 'info');
			})
			.catch(function (err) {
				notify(err && err.message ? err.message : t('Ошибка Automate'), 'error');
			});
	};

	NewsFormDraft.prototype.rowKey = function (membership) {
		if (membership.temp_key) {
			return 't:' + membership.temp_key;
		}
		return 'c:' + membership.collection_id;
	};

	NewsFormDraft.prototype.findMembership = function (key) {
		var self = this;
		return this.snapshot.memberships.find(function (m) {
			return self.rowKey(m) === key;
		}) || null;
	};

	NewsFormDraft.prototype.currentNewsTitle = function () {
		var fromForm = '';
		var $title = $('#title');
		if ($title.length) {
			fromForm = String($title.val() || '').trim();
		}
		if (fromForm) {
			return fromForm;
		}
		if (this.newsId > 0) {
			return t('Новость') + ' #' + this.newsId;
		}
		return t('Эта новость');
	};

	NewsFormDraft.prototype.render = function () {
		if (!this.$list.length) {
			return;
		}
		var self = this;
		this.destroySortables();
		var list = this.$list.get(0);
		list.textContent = '';

		if (!this.snapshot.memberships.length) {
			var emptyTpl = document.getElementById('dc-conn-nf-tpl-empty');
			if (emptyTpl && emptyTpl.content) {
				list.appendChild(emptyTpl.content.cloneNode(true));
			} else {
				var empty = document.createElement('div');
				empty.className = 'dc-conn-nf-empty';
				empty.textContent = t('Пока нет участий в сборках');
				list.appendChild(empty);
			}
			return;
		}

		var colTpl = document.getElementById('dc-conn-nf-tpl-collection');
		var itemTpl = document.getElementById('dc-conn-nf-tpl-item');
		if (!colTpl || !colTpl.content || !itemTpl || !itemTpl.content) {
			return;
		}

		var newsTitle = self.currentNewsTitle();
		this.snapshot.memberships.forEach(function (m) {
			var frag = colTpl.content.cloneNode(true);
			var root = frag.querySelector('[data-row-key]');
			var titleEl = frag.querySelector('[data-dc-conn-nf-title]');
			var typeChip = frag.querySelector('[data-dc-conn-nf-type-chip]');
			var itemsBox = frag.querySelector('[data-dc-conn-nf-items]');
			var renameBtn = frag.querySelector('[data-dc-conn-nf-rename]');
			var key = self.rowKey(m);
			var items = self.itemsForMembership(m, newsTitle);
			var isDraft = !!m.temp_key;
			var typeId = self.resolveTypeId(m);

			if (root) root.setAttribute('data-row-key', key);
			if (titleEl) titleEl.textContent = self.collectionTitle(m);
			if (typeChip) {
				typeChip.textContent = self.collectionTypeLabel(typeId);
				typeChip.setAttribute('title', t('Категория сборки'));
			}
			if (renameBtn) {
				renameBtn.hidden = !isDraft;
			}

			var autoBtn = frag.querySelector('[data-dc-conn-nf-automate]');
			var autoHint = frag.querySelector('[data-dc-conn-nf-automate-hint]');
			if (self.automationEnabled) {
				if (autoBtn) {
					autoBtn.hidden = isDraft || !(m.collection_id > 0);
				}
				if (autoHint) {
					autoHint.hidden = !isDraft;
				}
			} else {
				if (autoBtn) autoBtn.remove();
				if (autoHint) autoHint.remove();
			}

			items.forEach(function (entry) {
				var itemFrag = itemTpl.content.cloneNode(true);
				var itemRoot = itemFrag.querySelector('.dc-conn-nf-item');
				var newsEl = itemFrag.querySelector('[data-dc-conn-nf-news-title]');
				var metaRow = itemFrag.querySelector('[data-dc-conn-nf-meta-row]');
				var metaEl = itemFrag.querySelector('[data-dc-conn-nf-meta]');
				var commentEl = itemFrag.querySelector('[data-dc-conn-nf-comment]');
				var openEl = itemFrag.querySelector('[data-dc-conn-nf-open]');
				var eyeEl = itemFrag.querySelector('[data-dc-conn-nf-eye]');
				var toggleBtn = itemFrag.querySelector('[data-dc-conn-nf-vis-btn]');
				var meta = entry.is_current ? '' : String(entry.relation_type || '').trim();
				var comment = entry.is_current ? '' : String(entry.comment || '').trim();
				var nid = parseInt(entry.news_id, 10) || 0;
				var visSrc = self.assets + '/' + (entry.is_visible ? 'visibility-on.svg' : 'visibility-off.svg');

				if (itemRoot) {
					itemRoot.classList.toggle('is-hidden', !entry.is_visible);
					itemRoot.classList.toggle('is-focus', !!entry.is_current);
					if (entry.is_current) {
						itemRoot.setAttribute('data-dc-conn-nf-current', '1');
					}
					itemRoot.setAttribute('data-news-id', String(nid));
					itemRoot.setAttribute('data-comment', comment);
				}
				if (newsEl) newsEl.textContent = entry.news_title || '';
				/* Текущая новость не имеет типа связи к себе — скрываем meta-row. */
				if (entry.is_current) {
					if (metaRow) {
						metaRow.remove();
					}
					if (commentEl) {
						commentEl.remove();
					}
				} else if (metaEl) {
					metaEl.textContent = meta || t('Тип связи не задан');
					metaEl.classList.toggle('is-empty', !meta);
					metaEl.setAttribute('data-action', 'edit-type');
					metaEl.setAttribute('title', t('Тип связи'));
				}
				if (commentEl && !entry.is_current) {
					commentEl.textContent = comment;
					commentEl.hidden = !comment;
				}
				if (openEl) {
					if (nid > 0 && self.editNewsUrl) {
						openEl.hidden = false;
						openEl.setAttribute('href', self.editNewsUrl.split('__ID__').join(String(nid)));
					} else {
						openEl.remove();
					}
				}
				if (eyeEl) eyeEl.setAttribute('src', visSrc);
				if (toggleBtn) {
					toggleBtn.setAttribute('title', entry.is_visible ? t('Скрыть') : t('Показать'));
				}
				if (itemsBox) itemsBox.appendChild(itemFrag);
			});

			list.appendChild(frag);
		});

		this.bindSortables();
	};

	NewsFormDraft.prototype.destroySortables = function () {
		(this.sortables || []).forEach(function (inst) {
			try {
				if (inst && typeof inst.destroy === 'function') {
					inst.destroy();
				}
			} catch (e) { /* ignore */ }
		});
		this.sortables = [];
	};

	/**
	 * DnD как у DLE #rubriclist / .dd-handles: SortableJS (public/js/sortable.js).
	 * Сборки — .dc-conn-nf-col-drag; новости — .dc-conn-nf-drag (разные handle, иначе nested DnD конфликтует).
	 */
	NewsFormDraft.prototype.bindSortables = function () {
		var self = this;
		this.destroySortables();
		if (typeof window.Sortable === 'undefined') {
			this._sortableWait = (this._sortableWait || 0) + 1;
			if (this._sortableWait <= 40) {
				setTimeout(function () {
					self.bindSortables();
				}, 50);
			}
			return;
		}
		this._sortableWait = 0;

		var listEl = this.$list.get(0);
		if (listEl) {
			self.sortables.push(new Sortable(listEl, {
				animation: 150,
				handle: '.dc-conn-nf-col-drag',
				draggable: '.dc-conn-nf-collection',
				ghostClass: 'dc-conn-nf-ghost',
				chosenClass: 'sortable-chosen',
				forceFallback: true,
				fallbackOnBody: true,
				fallbackTolerance: 3,
				onChoose: function (evt) {
					if (evt.item) {
						evt.item.classList.add('is-sorting');
					}
				},
				onEnd: function (evt) {
					if (evt.item) {
						evt.item.classList.remove('is-sorting');
					}
					self.applyMembershipOrder();
					self.syncInput();
				},
			}));
		}

		this.$list.find('[data-dc-conn-nf-items]').each(function () {
			var treeEl = this;
			var key = $(treeEl).closest('[data-row-key]').attr('data-row-key') || '';
			var inst = new Sortable(treeEl, {
				animation: 150,
				handle: '.dc-conn-nf-drag',
				draggable: '.dc-conn-nf-item',
				ghostClass: 'dc-conn-nf-ghost',
				chosenClass: 'sortable-chosen',
				forceFallback: true,
				fallbackOnBody: true,
				fallbackTolerance: 3,
				onChoose: function (evt) {
					if (evt.item) {
						evt.item.classList.add('is-sorting');
					}
				},
				onEnd: function (evt) {
					if (evt.item) {
						evt.item.classList.remove('is-sorting');
					}
					self.applyTreeOrder(key, treeEl);
					self.syncInput();
				},
			});
			self.sortables.push(inst);
		});
	};

	/**
	 * Порядок DOM сборок → snapshot.memberships.
	 */
	NewsFormDraft.prototype.applyMembershipOrder = function () {
		var self = this;
		var byKey = {};
		this.snapshot.memberships.forEach(function (m) {
			byKey[self.rowKey(m)] = m;
		});
		var next = [];
		this.$list.children('.dc-conn-nf-collection').each(function () {
			var key = this.getAttribute('data-row-key') || '';
			if (byKey[key]) {
				next.push(byKey[key]);
				delete byKey[key];
			}
		});
		Object.keys(byKey).forEach(function (key) {
			next.push(byKey[key]);
		});
		this.snapshot.memberships = next;
	};

	/**
	 * Порядок DOM → membership.items (+ treesById), без полного re-render.
	 */
	NewsFormDraft.prototype.applyTreeOrder = function (key, treeEl) {
		var m = this.findMembership(key);
		if (!m || !treeEl) {
			return;
		}
		var items = this.ensureMembershipItems(m);
		var byId = {};
		items.forEach(function (row) {
			byId[String(parseInt(row.news_id, 10) || 0)] = row;
		});
		var next = [];
		Array.prototype.forEach.call(treeEl.children, function (el) {
			if (!el.classList || !el.classList.contains('dc-conn-nf-item')) {
				return;
			}
			var nid = String(parseInt(el.getAttribute('data-news-id') || '0', 10) || 0);
			if (byId[nid]) {
				next.push(byId[nid]);
				delete byId[nid];
			}
		});
		Object.keys(byId).forEach(function (nid) {
			next.push(byId[nid]);
		});
		m.items = next;

		var colId = m.collection_id;
		if (!colId) {
			return;
		}
		var col = this.treesById[colId] || this.treesById[String(colId)];
		if (!col || !Array.isArray(col.items)) {
			return;
		}
		var treeById = {};
		col.items.forEach(function (row) {
			treeById[String(parseInt(row.news_id, 10) || 0)] = row;
		});
		var treeNext = [];
		next.forEach(function (row) {
			var nid = String(parseInt(row.news_id, 10) || 0);
			if (treeById[nid]) {
				treeNext.push(treeById[nid]);
				delete treeById[nid];
			} else {
				treeNext.push({
					news_id: parseInt(row.news_id, 10) || 0,
					news_title: row.news_title || '',
					relation_type: row.relation_type || '',
					is_visible: row.is_visible !== undefined ? !!row.is_visible : true,
				});
			}
		});
		Object.keys(treeById).forEach(function (nid) {
			treeNext.push(treeById[nid]);
		});
		col.items = treeNext;
	};

	/**
	 * В снимке — полный список элементов сборки; у каждого свой relation_type / is_visible.
	 */
	NewsFormDraft.prototype.ensureMembershipItems = function (membership) {
		var self = this;
		var clearCurrentType = function (items) {
			membership.relation_type = '';
			items.forEach(function (row) {
				var nid = parseInt(row.news_id, 10) || 0;
				if (self.isCurrentNewsId(nid)) {
					row.relation_type = '';
				}
			});
			return items;
		};

		if (Array.isArray(membership.items) && membership.items.length) {
			return clearCurrentType(membership.items);
		}

		var items = [];

		if (membership.temp_key) {
			items.push({
				news_id: this.newsId > 0 ? this.newsId : 0,
				relation_type: '',
				comment: '',
				is_visible: membership.is_visible !== undefined ? !!membership.is_visible : true,
			});
			membership.items = items;
			return clearCurrentType(items);
		}

		var colId = membership.collection_id;
		var col = this.treesById[colId] || this.treesById[String(colId)] || null;
		var sourceItems = (col && Array.isArray(col.items)) ? col.items : [];
		var found = false;

		sourceItems.forEach(function (item) {
			var nid = parseInt(item.news_id, 10) || 0;
			var isCurrent = self.newsId > 0 && nid === self.newsId;
			if (isCurrent) {
				found = true;
			}
			items.push({
				news_id: nid,
				relation_type: isCurrent ? '' : (item.relation_type || ''),
				comment: isCurrent ? '' : (item.comment || ''),
				is_visible: isCurrent
					? (membership.is_visible !== undefined ? !!membership.is_visible : !!item.is_visible)
					: (item.is_visible !== undefined ? !!item.is_visible : true),
			});
		});

		if (!found) {
			items.push({
				news_id: this.newsId > 0 ? this.newsId : 0,
				relation_type: '',
				comment: '',
				is_visible: membership.is_visible !== undefined ? !!membership.is_visible : true,
			});
		}

		membership.items = items;
		return clearCurrentType(items);
	};

	NewsFormDraft.prototype.findMembershipItem = function (membership, newsId) {
		var items = this.ensureMembershipItems(membership);
		var id = parseInt(newsId, 10);
		if (isNaN(id)) {
			id = this.newsId;
		}
		var found = items.find(function (row) {
			return parseInt(row.news_id, 10) === id;
		});
		if (found) {
			return found;
		}
		/* Новая новость: в снимке может быть news_id=0. */
		if (this.newsId <= 0 || id === this.newsId) {
			return items.find(function (row) {
				return parseInt(row.news_id, 10) === 0;
			}) || null;
		}
		return null;
	};

	/**
	 * Элементы сборки: каждый со своим типом связи (не только текущая новость).
	 */
	NewsFormDraft.prototype.itemsForMembership = function (membership, newsTitle) {
		var self = this;
		var draftItems = this.ensureMembershipItems(membership);
		var colId = membership.collection_id;
		var col = this.treesById[colId] || this.treesById[String(colId)] || null;
		var titleById = {};

		if (col && Array.isArray(col.items)) {
			col.items.forEach(function (item) {
				titleById[String(item.news_id)] = item.news_title || '';
			});
		}

		return draftItems.map(function (row) {
			var nid = parseInt(row.news_id, 10) || 0;
			var isCurrent = (self.newsId > 0 && nid === self.newsId) || (self.newsId <= 0 && nid === 0);
			if (isCurrent) {
				row.relation_type = '';
			}
			return {
				news_id: nid,
				news_title: isCurrent
					? newsTitle
					: (row.news_title || titleById[String(nid)] || ('#' + nid)),
				relation_type: isCurrent ? '' : (row.relation_type || ''),
				comment: isCurrent ? '' : (row.comment || ''),
				is_visible: row.is_visible !== undefined ? !!row.is_visible : true,
				is_current: isCurrent,
			};
		});
	};

	NewsFormDraft.prototype.commit = function () {
		this.syncInput();
		this.render();
	};

	NewsFormDraft.prototype.addNewCollection = function () {
		var self = this;
		promptText(t('Название новой сборки'), '', t('Новая сборка'), false).then(function (title) {
			if (title === null) {
				return;
			}
			title = String(title).trim();
			if (!title) {
				notify(t('Название не может быть пустым'), 'error');
				return;
			}
			var tempKey = 'nc-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
			var newsId = self.newsId > 0 ? self.newsId : 0;
			self.snapshot.new_collections.push({
				temp_key: tempKey,
				title: title,
				description: null,
				type_id: 0,
			});
			self.snapshot.memberships.push({
				collection_id: null,
				temp_key: tempKey,
				type_id: 0,
				relation_type: '',
				is_visible: true,
				items: [{
					news_id: newsId,
					relation_type: '',
					comment: '',
					is_visible: true,
				}],
			});
			self.commit();
		});
	};

	/**
	 * Добавить любую новость в сборку (черновик; запись в БД после Save формы).
	 */
	NewsFormDraft.prototype.addItemToCollection = function (key) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		pickNewsDialog(this.ajaxMod).then(function (news) {
			if (!news || !news.id) {
				return;
			}
			var items = self.ensureMembershipItems(m);
			var exists = items.some(function (row) {
				return parseInt(row.news_id, 10) === news.id;
			});
			if (exists) {
				notify(t('Новость уже есть в этой сборке'), 'warning');
				return;
			}
			if (self.newsId > 0 && news.id === self.newsId) {
				notify(t('Новость уже есть в этой сборке'), 'warning');
				return;
			}
			items.push({
				news_id: news.id,
				relation_type: '',
				is_visible: true,
				news_title: news.title || ('#' + news.id),
			});
			m.items = items;

			var colId = m.collection_id;
			if (colId) {
				var col = self.treesById[colId] || self.treesById[String(colId)];
				if (!col || typeof col !== 'object') {
					col = { id: colId, title: self.collectionTitle(m), items: [] };
					self.treesById[colId] = col;
				}
				if (!Array.isArray(col.items)) {
					col.items = [];
				}
				var inTree = col.items.some(function (row) {
					return parseInt(row.news_id, 10) === news.id;
				});
				if (!inTree) {
					col.items.push({
						news_id: news.id,
						news_title: news.title || ('#' + news.id),
						relation_type: '',
						is_visible: true,
					});
				}
			}

			self.commit();
			notify(t('Новость добавлена в черновик сборки'), 'info');
		});
	};

	/**
	 * Убрать элемент из сборки в черновике. Текущая новость → снять membership.
	 */
	NewsFormDraft.prototype.removeItemFromCollection = function (key, newsId) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var nid = parseInt(newsId, 10) || 0;
		if (this.isCurrentNewsId(nid)) {
			this.removeMembership(key);
			return;
		}
		confirmAction(t('Убрать эту новость из сборки в черновике?'), t('Подтверждение')).then(function (ok) {
			if (!ok) {
				return;
			}
			var items = self.ensureMembershipItems(m).filter(function (row) {
				return parseInt(row.news_id, 10) !== nid;
			});
			m.items = items;
			var colId = m.collection_id;
			if (colId) {
				var col = self.treesById[colId] || self.treesById[String(colId)];
				if (col && Array.isArray(col.items)) {
					col.items = col.items.filter(function (row) {
						return parseInt(row.news_id, 10) !== nid;
					});
				}
			}
			self.commit();
		});
	};

	NewsFormDraft.prototype.replaceItemNews = function (key, newsId) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var entry = this.findMembershipItem(m, newsId);
		if (!entry) {
			return;
		}
		var oldId = parseInt(entry.news_id, 10) || 0;
		pickNewsDialog(this.ajaxMod).then(function (news) {
			if (!news || !news.id) {
				return;
			}
			if (news.id === oldId) {
				return;
			}
			var items = self.ensureMembershipItems(m);
			var clash = items.some(function (row) {
				return parseInt(row.news_id, 10) === news.id && row !== entry;
			});
			if (clash) {
				notify(t('Новость уже есть в этой сборке'), 'warning');
				return;
			}
			entry.news_id = news.id;
			entry.news_title = news.title || ('#' + news.id);
			entry.is_current = self.isCurrentNewsId(news.id);
			if (self.isCurrentNewsId(oldId) || self.isCurrentNewsId(news.id)) {
				m.relation_type = entry.relation_type || '';
				m.is_visible = entry.is_visible !== undefined ? !!entry.is_visible : true;
			}
			var colId = m.collection_id;
			if (colId) {
				var col = self.treesById[colId] || self.treesById[String(colId)];
				if (col && Array.isArray(col.items)) {
					var treeRow = col.items.find(function (row) {
						return parseInt(row.news_id, 10) === oldId;
					});
					if (treeRow) {
						treeRow.news_id = news.id;
						treeRow.news_title = entry.news_title;
					}
				}
			}
			self.commit();
			notify(t('Новость заменена в черновике'), 'info');
		});
	};

	NewsFormDraft.prototype.copyItemToCollection = function (key, newsId) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var entry = this.findMembershipItem(m, newsId);
		if (!entry) {
			return;
		}
		var options = this.collections.map(function (c) {
			return { value: 'c:' + c.id, label: c.title || ('#' + c.id) };
		});
		this.snapshot.new_collections.forEach(function (nc) {
			options.push({ value: 't:' + nc.temp_key, label: nc.title || nc.temp_key });
		});
		options = options.filter(function (opt) {
			return opt.value !== key;
		});
		if (!options.length) {
			notify(t('Нет другой сборки для копирования'), 'warning');
			return;
		}
		selectDialog(t('Копировать в сборку'), t('Сборка'), options, options[0].value).then(function (targetKey) {
			if (targetKey === null || targetKey === '') {
				return;
			}
			var target = self.findMembership(targetKey);
			if (!target) {
				if (targetKey.indexOf('c:') === 0) {
					var colId = parseInt(targetKey.slice(2), 10);
					target = {
						collection_id: colId,
						temp_key: null,
						type_id: self.resolveTypeId({ collection_id: colId }),
						relation_type: '',
						is_visible: true,
						items: [],
					};
					self.snapshot.memberships.push(target);
					self.ensureMembershipItems(target);
				} else {
					notify(t('Сборка не найдена'), 'error');
					return;
				}
			}
			var items = self.ensureMembershipItems(target);
			var nid = parseInt(entry.news_id, 10) || 0;
			var exists = items.some(function (row) {
				return parseInt(row.news_id, 10) === nid;
			});
			if (exists) {
				notify(t('Новость уже есть в этой сборке'), 'warning');
				return;
			}
			items.push({
				news_id: nid,
				relation_type: entry.relation_type || '',
				is_visible: entry.is_visible !== undefined ? !!entry.is_visible : true,
				news_title: entry.news_title || ('#' + nid),
				is_current: self.isCurrentNewsId(nid),
			});
			target.items = items;
			self.commit();
			notify(t('Новость скопирована в черновик'), 'info');
		});
	};

	NewsFormDraft.prototype.copyCollectionDraft = function (key) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var title = this.collectionTitle(m) + ' (' + t('копия') + ')';
		promptText(t('Название сборки'), title, t('Копировать сборку'), false).then(function (nextTitle) {
			if (nextTitle === null) {
				return;
			}
			nextTitle = String(nextTitle).trim();
			if (!nextTitle) {
				notify(t('Название не может быть пустым'), 'error');
				return;
			}
			var tempKey = 'new_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
			var srcItems = self.ensureMembershipItems(m);
			var cloned = srcItems.map(function (row) {
				return {
					news_id: parseInt(row.news_id, 10) || 0,
					relation_type: row.relation_type || '',
					is_visible: row.is_visible !== undefined ? !!row.is_visible : true,
					news_title: row.news_title || '',
					is_current: self.isCurrentNewsId(row.news_id),
				};
			});
			var typeId = self.resolveTypeId(m);
			self.snapshot.new_collections.push({
				temp_key: tempKey,
				title: nextTitle,
				type_id: typeId,
			});
			self.snapshot.memberships.push({
				collection_id: null,
				temp_key: tempKey,
				type_id: typeId,
				relation_type: m.relation_type || '',
				is_visible: m.is_visible !== undefined ? !!m.is_visible : true,
				items: cloned,
			});
			self.commit();
			notify(t('Сборка скопирована в черновик'), 'info');
		});
	};

	NewsFormDraft.prototype.renameCollection = function (key) {
		var self = this;
		var m = this.findMembership(key);
		if (!m || !m.temp_key) {
			notify(t('Переименовать можно только новую сборку из черновика'), 'warning');
			return;
		}
		var nc = this.snapshot.new_collections.find(function (c) {
			return c.temp_key === m.temp_key;
		});
		if (!nc) {
			return;
		}
		promptText(t('Название сборки'), nc.title || '', t('Переименовать'), false).then(function (title) {
			if (title === null) {
				return;
			}
			title = String(title).trim();
			if (!title) {
				notify(t('Название не может быть пустым'), 'error');
				return;
			}
			nc.title = title;
			self.commit();
		});
	};

	NewsFormDraft.prototype.removeMembership = function (key) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		confirmAction(t('Убрать новость из этой сборки в черновике?'), t('Подтверждение')).then(function (ok) {
			if (!ok) {
				return;
			}
			self.snapshot.memberships = self.snapshot.memberships.filter(function (row) {
				return row !== m;
			});
			if (m.temp_key) {
				var still = self.snapshot.memberships.some(function (row) {
					return row.temp_key === m.temp_key;
				});
				if (!still) {
					self.snapshot.new_collections = self.snapshot.new_collections.filter(function (c) {
						return c.temp_key !== m.temp_key;
					});
				}
			}
			self.commit();
		});
	};

	NewsFormDraft.prototype.toggleVisible = function (key, newsId) {
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var entry = this.findMembershipItem(m, newsId);
		if (!entry) {
			return;
		}
		entry.is_visible = !entry.is_visible;
		if (this.isCurrentNewsId(entry.news_id)) {
			m.is_visible = entry.is_visible;
		}
		this.commit();
	};

	NewsFormDraft.prototype.isCurrentNewsId = function (newsId) {
		var nid = parseInt(newsId, 10) || 0;
		if (this.newsId > 0) {
			return nid === this.newsId;
		}
		return nid === 0;
	};

	NewsFormDraft.prototype.editRelationType = function (key, newsId) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var entry = this.findMembershipItem(m, newsId);
		if (!entry) {
			return;
		}
		/* Новость не может иметь тип связи к самой себе. */
		if (this.isCurrentNewsId(entry.news_id)) {
			return;
		}
		var options = [{ value: '', label: t('— без типа (подавить auto) —') }].concat(
			this.types.map(function (tp) {
				return { value: tp.name, label: tp.name };
			})
		);
		var apply = function (val) {
			entry.relation_type = String(val).trim();
			self.commit();
		};
		if (!this.types.length) {
			notify(t('Сначала создайте типы связей в модуле Connections'), 'warning');
			return;
		}
		selectDialog(t('Тип связи'), t('Тип'), options, entry.relation_type || '').then(function (val) {
			if (val === null) {
				return;
			}
			apply(val);
		});
	};

	NewsFormDraft.prototype.editRelationComment = function (key, newsId) {
		var self = this;
		var m = this.findMembership(key);
		if (!m) {
			return;
		}
		var entry = this.findMembershipItem(m, newsId);
		if (!entry || this.isCurrentNewsId(entry.news_id)) {
			return;
		}
		promptText(t('Комментарий к связи'), entry.comment || '', t('Комментарий'), true).then(function (next) {
			if (next === null) {
				return;
			}
			entry.comment = String(next).trim();
			self.commit();
		});
	};

	function boot() {
		$('[data-dc-conn-embed="1"]').each(function () {
			var el = this;
			if (el.getAttribute('data-dc-conn-booted')) {
				return;
			}
			el.setAttribute('data-dc-conn-booted', '1');
			new NewsFormDraft(el);
		});
	}

	$(boot);

	window.DevCraftConnectionsAdmin = { reload: boot };
})(window, document, window.jQuery);
