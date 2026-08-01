/* Theme Builder — minimal JS for level filter dropdown */
(function () {
	'use strict';

	function init() {
		var select = document.getElementById('itsi-tb-level-filter');
		if (!select) {
			return;
		}
		var baseUrl = select.getAttribute('data-base-url');
		if (!baseUrl) {
			return;
		}
		select.addEventListener('change', function () {
			var level = this.value;
			var url = baseUrl;
			// Preserve other params (size, view) via window.location.search parse.
			var params = new URLSearchParams(window.location.search);
			params.set('page', 'itsi-theme-builder');
			params.set('tab', 'tree');
			if (level) {
				params.set('level', level);
			} else {
				params.delete('level');
			}
			window.location.search = '?' + params.toString();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();