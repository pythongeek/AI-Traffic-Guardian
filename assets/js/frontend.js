/**
 * AI Traffic Guardian — accessible human-confirmation beacon.
 *
 * Accessibility contract:
 *  - Never blocks rendering, never gates any form, never required.
 *  - Listens passively for ANY natural interaction: pointer, keyboard,
 *    scroll, or touch. Keyboard-only and switch-device users are covered
 *    by the 'keydown' and 'scroll' paths; no mouse is required.
 *  - Sends one beacon per session window. navigator.webdriver=true
 *    downgrades the signal instead of hard-failing the user.
 */
(function () {
	'use strict';

	var cfg = window.ATG_FRONT || {};
	if (!cfg.beacon) { return; }

	function getCookie(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : '';
	}

	var sent = false;
	function confirmHuman(eventName) {
		if (sent) { return; }
		sent = true;
		var sid = getCookie('atg_sid');
		if (!sid) { return; }
		try {
			fetch(cfg.beacon, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({
					sid: sid,
					event: eventName,
					webdriver: !!navigator.webdriver,
					ts: Date.now()
				}),
				keepalive: true
			});
		} catch (e) { /* Beacon failure must never affect the page. */ }
	}

	var opts = { passive: true, once: true };
	window.addEventListener('pointerdown', function () { confirmHuman('pointer'); }, opts);
	window.addEventListener('keydown', function () { confirmHuman('key'); }, opts);
	window.addEventListener('touchstart', function () { confirmHuman('touch'); }, opts);
	window.addEventListener('scroll', function () { confirmHuman('scroll'); }, opts);
})();
