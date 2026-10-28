/**
 * Helpers для Automate-диалогов.
 * Сателлит extends Connections → AJAX через host mod (dle_connections).
 */
(function (window) {
	'use strict';

	if (!window.DevCraft) {
		return;
	}

	const Ajax = window.DevCraft.Ajax;

	function hostMod() {
		return (document.body && document.body.dataset.mod) || 'dle_connections';
	}

	function postAuto(method, data) {
		const params = { controller: 'admin', method: method, mod: hostMod() };
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

	window.DevCraftConnectionsAutomation = {
		post: postAuto,
		listRulesForCollection: function (collectionId) {
			return postAuto('list_rules_for_collection', { collection_id: collectionId });
		},
		runAutomation: function (collectionId, ruleId, mode) {
			return postAuto('run_automation', {
				collection_id: collectionId,
				rule_id: ruleId || 0,
				mode: mode || 'preserve',
			});
		},
		autoRules: function (data) {
			return postAuto('auto_rules', data || {});
		},
	};
})(window);
