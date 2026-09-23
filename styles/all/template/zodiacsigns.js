/**
 * Zodiac Signs - phpBB extension
 * (c) 2026 Salvo Cortesiano - https://netshadows.de
 *
 * 1. Sign picker: automatic choice from the birthday, the member can always change it.
 * 2. Horoscope popover on the badges in viewtopic.
 */
(function () {
	'use strict';

	/* ---------- Birthday -> sign (same table as core/zodiac.php) ---------- */

	// [id, first month, first day]; Capricorn (10) spans the new year and is handled apart
	var STARTS = [[1, 3, 21], [2, 4, 20], [3, 5, 21], [4, 6, 21], [5, 7, 23], [6, 8, 23],
		[7, 9, 23], [8, 10, 23], [9, 11, 22], [11, 1, 20], [12, 2, 19]];

	function signFromDate(day, month) {
		day = parseInt(day, 10);
		month = parseInt(month, 10);
		if (!day || !month) {
			return 0;
		}
		var md = month * 100 + day;
		if (md >= 1222 || md < 120) {
			return 10;
		}
		var best = 0, bestStart = 0;
		STARTS.forEach(function (s) {
			var start = s[1] * 100 + s[2];
			if (start <= md && start > bestStart) {
				best = s[0];
				bestStart = start;
			}
		});
		return best;
	}

	function initPicker(picker) {
		var form = picker.closest('form');
		if (!form) {
			return;
		}
		var day = picker.dataset.dayField ? form.querySelector(picker.dataset.dayField) : null;
		var month = picker.dataset.monthField ? form.querySelector(picker.dataset.monthField) : null;
		var hint = picker.parentNode.querySelector('[data-zodiac-hint]');

		function radio(id) {
			return picker.querySelector('input[type="radio"][value="' + id + '"]');
		}

		function checkedId() {
			var r = picker.querySelector('input[type="radio"]:checked');
			return r ? parseInt(r.value, 10) : 0;
		}

		function computed() {
			return (day && month) ? signFromDate(day.value, month.value) : 0;
		}

		function paint() {
			picker.querySelectorAll('.zodiac-option').forEach(function (opt) {
				var input = opt.querySelector('input');
				opt.classList.toggle('is-selected', !!(input && input.checked));
			});
		}

		function say(text, kind) {
			if (!hint) {
				return;
			}
			hint.textContent = text;
			hint.className = 'zodiac-hint' + (kind ? ' zodiac-hint-' + kind : '');
			hint.hidden = !text;
		}

		function compare() {
			paint();
			var c = computed(), cur = checkedId();
			if (!c || !cur) {
				say('', '');
				return;
			}
			var name = radio(c) ? radio(c).dataset.name : '';
			if (cur === c) {
				say(picker.dataset.msgAuto.replace('%s', name), 'ok');
			} else {
				say(picker.dataset.msgMismatch.replace('%s', name), 'warn');
			}
		}

		function fromDate() {
			var c = computed(), r = c ? radio(c) : null;
			if (r) {
				r.checked = true;
			}
			compare();
		}

		if (day) {
			day.addEventListener('change', fromDate);
		}
		if (month) {
			month.addEventListener('change', fromDate);
		}
		picker.addEventListener('change', compare);

		if (!checkedId() && computed()) {
			fromDate();
		} else {
			compare();
		}
	}

	/* ---------- Horoscope popover ---------- */

	var cfgEl = document.getElementById('zodiac-config');
	var cfg = cfgEl ? cfgEl.dataset : {};
	var urls = {};
	try {
		urls = JSON.parse(cfg.urls || '{}');
	} catch (e) {
		urls = {};
	}

	var cache = {};
	var pop = null;

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		if (text) {
			n.textContent = text;
		}
		return n;
	}

	function place() {
		if (!pop) {
			return;
		}
		var r = pop._btn.getBoundingClientRect();
		var width = pop.offsetWidth;
		var left = Math.min(Math.max(12, r.left + window.scrollX), window.scrollX + document.documentElement.clientWidth - width - 12);
		pop.style.left = left + 'px';
		pop.style.top = (r.bottom + window.scrollY + 8) + 'px';
	}

	function close(focusBack) {
		if (!pop) {
			return;
		}
		var btn = pop._btn;
		pop.remove();
		pop = null;
		btn.setAttribute('aria-expanded', 'false');
		if (focusBack) {
			btn.focus();
		}
	}

	function render(data) {
		pop.textContent = '';

		var head = el('div', 'zodiac-popover-head');
		if (data.img) {
			var img = el('img');
			img.src = data.img;
			img.alt = '';
			img.width = 52;
			img.height = 52;
			head.appendChild(img);
		}
		var title = el('h3', '', data.title || '');
		title.id = 'zodiac-pop-title';
		head.appendChild(title);
		var x = el('button', 'zodiac-popover-close', '\u00d7');
		x.type = 'button';
		x.setAttribute('aria-label', cfg.close || 'Close');
		x.addEventListener('click', function () {
			close(true);
		});
		head.appendChild(x);
		pop.appendChild(head);
		pop.setAttribute('aria-labelledby', 'zodiac-pop-title');

		var items = data.items || [];
		if (!items.length) {
			pop.appendChild(el('p', 'zodiac-popover-empty', data.empty || ''));
			return;
		}

		var body = el('div', 'zodiac-popover-body');
		var tabs = null;

		if (items.length > 1) {
			tabs = el('div', 'zodiac-popover-tabs');
			tabs.setAttribute('role', 'tablist');
			pop.appendChild(tabs);
		}

		function show(i) {
			body.textContent = '';
			String(items[i].text).split(/\n+/).forEach(function (para) {
				if (para.trim()) {
					body.appendChild(el('p', '', para.trim()));
				}
			});
			var meta = [items[i].note, items[i].source].filter(Boolean).join(' \u2013 ');
			if (meta) {
				body.appendChild(el('p', 'zodiac-popover-meta', meta));
			}
			if (tabs) {
				tabs.querySelectorAll('button').forEach(function (b, n) {
					b.setAttribute('aria-selected', n === i ? 'true' : 'false');
				});
			}
		}

		if (tabs) {
			items.forEach(function (item, i) {
				var b = el('button', '', item.title);
				b.type = 'button';
				b.setAttribute('role', 'tab');
				b.addEventListener('click', function () {
					show(i);
				});
				tabs.appendChild(b);
			});
		} else {
			pop.appendChild(el('h4', 'zodiac-popover-period', items[0].title));
		}

		pop.appendChild(body);
		show(0);
	}

	function open(btn) {
		var key = btn.getAttribute('data-zodiac-sign');
		if (pop && pop._btn === btn) {
			close(false);
			return;
		}
		close(false);
		if (!urls[key]) {
			return;
		}

		pop = el('div', 'zodiac-popover');
		pop.setAttribute('role', 'dialog');
		pop._btn = btn;
		pop.appendChild(el('p', 'zodiac-popover-loading', cfg.loading || '...'));
		document.body.appendChild(pop);
		btn.setAttribute('aria-expanded', 'true');
		place();

		var current = pop;
		var done = function (data) {
			if (pop !== current) {
				return;
			}
			render(data);
			place();
		};

		if (cache[key]) {
			done(cache[key]);
			return;
		}

		fetch(urls[key], {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
			.then(function (r) {
				if (!r.ok) {
					throw new Error(r.status);
				}
				return r.json();
			})
			.then(function (data) {
				cache[key] = data;
				done(data);
			})
			.catch(function () {
				if (pop === current) {
					pop.textContent = '';
					pop.appendChild(el('p', 'zodiac-popover-empty', cfg.error || 'Error'));
				}
			});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-zodiac-sign]') : null;
		if (btn) {
			e.preventDefault();
			open(btn);
			return;
		}
		if (pop && !pop.contains(e.target)) {
			close(false);
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && pop) {
			close(true);
		}
	});

	window.addEventListener('resize', place);

	/* ---------- Start ---------- */

	function init() {
		document.querySelectorAll('.zodiac-picker').forEach(initPicker);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
