(function () {
	'use strict';

	const config = window.purrfectMatchAdmin || {};

	function request(endpoint, method) {
		return fetch(config.restUrl + endpoint, {
			method: method || 'POST',
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json',
			},
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					throw new Error(data.message || res.statusText);
				}
				return data;
			});
		});
	}

	function setResult(el, message, success) {
		el.textContent = message;
		el.className = 'pm-admin__result pm-admin__result--' + (success ? 'success' : 'error');
	}

	document.addEventListener('DOMContentLoaded', function () {
		var testBtn = document.getElementById('pm-test-api');
		var testResult = document.getElementById('pm-test-result');

		if (testBtn) {
			testBtn.addEventListener('click', function () {
				testBtn.disabled = true;
				testResult.textContent = config.i18n.testing;
				testResult.className = 'pm-admin__result';

				request('test-connection')
					.then(function () {
						setResult(testResult, config.i18n.success, true);
					})
					.catch(function (err) {
						setResult(testResult, config.i18n.error + err.message, false);
					})
					.finally(function () {
						testBtn.disabled = false;
					});
			});
		}

		var flushBtn = document.getElementById('pm-flush-cache');
		var flushResult = document.getElementById('pm-flush-result');

		if (flushBtn) {
			flushBtn.addEventListener('click', function () {
				flushBtn.disabled = true;
				flushResult.textContent = config.i18n.flushing;
				flushResult.className = 'pm-admin__result';

				request('flush-cache')
					.then(function () {
						setResult(flushResult, config.i18n.flushed, true);
					})
					.catch(function () {
						setResult(flushResult, config.i18n.flushError, false);
					})
					.finally(function () {
						flushBtn.disabled = false;
					});
			});
		}
	});
})();
