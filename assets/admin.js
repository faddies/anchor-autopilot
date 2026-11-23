(function () {
	'use strict';
	var i18n = window.aaI18n || {};

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		// Rule form: show the From field only for page-specific rules.
		var scope = document.querySelectorAll('.aa-form input[name="scope"]');
		var from = document.querySelector('.aa-form .aa-from');
		function syncScope() {
			if (!from) { return; }
			var page = false;
			for (var i = 0; i < scope.length; i++) { if (scope[i].checked && scope[i].value === 'page') { page = true; } }
			from.style.display = page ? '' : 'none';
		}
		for (var i = 0; i < scope.length; i++) { scope[i].addEventListener('change', syncScope); }
		syncScope();

		// Confirm single deletes.
		document.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('a.aa-delete') : null;
			if (a && !window.confirm(i18n.confirmDelete || 'Delete?')) { e.preventDefault(); }
		});

		// Confirm "delete existing first" on import.
		var rep = document.querySelectorAll('.aa-replace');
		for (var r = 0; r < rep.length; r++) {
			rep[r].addEventListener('change', function () {
				if (this.checked && !window.confirm(i18n.confirmReplace || 'Continue?')) { this.checked = false; }
			});
		}

		// Link report: chunked site scan with progress.
		var scanBtn = document.getElementById('aa-scan-btn');
		if (scanBtn && window.aaScan) {
			var statusEl = document.getElementById('aa-scan-status');
			var prog = document.getElementById('aa-progress');
			var bar = document.getElementById('aa-progress-bar');
			var call = function (step, cb) {
				var body = 'action=aa_scan&nonce=' + encodeURIComponent(aaScan.nonce) + '&step=' + step;
				var x = new XMLHttpRequest();
				x.open('POST', aaScan.ajax, true);
				x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				x.onload = function () {
					var j = null;
					try { j = JSON.parse(x.responseText); } catch (e) { j = null; }
					if (x.status === 200 && j && j.success) { cb(j.data); } else { fail(); }
				};
				x.onerror = fail;
				x.send(body);
			};
			var fail = function () {
				statusEl.textContent = aaScan.failed;
				scanBtn.disabled = false;
			};
			var text = function (d, t) {
				return aaScan.scanning.replace('%1$d', d).replace('%2$d', t);
			};
			var loop = function (total) {
				call('chunk', function (d) {
					var pct = total ? Math.min(100, Math.round(d.done * 100 / total)) : 100;
					bar.style.width = pct + '%';
					statusEl.textContent = text(d.done, total);
					if (d.finished) { window.location.href = window.location.pathname + '?page=aa-report'; } else { loop(total); }
				});
			};
			scanBtn.addEventListener('click', function () {
				scanBtn.disabled = true;
				prog.hidden = false;
				bar.style.width = '0%';
				statusEl.textContent = '…';
				call('start', function (d) {
					if (d.finished) { window.location.href = window.location.pathname + '?page=aa-report'; return; }
					loop(d.total);
				});
			});
		}

		// Test screen: switch between post and pasted HTML.
		var src = document.querySelectorAll('.aa-test-form input[name="source"]');
		var boxPost = document.querySelector('.aa-src-post');
		var boxHtml = document.querySelector('.aa-src-html');
		function syncSrc() {
			if (!boxPost || !boxHtml) { return; }
			var html = false;
			for (var k = 0; k < src.length; k++) { if (src[k].checked && src[k].value === 'html') { html = true; } }
			boxPost.style.display = html ? 'none' : '';
			boxHtml.style.display = html ? '' : 'none';
		}
		for (var s = 0; s < src.length; s++) { src[s].addEventListener('change', syncSrc); }
		syncSrc();
	});
})();
