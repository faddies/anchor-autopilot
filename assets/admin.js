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
