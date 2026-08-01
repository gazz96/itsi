/*!
 * ITSI Theme – Front-end interactions.
 * Vanilla JS, no dependencies.
 */
(function () {
  'use strict';

  // ── Navbar scroll state ─────────────────────────────────
  var nb       = document.getElementById('navbar');
  var stButton = document.getElementById('scrollTop');

  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    if (nb) nb.classList.toggle('scrolled', y > 60);
    if (stButton) stButton.classList.toggle('show', y > 400);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // ── Scroll to top ───────────────────────────────────────
  if (stButton) {
    stButton.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ── Mobile menu toggle ──────────────────────────────────
  var mobNav     = document.getElementById('mobNav');
  var hamburger  = document.getElementById('hamburger');

  window.toggleMob = function () {
    if (!mobNav || !hamburger) return;
    var open = mobNav.classList.toggle('open');
    hamburger.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  };
  window.closeMob = function () {
    if (!mobNav || !hamburger) return;
    mobNav.classList.remove('open');
    hamburger.classList.remove('open');
    document.body.style.overflow = '';
  };

  // ── Search overlay ──────────────────────────────────────
  var searchOvl = document.getElementById('searchOvl');
  var searchInp = document.getElementById('searchInp');

  window.toggleSearch = function (open) {
    if (!searchOvl) return;
    var show = typeof open === 'boolean'
      ? open
      : !searchOvl.classList.contains('open');
    searchOvl.classList.toggle('open', show);
    document.body.style.overflow = show ? 'hidden' : '';
    if (show && searchInp) {
      setTimeout(function () { searchInp.focus(); }, 50);
    }
  };

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (searchOvl && searchOvl.classList.contains('open')) {
        window.toggleSearch(false);
      } else if (mobNav && mobNav.classList.contains('open')) {
        window.closeMob();
      }
    }
  });

  // ── Reveal-on-scroll ────────────────────────────────────
  var rvEls = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window && rvEls.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('on');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    rvEls.forEach(function (el) { io.observe(el); });
  } else {
    // Fallback: just show everything.
    rvEls.forEach(function (el) { el.classList.add('on'); });
  }

  // ── Animated counters ───────────────────────────────────
  function animateCount(el, target, suffix) {
    var dur = 1600;
    var start = performance.now();
    function step(now) {
      var t = Math.min(1, (now - start) / dur);
      var eased = 1 - Math.pow(1 - t, 3);
      var v = Math.floor(eased * target);
      el.textContent = v + (suffix || '');
      if (t < 1) requestAnimationFrame(step);
      else el.textContent = target + (suffix || '');
    }
    requestAnimationFrame(step);
  }

  var counters = document.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window && counters.length) {
    var cIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target;
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        animateCount(el, target, suffix);
        cIo.unobserve(el);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { cIo.observe(el); });
  }

  // ── Prodi tabs ──────────────────────────────────────────
  window.switchProdi = function (panelId, btn) {
    document.querySelectorAll('.prodi-panel').forEach(function (p) {
      p.classList.remove('on');
    });
    document.querySelectorAll('.ptab').forEach(function (b) {
      b.classList.remove('on');
    });
    var panel = document.getElementById(panelId);
    if (!panel) {
      // Backward compatibility: legacy ids prefixed with "prodi-".
      var legacy = document.getElementById('prodi-' + panelId);
      if (legacy) legacy.classList.add('on');
    } else {
      panel.classList.add('on');
    }
    if (btn) btn.classList.add('on');
  };

  // ── News filter chips (toggle active + swap data-cat-view blocks) ─
  window.filterChip = function (btn) {
    var wrap = btn.parentElement;
    if (!wrap) return;
    wrap.querySelectorAll('.chip').forEach(function (c) {
      c.classList.remove('on');
      c.setAttribute('aria-selected', 'false');
    });
    btn.classList.add('on');
    btn.setAttribute('aria-selected', 'true');

    var cat = btn.getAttribute('data-cat') || 'all';
    // Find the closest containing section so multiple Berita sections don't collide.
    var section = btn.closest('section');
    if (!section) return;
    var views = section.querySelectorAll('[data-cat-view]');
    if (!views.length) { return; } // cosmetic-only when no views (e.g. legacy markup)
    views.forEach(function (v) {
      v.style.display = v.getAttribute('data-cat-view') === cat ? '' : 'none';
    });
  };

  // ── Share / copy link helpers ───────────────────────────
  window.copyLink = function (e, url) {
    if (e && e.stopPropagation) e.stopPropagation();
    url = url || window.location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () {
        flashTooltip('Tautan disalin');
      });
    } else {
      var t = document.createElement('textarea');
      t.value = url; document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); flashTooltip('Tautan disalin'); } catch (_) {}
      document.body.removeChild(t);
    }
  };
  window.doShare = function (network, e) {
    if (e && e.stopPropagation) e.stopPropagation();
    var url = encodeURIComponent(window.location.href);
    var text = encodeURIComponent(document.title);
    var target = '';
    if (network === 'wa')  target = 'https://wa.me/?text=' + text + '%20' + url;
    if (network === 'ig')  target = 'https://www.instagram.com/';
    if (network === 'fb')  target = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
    if (network === 'tw')  target = 'https://twitter.com/intent/tweet?text=' + text + '&url=' + url;
    if (target) window.open(target, '_blank', 'noopener');
  };

  function flashTooltip(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);background:var(--ink);color:#fff;padding:.7rem 1.2rem;border-radius:10px;font-size:.85rem;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.4);';
    document.body.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 1400);
    setTimeout(function () { t.remove(); }, 1800);
  }
})();
  // ── Pengumuman / Artikel card click navigation ───────────────
  document.addEventListener('click', function (e) {
    var card = e.target.closest && e.target.closest('.peng[data-href], .art-card[data-href]');
    if (!card) return;
    if (e.target.closest('.peng-btn, .peng-actions, .share-btn, .art-img-cat')) return; // let buttons / badges act independently
    if (e.button !== 0) return;                                // ignore middle/right click
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; // ignore modifier keys
    window.location.href = card.dataset.href;
  });
  // ── Flash tooltip / toast (global) ──────────────────────
  window.flashTooltip = function (msg, type) {
    var t = document.createElement('div');
    t.className = 'itsi-toast' + (type ? ' ' + type : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('show'); }, 10);
    setTimeout(function () { t.classList.remove('show'); }, 2800);
    setTimeout(function () { t.remove(); }, 3300);
  };

  // ── Arc-archive filter chips (kat / kategori) ──────────
  window.filterKat = function (kat, el) {
    var wrap = el ? el.parentElement : document;
    if (wrap) {
      wrap.querySelectorAll('.arc-chip').forEach(function (c) { c.classList.remove('on'); });
    }
    if (el) el.classList.add('on');
    var all = (el ? el.closest('.arc-section, .ann-section, .kip-section') : document)
      .querySelectorAll('[data-cat]');
    all.forEach(function (card) {
      var cardKat = card.getAttribute('data-cat');
      if (!kat || kat === 'all' || cardKat === kat) {
        card.classList.remove('hide');
        card.style.display = '';
      } else {
        card.classList.add('hide');
        card.style.display = 'none';
      }
    });
  };

  // ── Arc-archive filter for dokumen (kip cards data-kat) ─
  window.filterDok = function (kat, el) {
    var wrap = el ? el.parentElement : document;
    if (wrap) {
      wrap.querySelectorAll('.arc-chip').forEach(function (c) { c.classList.remove('on'); });
    }
    if (el) el.classList.add('on');
    var all = (el ? el.closest('.arc-section, .kip-section') : document)
      .querySelectorAll('[data-kat]');
    all.forEach(function (card) {
      var cardKat = card.getAttribute('data-kat');
      if (!kat || kat === 'all' || cardKat === kat) {
        card.classList.remove('hide');
        card.style.display = '';
      } else {
        card.classList.add('hide');
        card.style.display = 'none';
      }
    });
  };

  // ── Simple client-side search ───────────────────────────
  window.doSearch = function (q) {
    q = (q || '').toLowerCase().trim();
    var items = document.querySelectorAll('.arc-section [data-searchable], .ann-list-arc [data-searchable]');
    items.forEach(function (card) {
      var text = (card.textContent || '').toLowerCase();
      var show = !q || text.indexOf(q) > -1;
      card.classList.toggle('hide', !show);
      card.style.display = show ? '' : 'none';
    });
  };

  // Search forms (arc-search-inp)
  document.querySelectorAll('.arc-search-inp').forEach(function (inp) {
    var form = inp.closest('form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      window.doSearch(inp.value);
    });
    inp.addEventListener('input', function () {
      if (inp.value.length === 0 || inp.value.length >= 2) {
        window.doSearch(inp.value);
      }
    });
  });

  // ── Program-studi 8-panel tabs ──────────────────────────
  window.switchPanel = function (id, btn) {
    var host = btn ? btn.closest('.prodi-single, .site-main') : document;
    if (host) {
      host.querySelectorAll('.pp').forEach(function (p) { p.classList.remove('on'); });
      host.querySelectorAll('.ptab-8').forEach(function (b) { b.classList.remove('on'); });
    }
    var panel = document.getElementById('pp-' + id);
    if (panel) panel.classList.add('on');
    if (btn) btn.classList.add('on');
  };

  // ── Mata-kuliah semester tabs ───────────────────────────
  window.switchMk = function (sem, btn) {
    var host = btn ? btn.closest('.pp') : document;
    if (host) {
      host.querySelectorAll('.mk-pane').forEach(function (p) { p.classList.remove('on'); });
      host.querySelectorAll('.mk-tab').forEach(function (b) { b.classList.remove('on'); });
    }
    var pane = document.getElementById('mk-' + sem);
    if (pane) pane.classList.add('on');
    if (btn) btn.classList.add('on');
  };

  // ── Dosen filter (by data-bid) ──────────────────────────
  window.filterDosen = function (bid, el) {
    var wrap = el ? el.parentElement : document;
    if (wrap) {
      wrap.querySelectorAll('.arc-chip').forEach(function (c) { c.classList.remove('on'); });
    }
    if (el) el.classList.add('on');
    var cards = document.querySelectorAll('.dosen-card[data-bid]');
    cards.forEach(function (card) {
      var cb = card.getAttribute('data-bid');
      if (!bid || bid === 'all' || cb === bid) {
        card.classList.remove('hide');
        card.style.display = '';
      } else {
        card.classList.add('hide');
        card.style.display = 'none';
      }
    });
  };

  // ── TOC generator (single post) ─────────────────────────
  function buildTOC() {
    var list = document.getElementById('tocList');
    var content = document.querySelector('.single-content');
    if (!list || !content) return;
    var headings = content.querySelectorAll('h2, h3');
    if (headings.length === 0) {
      list.innerHTML = '<li class="toc-empty">Tidak ada sub-bab</li>';
      return;
    }
    var html = '';
    headings.forEach(function (h, i) {
      if (!h.id) h.id = 'toc-h-' + (i + 1);
      html += '<li data-target="' + h.id + '">' + (h.textContent || '').trim() + '</li>';
    });
    list.innerHTML = html;
    list.querySelectorAll('li[data-target]').forEach(function (li) {
      li.addEventListener('click', function () {
        var t = document.getElementById(li.getAttribute('data-target'));
        if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    // Scrollspy
    if ('IntersectionObserver' in window) {
      var tocIo = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            var id = e.target.id;
            list.querySelectorAll('li').forEach(function (li) { li.classList.remove('active'); });
            var active = list.querySelector('li[data-target="' + id + '"]');
            if (active) active.classList.add('active');
          }
        });
      }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });
      headings.forEach(function (h) { tocIo.observe(h); });
    }
  }
  buildTOC();

  // ── Share buttons (single post) ─────────────────────────
  window.doShare2 = window.doShare; // alias
  window.doShare = function (network, e) {
    if (e && e.preventDefault) e.preventDefault();
    if (e && e.stopPropagation) e.stopPropagation();
    var url = encodeURIComponent(window.location.href);
    var text = encodeURIComponent(document.title || '');
    var target = '';
    if (network === 'wa')  target = 'https://wa.me/?text=' + text + '%20' + url;
    if (network === 'fb')  target = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
    if (network === 'tw')  target = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + text;
    if (network === 'li')  target = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url;
    if (target) window.open(target, '_blank', 'noopener,noreferrer');
  };

  // ── Form permohonan informasi (AJAX) ────────────────────
  var formEl = document.getElementById('formPermohonan');
  if (formEl) {
    var submitBtn = document.getElementById('formSubmitBtn');
    formEl.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (!window.itsiAjax) {
        flashTooltip('Konfigurasi AJAX tidak ditemukan', 'error');
        return;
      }
      // Reset invalid styles
      formEl.querySelectorAll('.field-inp').forEach(function (i) { i.classList.remove('is-invalid'); });
      // Native validation
      if (!formEl.checkValidity()) {
        formEl.querySelectorAll(':invalid').forEach(function (i) { if (i.classList) i.classList.add('is-invalid'); });
        formEl.reportValidity();
        return;
      }
      var fd = new FormData(formEl);
      fd.append('action', 'itsi_submit_permohonan');
      fd.append('nonce', window.itsiAjax.nonce);
      if (submitBtn) {
        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;
      }
      fetch(window.itsiAjax.url, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (resp && resp.success) {
          flashTooltip(resp.data && resp.data.message ? resp.data.message : 'Permohonan terkirim!', 'success');
          formEl.reset();
        } else {
          var err = resp && resp.data && resp.data.message ? resp.data.message : 'Terjadi kesalahan, coba lagi.';
          flashTooltip(err, 'error');
        }
      })
      .catch(function () {
        flashTooltip('Tidak dapat terhubung ke server', 'error');
      })
      .then(function () {
        if (submitBtn) {
          submitBtn.classList.remove('is-loading');
          submitBtn.disabled = false;
        }
      });
    });
  }
