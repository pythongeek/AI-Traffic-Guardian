/**
 * Bot Shield Pro — admin dashboard controller.
 * Vanilla JS + Chart.js. Talks to the atg/v1 REST API.
 */
(function () {
	'use strict';

	var cfg = window.ATG_ADMIN || {};
	var page = cfg.page || 'atg-dashboard';

	/* ---------------- REST helper ---------------- */
	function api(path, options) {
		options = options || {};
		var opts = {
			method: options.method || 'GET',
			headers: {
				'X-WP-Nonce': cfg.nonce,
				'Content-Type': 'application/json'
			},
			credentials: 'same-origin'
		};
		if (options.body) {
			opts.body = JSON.stringify(options.body);
		}
		return fetch(cfg.rest + path, opts).then(function (r) {
			if (!r.ok) {
				var err = 'REST API Request to ' + path + ' failed with status ' + r.status;
				fetch(cfg.rest + 'debug-log', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ message: err })
				});
				throw new Error('HTTP ' + r.status);
			}
			return r.json();
		}).catch(function (error) {
			var err = 'REST API Request to ' + path + ' threw error: ' + error.message;
			fetch(cfg.rest + 'debug-log', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ message: err })
			});
			throw error;
		});
	}

	window.addEventListener('error', function (e) {
		var msg = 'JS Error: ' + e.message + ' in ' + e.filename + ' on line ' + e.lineno;
		fetch(cfg.rest + 'debug-log', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ message: msg })
		});
	});

	/* ---------------- Toast ---------------- */
	var toastEl = null;
	function toast(msg, isError) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'atg-toast';
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.className = 'atg-toast is-visible' + (isError ? ' is-error' : '');
		setTimeout(function () { toastEl.classList.remove('is-visible'); }, 2600);
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function num(n) {
		return (n || 0).toLocaleString();
	}

	function pill(action) {
		var cls = action === 'allow' ? 'allow' : (action === 'block' ? 'block' : (action === 'throttle' ? 'throttle' : 'neutral'));
		return '<span class="atg-pill atg-pill-' + cls + '">' + esc(action) + '</span>';
	}

	function verifiedPill(row) {
		if (row.spoofed === '1' || row.spoofed === 1) {
			return '<span class="atg-pill atg-pill-spoof">spoofed</span>';
		}
		if (row.verified === '1' || row.verified === 1) {
			return '<span class="atg-pill atg-pill-yes">verified</span>';
		}
		if (row.verified === '0' || row.verified === 0) {
			return '<span class="atg-pill atg-pill-no">unverified</span>';
		}
		return '<span class="atg-pill atg-pill-neutral">n/a</span>';
	}

	/* ---------------- Top bar: panic + resume ---------------- */
	document.addEventListener('click', function (e) {
		var panic = e.target.closest('[data-atg-panic]');
		var resume = e.target.closest('[data-atg-resume]');
		if (panic) {
			if (!window.confirm(cfg.i18n.confirmPanic)) { return; }
			api('mode', { method: 'POST', body: { mode: 'off' } }).then(function () {
				toast(cfg.i18n.saved);
				setTimeout(function () { location.reload(); }, 600);
			}).catch(function () { toast(cfg.i18n.error, true); });
		}
		if (resume) {
			var mode = resume.getAttribute('data-mode');
			if (mode === 'active' && !window.confirm(cfg.i18n.confirmActive)) { return; }
			api('mode', { method: 'POST', body: { mode: mode } }).then(function () {
				toast(cfg.i18n.saved);
				setTimeout(function () { location.reload(); }, 600);
			}).catch(function () { toast(cfg.i18n.error, true); });
		}
	});

	/* =========================================================
	 * DASHBOARD
	 * ======================================================= */
	function initDashboard() {
		var days = 7;
		var seriesChart = null;
		var purposeChart = null;
		var summaryData = null;

		var CLASS_COLORS = {
			human: '#059669',
			authenticated: '#10b981',
			allowlisted: '#34d399',
			internal: '#6ee7b7',
			agent_proxy: '#0ea5e9',
			bot: '#dc2626',
			unknown_bot: '#f97316',
			form_abuse: '#9333ea'
		};
		var PURPOSE_COLORS = {
			search_engine: '#059669',
			ai_search: '#0ea5e9',
			ai_training: '#dc2626',
			agent_proxy: '#8b5cf6',
			seo_tool: '#f59e0b',
			social: '#ec4899',
			feed: '#14b8a6',
			monitor: '#6366f1',
			scraper: '#991b1b'
		};

		function loadSummary() {
			api('summary?days=' + days).then(function (data) {
				summaryData = data;
				// KPIs.
				document.querySelector('[data-kpi="total"]').textContent = num(data.kpis.total);
				document.querySelector('[data-kpi="bot_share"]').textContent = data.kpis.bot_share + '%';
				document.querySelector('[data-kpi="blocked"]').textContent = num(data.kpis.blocked);
				document.querySelector('[data-kpi="throttled"]').textContent = num(data.kpis.throttled);
				document.querySelector('[data-kpi="human_eq"]').textContent = num(data.kpis.human_eq);
				document.querySelector('[data-kpi="alerts"]').textContent = num(data.kpis.alerts);

				// Shadow banner.
				var banner = document.querySelector('[data-atg-shadow-banner]');
				if (data.mode === 'shadow' && banner) {
					banner.hidden = false;
					var cd = banner.querySelector('[data-atg-shadow-countdown]');
					if (cd && data.shadow.remaining > 0) {
						var hrs = Math.floor(data.shadow.remaining / 3600);
						var dLeft = Math.floor(hrs / 24);
						cd.textContent = dLeft > 0
							? 'Recommended observation time left: ' + dLeft + ' day(s)'
							: 'Recommended observation time left: ' + hrs + ' hour(s)';
					} else if (cd) {
						cd.textContent = 'Observation period complete — you can go live whenever ready.';
					}
				}

				renderSeries(data.series);
				renderPurpose(data.purposes);
				renderVendors(data.vendors);
				renderCountries(data.countries || []);
				calculateCostAndBandwidth();

				if (data.shadow_snapshot && data.shadow_snapshot.total > 0 && data.mode !== 'shadow') {
					var compWidget = document.getElementById('atg-comparison-widget');
					if (compWidget) {
						compWidget.style.display = 'block';
						document.getElementById('atg-compare-shadow-share').textContent = data.shadow_snapshot.bot_share + '%';
						document.getElementById('atg-compare-shadow-desc').textContent = 'Bot traffic share out of ' + num(data.shadow_snapshot.total) + ' requests';
						document.getElementById('atg-compare-active-share').textContent = data.kpis.bot_share + '%';
						document.getElementById('atg-compare-active-desc').textContent = 'Active bot share (' + num(data.kpis.blocked) + ' blocked requests)';
					}
				} else {
					var compWidget = document.getElementById('atg-comparison-widget');
					if (compWidget) { compWidget.style.display = 'none'; }
				}
			}).catch(function () { toast(cfg.i18n.error, true); });

			api('log?per_page=10').then(function (res) {
				renderRecent(res.rows);
			}).catch(function () {});
		}

		function calculateCostAndBandwidth() {
			if (!summaryData) { return; }
			var costMultiplier = parseFloat(document.getElementById('atg-cost-multiplier').value) || 0.05;
			var bandwidthMultiplier = parseFloat(document.getElementById('atg-bandwidth-multiplier').value) || 150;

			var total = summaryData.kpis.total || 0;
			var botShare = summaryData.kpis.bot_share || 0;
			var blocked = summaryData.kpis.blocked || 0;

			var botRequests = Math.round(total * (botShare / 100));
			var costSavings = (blocked / 10000) * costMultiplier;
			var bandwidthSaved = blocked * bandwidthMultiplier;

			var bandwidthStr = '';
			if (bandwidthSaved >= 1024 * 1024) {
				bandwidthStr = (bandwidthSaved / (1024 * 1024)).toFixed(2) + ' GB';
			} else if (bandwidthSaved >= 1024) {
				bandwidthStr = (bandwidthSaved / 1024).toFixed(2) + ' MB';
			} else {
				bandwidthStr = bandwidthSaved.toFixed(0) + ' KB';
			}

			document.getElementById('atg-cost-total-bots').textContent = num(botRequests);
			document.getElementById('atg-cost-blocked-bots').textContent = num(blocked);
			document.getElementById('atg-cost-savings').textContent = '$' + costSavings.toFixed(2);
			document.getElementById('atg-bandwidth-saved').textContent = bandwidthStr;

			var monthlyMultiplier = 30 / days;
			var projectedRequests = Math.round(total * monthlyMultiplier);
			var projectedBotRequests = Math.round(projectedRequests * (botShare / 100));
			var projectedCost = (projectedBotRequests / 10000) * costMultiplier;

			document.getElementById('atg-projected-requests').textContent = num(projectedBotRequests);
			document.getElementById('atg-projected-cost').textContent = '$' + projectedCost.toFixed(2);
		}

		function renderSeries(series) {
			var byDay = {};
			var classes = {};
			series.forEach(function (r) {
				if (!byDay[r.day]) { byDay[r.day] = {}; }
				byDay[r.day][r.classification] = parseInt(r.hits, 10);
				classes[r.classification] = true;
			});
			var labels = Object.keys(byDay).sort();
			var datasets = Object.keys(classes).map(function (c) {
				return {
					label: c.replace(/_/g, ' '),
					data: labels.map(function (d) { return byDay[d][c] || 0; }),
					backgroundColor: CLASS_COLORS[c] || '#94a3b8',
					borderColor: CLASS_COLORS[c] || '#94a3b8',
					fill: true,
					stack: 'total',
					tension: 0.3,
					borderWidth: 1,
					pointRadius: 0
				};
			});
			var ctx = document.getElementById('atg-chart-series');
			if (!ctx) { return; }
			if (seriesChart) { seriesChart.destroy(); }
			seriesChart = new Chart(ctx, {
				type: 'line',
				data: { labels: labels, datasets: datasets },
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { mode: 'index', intersect: false },
					scales: {
						y: { stacked: true, beginAtZero: true, grid: { color: '#f1f5f9' } },
						x: { stacked: true, grid: { display: false } }
					},
					plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
				}
			});
		}

		function renderPurpose(purposes) {
			var labels = purposes.map(function (p) { return p.purpose.replace(/_/g, ' '); });
			var data = purposes.map(function (p) { return parseInt(p.hits, 10); });
			var colors = purposes.map(function (p) { return PURPOSE_COLORS[p.purpose] || '#94a3b8'; });
			var ctx = document.getElementById('atg-chart-purpose');
			if (!ctx) { return; }
			if (purposeChart) { purposeChart.destroy(); }
			purposeChart = new Chart(ctx, {
				type: 'doughnut',
				data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
				options: {
					responsive: true,
					maintainAspectRatio: false,
					cutout: '62%',
					plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }
				}
			});
		}

		function renderVendors(vendors) {
			var tbody = document.querySelector('[data-atg-vendors] tbody');
			if (!tbody) { return; }
			if (!vendors.length) {
				tbody.innerHTML = '<tr><td colspan="3" class="atg-empty">No bot traffic recorded yet.</td></tr>';
				return;
			}
			var total = vendors.reduce(function (s, v) { return s + parseInt(v.hits, 10); }, 0);
			tbody.innerHTML = vendors.map(function (v) {
				var share = total ? Math.round((parseInt(v.hits, 10) / total) * 100) : 0;
				return '<tr><td><strong>' + esc(v.vendor) + '</strong></td><td>' + num(parseInt(v.hits, 10)) + '</td><td>' + share + '%</td></tr>';
			}).join('');
		}

		function renderCountries(countries) {
			var tbody = document.querySelector('[data-atg-countries] tbody');
			if (!tbody) { return; }
			if (!countries.length) {
				tbody.innerHTML = '<tr><td colspan="3" class="atg-empty">No geographical traffic recorded yet.</td></tr>';
				return;
			}
			var total = countries.reduce(function (s, v) { return s + parseInt(v.hits, 10); }, 0);
			tbody.innerHTML = countries.map(function (c) {
				var share = total ? Math.round((parseInt(c.hits, 10) / total) * 100) : 0;
				var name = c.country === 'unknown' || !c.country ? 'Unknown' : c.country;
				return '<tr><td><strong>' + esc(name) + '</strong></td><td>' + num(parseInt(c.hits, 10)) + '</td><td>' + share + '%</td></tr>';
			}).join('');
		}

		function renderRecent(rows) {
			var tbody = document.querySelector('[data-atg-recent] tbody');
			if (!tbody) { return; }
			if (!rows.length) {
				tbody.innerHTML = '<tr><td colspan="4" class="atg-empty">No decisions logged yet. Traffic will appear here as it arrives.</td></tr>';
				return;
			}
			tbody.innerHTML = rows.map(function (r) {
				var uaStr = r.ua || '';
				var name = r.bot_name || (uaStr ? uaStr.substring(0, 42) : r.classification);
				return '<tr>' +
					'<td>' + esc(r.ts) + '</td>' +
					'<td title="' + esc(uaStr) + '">' + esc(name) + '</td>' +
					'<td>' + verifiedPill(r) + '</td>' +
					'<td>' + pill(r.action) + (r.enforced === '0' ? ' <span class="atg-pill atg-pill-neutral">shadow</span>' : '') + '</td>' +
					'</tr>';
			}).join('');
		}

		// Tab navigation logic.
		document.querySelectorAll('.atg-tabs a').forEach(function(tab) {
			tab.addEventListener('click', function(e) {
				e.preventDefault();
				document.querySelectorAll('.atg-tabs a').forEach(function(t) { t.classList.remove('nav-tab-active'); });
				tab.classList.add('nav-tab-active');
				var target = tab.getAttribute('data-tab');
				if (target === 'overview') {
					document.getElementById('atg-dashboard-overview-tab').style.display = 'block';
					document.getElementById('atg-dashboard-cost-tab').style.display = 'none';
				} else {
					document.getElementById('atg-dashboard-overview-tab').style.display = 'none';
					document.getElementById('atg-dashboard-cost-tab').style.display = 'block';
					calculateCostAndBandwidth();
				}
			});
		});

		var recalcBtn = document.getElementById('atg-recalculate-cost-btn');
		if (recalcBtn) {
			recalcBtn.addEventListener('click', calculateCostAndBandwidth);
		}

		// Range buttons.
		document.querySelectorAll('[data-atg-range] button').forEach(function (btn) {
			btn.addEventListener('click', function () {
				document.querySelectorAll('[data-atg-range] button').forEach(function (b) { b.classList.remove('is-active'); });
				btn.classList.add('is-active');
				days = parseInt(btn.getAttribute('data-days'), 10);
				loadSummary();
			});
		});
		var refresh = document.querySelector('[data-atg-refresh]');
		if (refresh) { refresh.addEventListener('click', loadSummary); }

		loadSummary();
		setInterval(loadSummary, 60000);
	}

	/* =========================================================
	 * POLICY MATRIX
	 * ======================================================= */
	function initPolicy() {
		var state = { matrix: {}, signatures: [], purposes: {} };
		var customSignatures = [];

		api('policy').then(function (data) {
			state.matrix = data.matrix;
			state.signatures = data.signatures;
			state.purposes = data.purposes;
			renderPresets(data.presets);
			renderMatrix();
			initCustomSignaturesForm();
			loadCustomSignatures();
		}).catch(function () { toast(cfg.i18n.error, true); });

		// Export
		var exportBtn = document.getElementById('atg-export-policy-btn');
		if (exportBtn) {
			exportBtn.addEventListener('click', function () {
				window.location = cfg.rest + 'policy/export?_wpnonce=' + encodeURIComponent(cfg.nonce);
			});
		}

		// Import
		var importFile = document.getElementById('atg-import-policy-file');
		if (importFile) {
			importFile.addEventListener('change', function (e) {
				var file = e.target.files[0];
				if (!file) return;
				var reader = new FileReader();
				reader.onload = function (evt) {
					try {
						var config = JSON.parse(evt.target.result);
						api('policy/import', {
							method: 'POST',
							body: config
						}).then(function () {
							toast(cfg.i18n.saved);
							reloadAll();
						}).catch(function () {
							toast(cfg.i18n.error, true);
						});
					} catch (ex) {
						toast('Invalid JSON file.', true);
					}
				};
				reader.readAsText(file);
			});
		}

		function initCustomSignaturesForm() {
			var purposeSel = document.getElementById('atg-sig-purpose');
			if (purposeSel) {
				purposeSel.innerHTML = Object.keys(state.purposes).map(function (k) {
					return '<option value="' + esc(k) + '">' + esc(state.purposes[k]) + '</option>';
				}).join('');
			}

			var verifySel = document.getElementById('atg-sig-verify');
			if (verifySel) {
				verifySel.addEventListener('change', function () {
					var val = verifySel.value;
					document.querySelectorAll('.atg-sig-verify-extra').forEach(function (el) {
						el.style.display = 'none';
					});
					if (val === 'rdns') {
						var rdnsEx = document.querySelector('.rdns-extra');
						if (rdnsEx) rdnsEx.style.display = 'block';
					} else if (val === 'ip_range') {
						var ipEx = document.querySelector('.ip-range-extra');
						if (ipEx) ipEx.style.display = 'block';
					}
				});
			}

			var addBtn = document.getElementById('atg-add-custom-sig-btn');
			if (addBtn) {
				addBtn.addEventListener('click', function () {
					showForm();
				});
			}

			var cancelBtn = document.getElementById('atg-cancel-sig-btn');
			if (cancelBtn) {
				cancelBtn.addEventListener('click', function () {
					hideForm();
				});
			}

			var form = document.getElementById('atg-custom-sig-form');
			if (form) {
				form.addEventListener('submit', function (e) {
					e.preventDefault();
					saveCustomSignature();
				});
			}
		}

		function showForm(sig, index) {
			var formWrap = document.getElementById('atg-custom-sig-form-wrap');
			if (!formWrap) return;
			formWrap.style.display = 'block';
			
			var titleEl = document.getElementById('atg-form-title');
			var indexEl = document.getElementById('atg-sig-index');
			var nameEl = document.getElementById('atg-sig-name');
			var vendorEl = document.getElementById('atg-sig-vendor');
			var purposeEl = document.getElementById('atg-sig-purpose');
			var patternEl = document.getElementById('atg-sig-pattern');
			var verifyEl = document.getElementById('atg-sig-verify');
			var rdnsEl = document.getElementById('atg-sig-rdns');
			var ipSourceEl = document.getElementById('atg-sig-ip-source');

			if (sig && index !== undefined) {
				titleEl.textContent = 'Edit Custom Signature';
				indexEl.value = index;
				nameEl.value = sig.name || '';
				vendorEl.value = sig.vendor || '';
				purposeEl.value = sig.purpose || '';
				patternEl.value = sig.pattern || '';
				verifyEl.value = sig.verify || 'none';
				rdnsEl.value = (sig.rdns_suffix || []).join(', ');
				ipSourceEl.value = sig.ip_source || '';
			} else {
				titleEl.textContent = 'Add Custom Signature';
				indexEl.value = '';
				nameEl.value = '';
				vendorEl.value = '';
				purposeEl.value = Object.keys(state.purposes)[0] || '';
				patternEl.value = '';
				verifyEl.value = 'none';
				rdnsEl.value = '';
				ipSourceEl.value = '';
			}

			var event = new Event('change');
			verifyEl.dispatchEvent(event);
		}

		function hideForm() {
			var formWrap = document.getElementById('atg-custom-sig-form-wrap');
			if (formWrap) {
				formWrap.style.display = 'none';
			}
		}

		function loadCustomSignatures() {
			var tbody = document.querySelector('#atg-custom-signatures-table tbody');
			if (!tbody) return;
			api('custom-signatures').then(function (data) {
				customSignatures = data.signatures || [];
				if (!customSignatures.length) {
					tbody.innerHTML = '<tr><td colspan="6" class="atg-empty">No custom signatures defined yet.</td></tr>';
					return;
				}
				tbody.innerHTML = customSignatures.map(function (sig, idx) {
					var verifyText = sig.verify === 'rdns' ? 'rDNS' : (sig.verify === 'ip_range' ? 'IP range' : 'None');
					return '<tr>' +
						'<td><strong>' + esc(sig.name) + '</strong></td>' +
						'<td>' + esc(sig.vendor) + '</td>' +
						'<td>' + esc(state.purposes[sig.purpose] || sig.purpose) + '</td>' +
						'<td><code>' + esc(sig.pattern) + '</code></td>' +
						'<td>' + esc(verifyText) + '</td>' +
						'<td>' +
							'<button type="button" class="button atg-edit-custom-sig" data-idx="' + idx + '">Edit</button> ' +
							'<button type="button" class="button atg-delete-custom-sig" style="color:#dc2626;" data-idx="' + idx + '">Delete</button>' +
						'</td>' +
						'</tr>';
				}).join('');

				tbody.querySelectorAll('.atg-edit-custom-sig').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var idx = parseInt(btn.getAttribute('data-idx'), 10);
						showForm(customSignatures[idx], idx);
					});
				});

				tbody.querySelectorAll('.atg-delete-custom-sig').forEach(function (btn) {
					btn.addEventListener('click', function () {
						if (!confirm('Are you sure you want to delete this custom signature?')) return;
						var idx = parseInt(btn.getAttribute('data-idx'), 10);
						api('custom-signatures/' + idx, { method: 'DELETE' }).then(function () {
							toast(cfg.i18n.saved);
							hideForm();
							return reloadAll();
						}).catch(function () { toast(cfg.i18n.error, true); });
					});
				});
			}).catch(function () {
				tbody.innerHTML = '<tr><td colspan="6" class="atg-empty is-error">Failed to load custom signatures.</td></tr>';
			});
		}

		function saveCustomSignature() {
			var index = document.getElementById('atg-sig-index').value;
			var name = document.getElementById('atg-sig-name').value;
			var vendor = document.getElementById('atg-sig-vendor').value;
			var purpose = document.getElementById('atg-sig-purpose').value;
			var pattern = document.getElementById('atg-sig-pattern').value;
			var verify = document.getElementById('atg-sig-verify').value;
			var rdnsVal = document.getElementById('atg-sig-rdns').value;
			var ipSource = document.getElementById('atg-sig-ip-source').value;

			var rdnsSuffix = rdnsVal.split(',').map(function (s) { return s.trim(); }).filter(Boolean);

			var payload = {
				name: name,
				vendor: vendor,
				purpose: purpose,
				pattern: pattern,
				verify: verify,
				rdns_suffix: rdnsSuffix,
				ip_source: ipSource
			};

			var url = 'custom-signatures';
			if (index !== '') {
				url += '/' + index;
			}

			api(url, { method: 'POST', body: payload }).then(function () {
				toast(cfg.i18n.saved);
				hideForm();
				reloadAll();
			}).catch(function (err) {
				toast(cfg.i18n.error, true);
			});
		}

		function reloadAll() {
			return api('policy').then(function (data) {
				state.matrix = data.matrix;
				state.signatures = data.signatures;
				renderMatrix();
				loadCustomSignatures();
			});
		}

		function renderPresets(presets) {
			var wrap = document.querySelector('[data-atg-presets]');
			if (!wrap) { return; }
			if (!presets || !Object.keys(presets).length) {
				wrap.innerHTML = '<div class="atg-empty">No presets available.</div>';
				return;
			}
			wrap.innerHTML = Object.keys(presets).map(function (key) {
				var p = presets[key];
				return '<div class="atg-preset">' +
					'<h3>' + esc(p.label) + '</h3>' +
					'<p>' + esc(p.description) + '</p>' +
					'<button type="button" class="button button-primary atg-apply-preset-btn" data-preset="' + esc(key) + '">Apply this preset</button>' +
					'</div>';
			}).join('');

			wrap.onclick = function (e) {
				var btn = e.target.closest('.atg-apply-preset-btn');
				if (!btn) return;
				e.preventDefault();
				var presetKey = btn.getAttribute('data-preset');
				btn.disabled = true;
				api('policy/preset', { method: 'POST', body: { preset: presetKey } })
					.then(function () {
						btn.disabled = false;
						toast(cfg.i18n.saved);
						return reloadAll();
					})
					.catch(function () {
						btn.disabled = false;
						toast(cfg.i18n.error, true);
					});
			};
		}

		function renderMatrix() {
			var tbody = document.querySelector('[data-atg-matrix] tbody');
			if (!tbody) { return; }
			tbody.innerHTML = state.signatures.map(function (sig) {
				var vendorRow = state.matrix[sig.vendor] || {};
				var action = vendorRow[sig.purpose] || 'allow';
				var verify = sig.verify === 'rdns' ? 'DNS verify' : (sig.verify === 'ip_range' ? 'IP ranges' : 'Unverifiable → throttle+log');
				return '<tr>' +
					'<td><strong>' + esc(sig.name) + '</strong></td>' +
					'<td>' + esc(sig.vendor) + '</td>' +
					'<td>' + esc(state.purposes[sig.purpose] || sig.purpose) + '</td>' +
					'<td><span class="atg-tag">' + esc(verify) + '</span></td>' +
					'<td><select data-vendor="' + esc(sig.vendor) + '" data-purpose="' + esc(sig.purpose) + '">' +
						['allow', 'throttle', 'block'].map(function (a) {
							return '<option value="' + a + '"' + (a === action ? ' selected' : '') + '>' + a.charAt(0).toUpperCase() + a.slice(1) + '</option>';
						}).join('') +
					'</select></td>' +
					'</tr>';
			}).join('');

			tbody.querySelectorAll('select').forEach(function (sel) {
				sel.addEventListener('change', function () {
					api('policy', {
						method: 'POST',
						body: {
							vendor: sel.getAttribute('data-vendor'),
							purpose: sel.getAttribute('data-purpose'),
							action: sel.value
						}
					}).then(function () { toast(cfg.i18n.saved); })
					  .catch(function () { toast(cfg.i18n.error, true); });
				});
			});
		}
	}

	/* =========================================================
	 * TRAFFIC LOG
	 * ======================================================= */
	function initLog() {
		var state = { page: 1, pages: 1 };

		function collectFilters() {
			var f = {};
			document.querySelectorAll('[data-filter]').forEach(function (el) {
				if (el.value) { f[el.getAttribute('data-filter')] = el.value; }
			});
			return f;
		}

		function load() {
			var f = collectFilters();
			f.page = state.page;
			var qs = Object.keys(f).map(function (k) { return k + '=' + encodeURIComponent(f[k]); }).join('&');
			api('log?' + qs).then(function (res) {
				state.pages = res.pages;
				renderRows(res.rows);
				renderPager(res);
			}).catch(function () { toast(cfg.i18n.error, true); });
		}

		function renderRows(rows) {
			var tbody = document.querySelector('[data-atg-log-table] tbody');
			if (!tbody) { return; }
			if (!rows.length) {
				tbody.innerHTML = '<tr><td colspan="10" class="atg-empty">No matching records.</td></tr>';
				return;
			}
			tbody.innerHTML = rows.map(function (r, idx) {
				var name = r.bot_name || (r.ua ? r.ua.substring(0, 40) : '—');
				return '<tr>' +
					'<td style="white-space:nowrap">' + esc(r.ts) + '</td>' +
					'<td><span class="atg-tag">' + esc(r.classification) + '</span></td>' +
					'<td title="' + esc(r.ua) + '">' + esc(name) + '</td>' +
					'<td title="' + esc(r.path) + '">' + esc(r.path.substring(0, 48)) + '</td>' +
					'<td>' + verifiedPill(r) + '</td>' +
					'<td>' + pill(r.action) + '</td>' +
					'<td>' + (r.enforced === '1' ? '<span class="atg-pill atg-pill-yes">yes</span>' : '<span class="atg-pill atg-pill-neutral">shadow</span>') + '</td>' +
					'<td><code>' + esc(r.ip_display) + '</code></td>' +
					'<td title="' + esc(r.reason) + '">' + esc((r.reason || '').substring(0, 40)) + '</td>' +
					'<td><button type="button" class="button atg-replay-btn" data-idx="' + idx + '">Replay</button></td>' +
					'</tr>';
			}).join('');

			tbody.querySelectorAll('.atg-replay-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var idx = parseInt(btn.getAttribute('data-idx'), 10);
					var row = rows[idx];
					api('debug-replay', {
						method: 'POST',
						body: {
							ua: row.ua,
							ip: row.ip || '',
							path: row.path
						}
					}).then(function (res) {
						var dec = res.decision;
						var msg = 'Replay Results:\n' +
							'Classification: ' + dec.classification + '\n' +
							'Action: ' + dec.action + '\n' +
							'Reason: ' + dec.reason + '\n' +
							'Status: ' + dec.status_code + '\n' +
							'Risk: ' + dec.risk + '%';
						alert(msg);
					}).catch(function () {
						toast('Replay failed.', true);
					});
				});
			});
		}

		function renderPager(res) {
			var pager = document.querySelector('[data-atg-pagination]');
			if (!pager) { return; }
			var html = '';
			for (var p = 1; p <= res.pages && p <= 12; p++) {
				html += '<button data-page="' + p + '"' + (p === state.page ? ' class="is-active"' : '') + '>' + p + '</button>';
			}
			html += '<span>' + num(res.total) + ' records</span>';
			pager.innerHTML = html;
			pager.querySelectorAll('button').forEach(function (b) {
				b.addEventListener('click', function () {
					state.page = parseInt(b.getAttribute('data-page'), 10);
					load();
				});
			});
		}

		var apply = document.querySelector('[data-atg-apply-filters]');
		if (apply) {
			apply.addEventListener('click', function () { state.page = 1; load(); });
		}
		var search = document.querySelector('[data-filter="search"]');
		if (search) {
			search.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { state.page = 1; load(); }
			});
		}
		var exp = document.querySelector('[data-atg-export]');
		if (exp) {
			exp.addEventListener('click', function () {
				var f = collectFilters();
				var qs = Object.keys(f).map(function (k) { return k + '=' + encodeURIComponent(f[k]); }).join('&');
				window.location = cfg.rest + 'export?' + qs + '&_wpnonce=' + encodeURIComponent(cfg.nonce);
			});
		}

		load();
	}

	/* =========================================================
	 * ALLOWLIST
	 * ======================================================= */
	function initAllowlist() {
		var pathRules = [];

		api('allowlist').then(function (data) {
			document.querySelector('[data-atg-ips]').value = (data.allowlist.ips || []).join('\n');
			document.querySelector('[data-atg-paths]').value = (data.allowlist.paths || []).join('\n');
			document.querySelector('[data-atg-uas]').value = (data.allowlist.uas || []).join('\n');
			pathRules = data.allowlist.path_rules || [];
			renderPathRules();

			var tags = document.querySelector('[data-atg-protected-paths]');
			if (tags) {
				tags.innerHTML = (data.protected || []).map(function (p) {
					return '<span class="atg-tag">' + esc(p) + '</span>';
				}).join('');
			}
		}).catch(function () { toast(cfg.i18n.error, true); });

		function renderPathRules() {
			var tbody = document.querySelector('#atg-path-rules-table tbody');
			if (!tbody) return;
			if (!pathRules.length) {
				tbody.innerHTML = '<tr><td colspan="3" class="atg-empty">No path overrides defined yet.</td></tr>';
				return;
			}
			tbody.innerHTML = pathRules.map(function (rule, idx) {
				return '<tr>' +
					'<td><code>' + esc(rule.path) + '</code></td>' +
					'<td><span class="atg-pill atg-pill-' + esc(rule.action) + '">' + esc(rule.action) + '</span></td>' +
					'<td><button type="button" class="button atg-remove-path-rule" data-idx="' + idx + '">Remove</button></td>' +
					'</tr>';
			}).join('');

			tbody.querySelectorAll('.atg-remove-path-rule').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var idx = parseInt(btn.getAttribute('data-idx'), 10);
					pathRules.splice(idx, 1);
					renderPathRules();
				});
			});
		}

		var addRuleBtn = document.getElementById('atg-add-path-rule-btn');
		if (addRuleBtn) {
			addRuleBtn.addEventListener('click', function () {
				var pathInput = document.getElementById('atg-new-path-rule-path');
				var actionInput = document.getElementById('atg-new-path-rule-action');
				var pathVal = pathInput.value.trim();
				var actionVal = actionInput.value;
				if (!pathVal) return;
				pathRules.push({ path: pathVal, action: actionVal });
				pathInput.value = '';
				renderPathRules();
			});
		}

		var save = document.querySelector('[data-atg-save-allowlist]');
		if (save) {
			save.addEventListener('click', function () {
				function lines(sel) {
					return document.querySelector(sel).value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
				}
				api('allowlist', {
					method: 'POST',
					body: {
						ips: lines('[data-atg-ips]'),
						paths: lines('[data-atg-paths]'),
						uas: lines('[data-atg-uas]'),
						path_rules: pathRules
					}
				}).then(function () { toast(cfg.i18n.saved); })
				  .catch(function () { toast(cfg.i18n.error, true); });
			});
		}
	}

	/* =========================================================
	 * ALERTS
	 * ======================================================= */
	function initAlerts() {
		var sel = document.querySelector('[data-atg-alert-status]');

		function load() {
			api('alerts?status=' + (sel ? sel.value : 'open')).then(function (data) {
				render(data.alerts);
			}).catch(function () { toast(cfg.i18n.error, true); });
		}

		function render(alerts) {
			var wrap = document.querySelector('[data-atg-alerts-list]');
			if (!wrap) { return; }
			if (!alerts.length) {
				wrap.innerHTML = '<div class="atg-empty">No alerts here. New AI bot signatures will show up automatically.</div>';
				return;
			}
			wrap.innerHTML = alerts.map(function (a) {
				var ua = a.payload && a.payload.ua ? a.payload.ua : '';
				return '<div class="atg-alert-item ' + (a.status === 'open' ? 'is-open' : '') + '">' +
					'<div class="atg-alert-body">' +
						'<strong>' + esc(a.title) + '</strong>' +
						'<span class="atg-alert-meta">' + esc(a.type) + ' · ' + esc(a.created) + ' · status: ' + esc(a.status) + '</span>' +
						(ua ? '<div class="atg-alert-ua">' + esc(ua) + '</div>' : '') +
					'</div>' +
					(a.status === 'open' ? '<button class="button" data-dismiss="' + a.id + '">Dismiss</button>' : '') +
					'</div>';
			}).join('');
			wrap.querySelectorAll('[data-dismiss]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					api('alerts/' + btn.getAttribute('data-dismiss') + '/dismiss', { method: 'POST' })
						.then(load)
						.catch(function () { toast(cfg.i18n.error, true); });
				});
			});
		}

		if (sel) { sel.addEventListener('change', load); }
		load();
	}

	/* =========================================================
	 * SETTINGS-BACKED PAGES (protection / analytics / seo / settings)
	 * ======================================================= */
	function initSettingsForm() {
		var form = document.querySelector('[data-atg-settings-form]');
		if (!form) { return; }

		api('settings').then(function (data) {
			// Fill inputs.
			document.querySelectorAll('[data-setting]').forEach(function (el) {
				var key = el.getAttribute('data-setting');
				if (!(key in data.settings)) { return; }
				var val = data.settings[key];
				if (el.type === 'checkbox') {
					el.checked = !!val;
				} else {
					el.value = val;
				}
			});
			// Environment table (settings page).
			var env = document.querySelector('[data-atg-env] tbody');
			if (env && data.env) {
				var rows = [
					['WordPress', data.env.wp],
					['PHP', data.env.php],
					['Multisite', data.env.multisite ? 'Yes — tables are provisioned per site' : 'No'],
					['WooCommerce', data.env.woocommerce ? 'Active — checkout protection available' : 'Not detected'],
					['Persistent object cache', data.env.object_cache ? 'Yes — rate-limit buckets use it' : 'No (transients in database)'],
					['SEO plugins detected', data.env.seo_plugins.length ? data.env.seo_plugins.join(', ') + ' (robots rules are appended safely)' : 'None']
				];
				env.innerHTML = rows.map(function (r) {
					return '<tr><th style="width:240px">' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td></tr>';
				}).join('');
			}
		}).catch(function () { toast(cfg.i18n.error, true); });

		// Robots preview (SEO page).
		var preview = document.querySelector('[data-atg-robots-preview]');
		if (preview) {
			api('robots-preview').then(function (data) {
				preview.textContent = data.rules || '(no rules — everything is set to Allow)';
				var note = document.querySelector('[data-atg-seo-detected]');
				if (note && data.seo_plugins.length) {
					note.textContent = 'Detected: ' + data.seo_plugins.join(', ') + '. Rules are appended through the WordPress robots_txt filter — your SEO plugin keeps full control.';
				}
			}).catch(function () { preview.textContent = 'Could not load preview.'; });

			var copyBtn = document.querySelector('[data-atg-copy-robots]');
			if (copyBtn) {
				copyBtn.addEventListener('click', function () {
					navigator.clipboard.writeText(preview.textContent).then(function () {
						toast('Copied to clipboard.');
					});
				});
			}
		}

		// Save.
		document.querySelectorAll('[data-atg-save-settings]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var body = {};
				document.querySelectorAll('[data-setting]').forEach(function (el) {
					var key = el.getAttribute('data-setting');
					body[key] = el.type === 'checkbox' ? el.checked : el.value;
				});
				api('settings', { method: 'POST', body: body })
					.then(function () { toast(cfg.i18n.saved); })
					.catch(function () { toast(cfg.i18n.error, true); });
			});
		});

		var clearTrafficBtn = document.getElementById('atg-clear-traffic-data-btn');
		if (clearTrafficBtn) {
			clearTrafficBtn.addEventListener('click', function () {
				if (!confirm('Are you sure you want to delete all daily traffic statistics and activity logs? This action is permanent.')) {
					return;
				}
				clearTrafficBtn.disabled = true;
				api('log', { method: 'DELETE' }).then(function () {
					clearTrafficBtn.disabled = false;
					toast('Traffic database cleared');
				}).catch(function () {
					clearTrafficBtn.disabled = false;
					toast('Failed to clear traffic database', true);
				});
			});
		}
	}

	/* =========================================================
	 * BOT SECURITY AUDIT
	 * ======================================================= */
	function initAudit() {
		var runBtn = document.getElementById('atg-run-audit');
		var rerunBtn = document.getElementById('atg-rerun-audit');
		var launcher = document.getElementById('atg-audit-launcher');
		var progressSec = document.getElementById('atg-audit-progress');
		var progressFill = document.getElementById('atg-progress-fill');
		var progressLabel = document.getElementById('atg-progress-label');
		var results = document.getElementById('atg-audit-results');

		function run() {
			if (launcher) launcher.hidden = true;
			if (results) results.hidden = true;
			if (progressSec) progressSec.hidden = false;
			if (progressFill) progressFill.style.width = '0%';
			if (progressLabel) progressLabel.textContent = 'Running diagnostics...';

			var progress = 0;
			var interval = setInterval(function() {
				if (progress < 90) {
					progress += Math.floor(Math.random() * 15) + 5;
					if (progress > 90) progress = 90;
					if (progressFill) progressFill.style.width = progress + '%';
					if (progressLabel) {
						if (progress > 60) {
							progressLabel.textContent = 'Checking robots.txt and security headers...';
						} else if (progress > 30) {
							progressLabel.textContent = 'Analyzing database schema and traffic data...';
						}
					}
				}
			}, 300);

			api('audit', { method: 'POST' }).then(function(data) {
				clearInterval(interval);
				if (progressFill) progressFill.style.width = '100%';
				if (progressLabel) progressLabel.textContent = 'Audit complete!';

				setTimeout(function() {
					if (progressSec) progressSec.hidden = true;
					renderResults(data);
					if (results) results.hidden = false;
				}, 500);
			}).catch(function() {
				clearInterval(interval);
				if (progressSec) progressSec.hidden = true;
				if (launcher) launcher.hidden = false;
				toast(cfg.i18n.error, true);
			});
		}

		if (runBtn) runBtn.addEventListener('click', run);
		if (rerunBtn) rerunBtn.addEventListener('click', run);

		function renderResults(data) {
			// Score card
			var gradeEl = document.getElementById('atg-score-grade');
			var numEl = document.getElementById('atg-score-number');
			var labelEl = document.getElementById('atg-score-label');
			var metaEl = document.getElementById('atg-score-meta');

			if (gradeEl) {
				gradeEl.textContent = data.grade.letter;
				gradeEl.style.backgroundColor = data.grade.color;
			}
			if (numEl) numEl.textContent = data.score + '%';
			if (labelEl) labelEl.textContent = 'Overall protection score: ' + data.grade.label;
			if (metaEl) metaEl.textContent = 'Generated: ' + data.generated;

			// Counts
			var failCount = 0;
			var warnCount = 0;
			var passCount = 0;
			var priorityActions = [];

			var fullReportHtml = '';

			Object.keys(data.sections).forEach(function(secKey) {
				var sec = data.sections[secKey];
				var secHtml = '<div class="atg-card atg-audit-section">';
				secHtml += '<div class="atg-card-head" style="display:flex; justify-content:space-between; align-items:center;">';
				secHtml += '<h2><span class="dashicons dashicons-' + esc(sec.icon) + '" style="margin-right:8px;vertical-align:text-bottom;"></span>' + esc(sec.label) + '</h2>';
				secHtml += '</div>';
				secHtml += '<ul class="atg-audit-checks-list" style="margin-top:15px;list-style:none;padding:0;margin-bottom:0;">';

				sec.checks.forEach(function(check) {
					if (check.status === 'fail') failCount++;
					if (check.status === 'warning') warnCount++;
					if (check.status === 'pass') passCount++;

					var statusClass = 'atg-check-' + check.status;
					var icon = 'yes-alt';
					if (check.status === 'fail') icon = 'dismiss';
					if (check.status === 'warning') icon = 'warning';
					if (check.status === 'info') icon = 'info';

					secHtml += '<li class="' + statusClass + '" style="padding:12px 0;border-bottom:1px solid #f1f5f9;display:flex;gap:12px;align-items:flex-start;">';
					secHtml += '<span class="dashicons dashicons-' + icon + '" style="margin-top:2px;"></span>';
					secHtml += '<div style="flex:1;">';
					secHtml += '<strong style="font-size:14px;">' + esc(check.label) + '</strong>';
					secHtml += '<p class="description" style="margin:5px 0 0 0;font-size:13px;line-height:1.5;">' + esc(check.detail) + '</p>';

					if (check.fix) {
						priorityActions.push(check);
						secHtml += '<div class="atg-audit-fix" style="margin-top:10px;background:#f8fafc;padding:12px;border-radius:6px;border-left:3px solid #cbd5e1;">';
						secHtml += '<strong style="display:block;margin-bottom:5px;color:#475569;font-size:13px;"><span class="dashicons dashicons-hammer" style="font-size:16px;width:16px;height:16px;margin-right:5px;vertical-align:text-bottom;"></span>How to fix: ' + esc(check.fix.title) + '</strong>';
						secHtml += '<ol style="margin:0;padding-left:18px;font-size:12px;line-height:1.6;color:#334155;">';
						check.fix.steps.forEach(function(step) {
							secHtml += '<li>' + esc(step) + '</li>';
						});
						secHtml += '</ol>';
						secHtml += '</div>';
					}

					secHtml += '</div>';
					secHtml += '</li>';
				});

				secHtml += '</ul></div>';
				fullReportHtml += secHtml;
			});

			var scFail = document.getElementById('sc-fail');
			var scWarn = document.getElementById('sc-warn');
			var scPass = document.getElementById('sc-pass');

			if (scFail) scFail.innerHTML = '<span class="dashicons dashicons-dismiss"></span> ' + failCount + ' — critical';
			if (scWarn) scWarn.innerHTML = '<span class="dashicons dashicons-warning"></span> ' + warnCount + ' — warnings';
			if (scPass) scPass.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> ' + passCount + ' — passing';

			// Priority actions
			var priorityWrap = document.getElementById('atg-priority-wrap');
			var priorityList = document.getElementById('atg-priority-list');
			if (priorityWrap && priorityList) {
				if (priorityActions.length > 0) {
					priorityWrap.hidden = false;
					priorityList.innerHTML = priorityActions.map(function(check) {
						var labelColor = check.status === 'fail' ? 'var(--atg-red)' : 'var(--atg-amber)';
						var badgeBg = check.status === 'fail' ? 'var(--atg-red-soft)' : 'var(--atg-amber-soft)';
						var itemHtml = '<div class="atg-priority-action" style="padding:15px 0;border-bottom:1px solid #f1f5f9;">';
						itemHtml += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">';
						itemHtml += '<span style="font-weight:700;font-size:10px;padding:2px 8px;border-radius:4px;color:' + labelColor + ';background-color:' + badgeBg + ';border:1px solid ' + labelColor + ';text-transform:uppercase;">' + check.status + '</span>';
						itemHtml += '<strong style="font-size:14px;">' + esc(check.label) + '</strong>';
						itemHtml += '</div>';
						itemHtml += '<p class="description" style="margin:0 0 10px 0;font-size:13px;line-height:1.5;">' + esc(check.detail) + '</p>';

						if (check.fix) {
							itemHtml += '<div style="background:#f8fafc;padding:12px;border-radius:6px;border-left:3px solid ' + labelColor + ';">';
							itemHtml += '<strong style="display:block;margin-bottom:5px;color:#475569;font-size:13px;"><span class="dashicons dashicons-hammer" style="font-size:16px;width:16px;height:16px;margin-right:5px;vertical-align:text-bottom;"></span>Fix instructions: ' + esc(check.fix.title) + '</strong>';
							itemHtml += '<ol style="margin:0;padding-left:18px;font-size:12px;line-height:1.6;color:#334155;">';
							check.fix.steps.forEach(function(step) {
								itemHtml += '<li>' + esc(step) + '</li>';
							});
							itemHtml += '</ol>';
							itemHtml += '</div>';
						}
						itemHtml += '</div>';
						return itemHtml;
					}).join('');
				} else {
					priorityWrap.hidden = true;
				}
			}

			var fullReport = document.getElementById('atg-full-report');
			if (fullReport) fullReport.innerHTML = fullReportHtml;
			var stamp = document.getElementById('atg-audit-timestamp');
			if (stamp) stamp.textContent = 'Last audit: ' + data.generated;
		}
	}

	/* =========================================================
	 * DEBUG LOGS
	 * ======================================================= */
	function initDebug() {
		var entriesContainer = document.querySelector('[data-atg-debug-entries]');
		var toggleBtn = document.querySelector('[data-atg-debug-toggle]');
		var refreshBtn = document.querySelector('[data-atg-debug-refresh]');
		var clearBtn = document.querySelector('[data-atg-debug-clear]');
		var contextSelect = document.querySelector('[data-atg-debug-context]');
		var searchInput = document.querySelector('[data-atg-debug-search]');
		var liveInterval = null;
		var isLogging = false;

		function loadDebugLogs() {
			var context = contextSelect ? contextSelect.value : '';
			api('debug?context=' + encodeURIComponent(context)).then(function (data) {
				isLogging = data.enabled;
				updateUIState(data.enabled, data.expiry);
				renderEntries(data.entries);
			}).catch(function () {
				if (entriesContainer) {
					entriesContainer.innerHTML = '<div class="atg-empty is-error">Failed to load log entries.</div>';
				}
			});
		}

		function updateUIState(enabled, expiry) {
			if (toggleBtn) {
				toggleBtn.textContent = enabled ? 'Disable logging' : 'Enable logging';
				if (enabled) {
					toggleBtn.classList.remove('button-primary');
				} else {
					toggleBtn.classList.add('button-primary');
				}
			}

			var card = document.querySelector('.atg-card');
			var pillEl = document.querySelector('.atg-card-head h2 .atg-pill');
			if (card) {
				if (enabled) {
					card.classList.add('atg-debug-active');
					card.classList.remove('atg-debug-inactive');
				} else {
					card.classList.remove('atg-debug-active');
					card.classList.add('atg-debug-inactive');
				}
			}
			if (pillEl) {
				pillEl.textContent = enabled ? 'LIVE' : 'OFF';
				pillEl.className = enabled ? 'atg-pill atg-pill-allow' : 'atg-pill atg-pill-neutral';
			}

			if (enabled) {
				if (!liveInterval) {
					liveInterval = setInterval(loadDebugLogs, 5000);
				}
			} else {
				if (liveInterval) {
					clearInterval(liveInterval);
					liveInterval = null;
				}
			}
		}

		function renderEntries(entries) {
			if (!entriesContainer) return;
			if (!entries || entries.length === 0) {
				entriesContainer.innerHTML = '<div class="atg-empty">No log entries found.</div>';
				return;
			}

			var query = searchInput ? searchInput.value.toLowerCase().trim() : '';

			var html = '<table class="atg-table atg-debug-table" style="font-family:monospace; font-size:12px;">';
			html += '<thead><tr>';
			html += '<th style="width:150px;">Timestamp</th>';
			html += '<th style="width:100px;">Context</th>';
			html += '<th>Message</th>';
			html += '<th>Caller</th>';
			html += '</tr></thead><tbody>';

			var count = 0;
			entries.forEach(function (e) {
				var msg = e.message || '';
				var caller = e.caller || '';
				var dataStr = e.data || '';

				if (query && msg.toLowerCase().indexOf(query) === -1 && caller.toLowerCase().indexOf(query) === -1 && dataStr.toLowerCase().indexOf(query) === -1) {
					return;
				}

				count++;
				var contextCls = e.context === 'stray-output' || e.context === 'php-error' || e.context === 'error' ? 'block' : (e.context === 'rest' ? 'throttle' : 'neutral');
				var rowId = 'atg-debug-row-' + count;

				html += '<tr style="cursor:pointer;" onclick="var detail = document.getElementById(\'' + rowId + '\'); detail.style.display = detail.style.display === \'none\' ? \'table-row\' : \'none\';">';
				html += '<td style="color:#64748b;">' + esc(e.ts) + '</td>';
				html += '<td><span class="atg-pill atg-pill-' + contextCls + '" style="font-size:10px;text-transform:uppercase;">' + esc(e.context) + '</span></td>';
				html += '<td><strong>' + esc(msg) + '</strong></td>';
				html += '<td style="color:#475569;">' + esc(caller) + '</td>';
				html += '</tr>';

				if (dataStr) {
					html += '<tr id="' + rowId + '" class="atg-debug-detail-row" style="display:none; background-color:#f8fafc;">';
					html += '<td colspan="4" style="padding:15px; border-bottom:1px solid #e2e8f0;">';
					html += '<pre style="margin:0; padding:10px; background:#1e293b; color:#f8fafc; border-radius:6px; overflow-x:auto; font-size:11px;">' + esc(dataStr) + '</pre>';
					html += '</td></tr>';
				}
			});

			html += '</tbody></table>';

			if (count === 0) {
				entriesContainer.innerHTML = '<div class="atg-empty">No matching log entries found.</div>';
			} else {
				entriesContainer.innerHTML = html;
			}
		}

		if (toggleBtn) {
			toggleBtn.addEventListener('click', function () {
				toggleBtn.disabled = true;
				api('debug', { method: 'POST', body: { enabled: !isLogging } }).then(function (res) {
					toggleBtn.disabled = false;
					isLogging = res.enabled;
					updateUIState(res.enabled, res.expiry);
					loadDebugLogs();
					toast('Settings saved');
				}).catch(function () {
					toggleBtn.disabled = false;
					toast('Failed to save settings', true);
				});
			});
		}

		if (refreshBtn) {
			refreshBtn.addEventListener('click', function () {
				loadDebugLogs();
				toast('Refreshed log');
			});
		}

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				if (!confirm('Are you sure you want to clear the debug log?')) return;
				clearBtn.disabled = true;
				api('debug', { method: 'DELETE' }).then(function () {
					clearBtn.disabled = false;
					loadDebugLogs();
					toast('Log cleared');
				}).catch(function () {
					clearBtn.disabled = false;
					toast('Failed to clear log', true);
				});
			});
		}

		if (contextSelect) {
			contextSelect.addEventListener('change', loadDebugLogs);
		}

		if (searchInput) {
			searchInput.addEventListener('input', function () {
				loadDebugLogs();
			});
		}

		loadDebugLogs();
	}

	/* ---------------- Router ---------------- */
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		switch (page) {
			case 'atg-dashboard': initDashboard(); break;
			case 'atg-policy': initPolicy(); break;
			case 'atg-log': initLog(); break;
			case 'atg-allowlist': initAllowlist(); break;
			case 'atg-alerts': initAlerts(); break;
			case 'atg-audit': initAudit(); break;
			case 'atg-debug': initDebug(); break;
			case 'atg-protection':
			case 'atg-analytics':
			case 'atg-seo':
			case 'atg-settings':
				initSettingsForm();
				break;
		}
	});
})();

