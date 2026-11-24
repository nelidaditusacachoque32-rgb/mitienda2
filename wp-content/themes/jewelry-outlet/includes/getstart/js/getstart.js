(function () {
	'use strict';
	var cfg = window.jewelryOutletGS;
	if (!cfg) { return; }

	var btn = document.getElementById('wpet-quick-start');
	var text = document.getElementById('wpet-step-text');
	var fill = document.getElementById('wpet-bar-fill');
	var steps = document.querySelectorAll('#wpet-steps li');
	var visit = document.getElementById('wpet-visit');
	var edit = document.getElementById('wpet-edit');
	var busy = false;

	function setStep(n, pct) {
		steps.forEach(function (li) {
			var s = parseInt(li.getAttribute('data-step'), 10);
			li.classList.toggle('is-active', s === n);
			li.classList.toggle('is-done', s < n);
		});
		fill.style.width = pct + '%';
	}

	function pending() {
		return cfg.plugins.filter(function (p) { return !p.active; });
	}

	function post(data, retried) {
		var body = new URLSearchParams(data);
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) {
			// A freshly activated plugin may redirect the next admin request to its
			// welcome/setup page; our action never ran, so send it once more.
			if (r.redirected && !retried) {
				return post(data, true);
			}
			return r.text().then(parseReply);
		});
	}

	function parseReply(t) {
		try { return JSON.parse(t); } catch (e) {
			// PHP notices (WP_DEBUG on) can be printed around the JSON reply.
			var start = t.indexOf('{"');
			var end = t.lastIndexOf('}');
			if (start !== -1 && end > start) {
				try { return JSON.parse(t.slice(start, end + 1)); } catch (e2) {}
			}
			throw new Error(cfg.i18n.error);
		}
	}

	function showDone() {
		setStep(3, 100);
		text.textContent = cfg.i18n.stepDone;
		btn.hidden = true;
		visit.hidden = false;
		visit.href = cfg.permalink;
		if (cfg.editUrl) {
			edit.hidden = false;
			edit.href = cfg.editUrl;
		} else {
			edit.hidden = true;
		}
	}

	function render() {
		var left = pending().length;
		// Plugins left over after an import (e.g. one was deactivated) still need installing,
		// but the existing demo page can be edited meanwhile.
		if (cfg.imported && cfg.editUrl) { edit.hidden = false; edit.href = cfg.editUrl; }
		if (cfg.imported && !left) { showDone(); return; }
		if (!left) {
			setStep(2, 50);
			text.textContent = cfg.i18n.stepImport;
			btn.textContent = cfg.i18n.import;
			return;
		}
		setStep(1, Math.round(((cfg.plugins.length - left) / cfg.plugins.length) * 33));
		text.textContent = cfg.i18n.stepPlugins.replace('%d', left);
		btn.textContent = cfg.i18n.install;
	}

	// Dependencies the server activated along the way, e.g. WooCommerce for a WooCommerce add-on.
	function markActive(slugs) {
		if (!slugs) { return; }
		cfg.plugins.forEach(function (p) {
			if (slugs.indexOf(p.slug) !== -1) { p.active = true; p.installed = true; }
		});
	}

	function installAll() {
		var list = pending();
		var total = cfg.plugins.length;
		return list.reduce(function (chain, plugin) {
			return chain.then(function () {
				// The list may have been activated already as another plugin's dependency.
				if (plugin.active) { return; }
				text.textContent = cfg.i18n.installing + ' ' + plugin.name + '…';
				return post({ action: 'jewelry_outlet_gs_install', nonce: cfg.nonce, slug: plugin.slug }).then(function (res) {
					markActive(res && res.data && res.data.activated);
					if (!res || !res.success) {
						throw new Error((res && res.data && res.data.message) || cfg.i18n.error);
					}
					plugin.active = true;
					plugin.installed = true;
					fill.style.width = Math.round(((total - pending().length) / total) * 33) + '%';
				});
			});
		}, Promise.resolve());
	}

	function importDemo() {
		setStep(2, 50);
		text.textContent = cfg.i18n.importing;
		return post({ action: 'wpelemento_importer_setup_elementor', wpnonce: cfg.importNonce }).then(function (res) {
			if (!res || (!res.permalink && !res.edit_post_link)) {
				throw new Error((res && res.data && res.data.message) || cfg.i18n.error);
			}
			cfg.imported = true;
			cfg.permalink = res.permalink || cfg.permalink;
			cfg.editUrl = res.edit_post_link || '';
			return post({ action: 'jewelry_outlet_gs_done', nonce: cfg.nonce, permalink: cfg.permalink, edit: cfg.editUrl });
		});
	}

	btn.addEventListener('click', function () {
		if (busy) { return; }
		busy = true;
		btn.disabled = true;
		btn.classList.add('is-loading');

		var run = Promise.resolve();
		if (pending().length) { run = run.then(installAll); }
		run.then(importDemo).then(showDone).catch(function (err) {
			text.textContent = err.message || cfg.i18n.error;
			btn.textContent = pending().length ? cfg.i18n.install : cfg.i18n.import;
		}).then(function () {
			busy = false;
			btn.disabled = false;
			btn.classList.remove('is-loading');
		});
	});

	// Changelog: show two entries, reveal the rest on demand.
	var rows = document.querySelectorAll('#jewelry-outlet-changelog-container .block-changelog');
	var more = document.getElementById('jewelry-outlet-load-more');
	rows.forEach(function (row, i) { if (i > 1) { row.hidden = true; } });
	if (more) {
		if (rows.length <= 2) { more.hidden = true; }
		more.addEventListener('click', function () {
			rows.forEach(function (row) { row.hidden = false; });
			more.hidden = true;
		});
	}

	// Coupon copy buttons.
	function copyText(code) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(code);
		}
		return new Promise(function (resolve, reject) {
			var ta = document.createElement('textarea');
			ta.value = code;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			var ok = document.execCommand('copy');
			document.body.removeChild(ta);
			if (ok) { resolve(); } else { reject(); }
		});
	}
	document.querySelectorAll('.wpet-coupon-copy').forEach(function (b) {
		b.addEventListener('click', function () {
			copyText(b.getAttribute('data-code')).then(function () {
				b.textContent = cfg.i18n.copied;
				b.classList.add('is-copied');
				setTimeout(function () {
					b.textContent = cfg.i18n.copy;
					b.classList.remove('is-copied');
				}, 1800);
			}).catch(function () {});
		});
	});

	render();
})();
