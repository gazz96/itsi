/**
 * ITSI Theme — jQuery 3.x compatibility shim (admin).
 *
 * TypeRocket v6 (page builder) is built against an old jQuery API surface.
 * Under WordPress 6.9.1 (jQuery 3.7.1 + jquery-migrate 3.4.1) several
 * deprecated static APIs trigger `QMIGRATE: ... is deprecated` warnings
 * (and, if jquery-migrate is disabled, would throw outright).
 *
 * This file is loaded from the THEME (not the plugin) so the TypeRocket
 * plugin stays pristine. It:
 *   1. Mutes jquery-migrate's console noise (warnings + traces).
 *   2. Re-implements the deprecated static APIs with clean equivalents.
 *
 * Load order is guaranteed by enqueuing with the `jquery` dependency:
 *   jquery-core → jquery-migrate → typerocket-compat → (footer: core.js)
 * Because this file loads AFTER jquery-migrate, our definitions win and
 * no migrate warning is emitted for them.
 *
 * NOTE: We only touch static APIs (`$.fn.*` jQuery-UI plugins are left alone).
 */
(function () {
	'use strict';

	var $ = window.jQuery;

	// jquery-migrate (or jQuery itself) not available — nothing to patch.
	if (typeof $ !== 'function') {
		return;
	}

	// ── 1) Mute jquery-migrate warnings & stack traces ────────────────
	// Set after jquery-migrate has loaded (enqueue order guarantees this).
	try {
		if ($.migrateMute !== true) { $.migrateMute = true; }
		if ($.migrateTrace !== false) { $.migrateTrace = false; }
	} catch (e) { /* ignore */ }

	// ── 2) Clean re-implementations of deprecated static APIs ──────────
	// These overwrite jquery-migrate's warn-wrapped shims, so the console
	// stays clean while behaviour is preserved (and they also work when
	// jquery-migrate is not loaded at all).

	// jQuery.isFunction() → typeof check (removed in jQuery 3.3+).
	$.isFunction = function (obj) {
		return typeof obj === 'function';
	};

	// jQuery.trim() → String#trim with jQuery's unicode/whitespace set.
	$.trim = function (text) {
		return text == null ? '' : String(text).replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
	};

	// jQuery.parseJSON() → native JSON.parse.
	$.parseJSON = function (data) {
		return JSON.parse(data);
	};

	// jQuery.now() → Date.now.
	$.now = function () {
		return Date.now();
	};

	// jQuery.type() → reliable type detection (mirrors jQuery's class2type).
	var class2type = {};
	'Boolean Number String Function Array Date RegExp Object Error Symbol'
		.split(' ')
		.forEach(function (name) {
			class2type['[object ' + name + ']'] = name.toLowerCase();
		});

	$.type = function (obj) {
		if (obj == null) {
			return obj + '';
		}
		return typeof obj === 'object' || typeof obj === 'function'
			? class2type[Object.prototype.toString.call(obj)] || 'object'
			: typeof obj;
	};
})();
