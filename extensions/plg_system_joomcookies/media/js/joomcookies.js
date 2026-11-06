(function (window, document) {
  'use strict';

  if (window.JoomCookies && window.JoomCookies._attached) {
    return;
  }

  var STORAGE_KEY = 'joomcookies.consent';
  var root;
  var config;
  var banner;
  var panel;
  var overlay;
  var lastFocus = null;
  var loadedKeys = Object.create(null);

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function loadConfig() {
    var node = document.getElementById('joomcookies-config');
    if (!node) {
      return null;
    }
    try {
      return JSON.parse(node.textContent || '{}');
    } catch (e) {
      return null;
    }
  }

  function log() {
    if (config && config.ui && config.ui.diagnostic && window.console && console.info) {
      console.info.apply(console, arguments);
    }
  }

  function categoriesFrom(source) {
    return {
      necessary: true,
      statistics: !!(source && source.statistics),
      marketing: !!(source && source.marketing),
      embeds: !!(source && source.embeds)
    };
  }

  function encodeState(categories, version, timestamp) {
    var payload = JSON.stringify({
      v: version,
      t: timestamp || Math.floor(Date.now() / 1000),
      c: categoriesFrom(categories)
    });
    var base64 = btoa(unescape(encodeURIComponent(payload)));
    return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }

  function decodeState(raw) {
    if (!raw) {
      return null;
    }
    try {
      var json = raw;
      if (raw.charAt(0) !== '{') {
        var padded = raw.replace(/-/g, '+').replace(/_/g, '/');
        while (padded.length % 4) {
          padded += '=';
        }
        json = decodeURIComponent(escape(atob(padded)));
      }
      var data = JSON.parse(json);
      if (!data || !data.v || !data.c) {
        return null;
      }
      return {
        v: String(data.v),
        t: data.t ? parseInt(data.t, 10) : 0,
        c: categoriesFrom(data.c)
      };
    } catch (e) {
      return null;
    }
  }

  function cookieName() {
    return (config.cookie && config.cookie.name) || 'joomcookies_consent';
  }

  function readCookie() {
    var name = cookieName() + '=';
    var parts = document.cookie.split(';');
    for (var i = 0; i < parts.length; i += 1) {
      var part = parts[i].replace(/^\s+/, '');
      if (part.indexOf(name) === 0) {
        return decodeURIComponent(part.substring(name.length));
      }
    }
    return '';
  }

  function writeCookie(value, days) {
    var maxAge = Math.max(1, days || 180) * 86400;
    var path = (config.cookie && config.cookie.path) || '/';
    var cookie = cookieName() + '=' + encodeURIComponent(value) + '; Path=' + path + '; Max-Age=' + maxAge + '; SameSite=Lax';
    if (location.protocol === 'https:') {
      cookie += '; Secure';
    }
    document.cookie = cookie;
  }

  function persist(state) {
    var encoded = encodeState(state.c, state.v, state.t);
    writeCookie(encoded, config.cookie && config.cookie.days);
    try {
      localStorage.setItem(STORAGE_KEY, encoded);
    } catch (e) {
      // Privacy mode may block storage; cookie remains the primary record.
    }
  }

  function readState() {
    var cookieState = decodeState(readCookie());
    var stored = null;
    try {
      stored = decodeState(localStorage.getItem(STORAGE_KEY) || '');
    } catch (e) {
      stored = null;
    }
    var state = cookieState || stored;
    if (!state || state.v !== config.version) {
      return null;
    }
    return state;
  }

  function deleteCookies(names) {
    if (!names || !names.length) {
      return;
    }
    var path = (config.cookie && config.cookie.path) || '/';
    var host = location.hostname.replace(/^www\./, '');
    var domains = ['', location.hostname, '.' + host, host];
    names.forEach(function (name) {
      domains.forEach(function (domain) {
        var cookie = name + '=; Path=' + path + '; Max-Age=0; SameSite=Lax';
        if (domain) {
          cookie += '; Domain=' + domain;
        }
        document.cookie = cookie;
      });
    });
  }

  function optionalCookieNames() {
    var names = [];
    (config.services || []).forEach(function (service) {
      (service.cookies || []).forEach(function (name) {
        if (names.indexOf(name) === -1) {
          names.push(name);
        }
      });
    });
    return names;
  }

  function trapFocus(container, event) {
    if (event.key !== 'Tab') {
      return;
    }
    var nodes = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    var enabled = [];
    nodes.forEach(function (node) {
      if (!node.disabled && node.offsetParent !== null) {
        enabled.push(node);
      }
    });
    if (!enabled.length) {
      event.preventDefault();
      return;
    }
    var first = enabled[0];
    var last = enabled[enabled.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function setHidden(el, hidden) {
    if (!el) {
      return;
    }
    el.hidden = hidden;
    if (hidden) {
      el.setAttribute('hidden', 'hidden');
    } else {
      el.removeAttribute('hidden');
    }
  }

  function attachToBody() {
    if (root && document.body && root.parentNode !== document.body) {
      document.body.appendChild(root);
    }
  }

  function forwardPageScroll(event) {
    if (!banner || banner.hidden || (panel && !panel.hidden)) {
      return;
    }
    var canScrollSelf = banner.scrollHeight > banner.clientHeight + 2;
    if (canScrollSelf) {
      var down = event.deltaY > 0;
      var atBottom = banner.scrollTop + banner.clientHeight >= banner.scrollHeight - 1;
      var atTop = banner.scrollTop <= 0;
      if ((down && !atBottom) || (!down && !atTop)) {
        return;
      }
    }
    window.scrollBy(event.deltaX, event.deltaY);
  }

  function lockPageScroll(locked) {
    if (locked) {
      document.documentElement.classList.add('jck-prefs-open');
    } else {
      document.documentElement.classList.remove('jck-prefs-open');
    }
  }

  function openDialog(which) {
    attachToBody();
    lastFocus = document.activeElement;
    root.hidden = false;
    setHidden(banner, which !== 'banner');
    setHidden(panel, which !== 'panel');
    setHidden(overlay, which !== 'panel');
    lockPageScroll(which === 'panel');
    var target = which === 'panel' ? panel : banner;
    target.focus();
    var focusable = target.querySelector('button, [href], input');
    if (focusable) {
      focusable.focus();
    }
  }

  function closeDialogs(keepRoot) {
    setHidden(banner, true);
    setHidden(panel, true);
    setHidden(overlay, true);
    lockPageScroll(false);
    if (!keepRoot) {
      var reopen = root.querySelector('.jck-reopen');
      setHidden(root, !reopen);
    }
    if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus();
    }
  }

  function fillToggles(categories) {
    root.querySelectorAll('[data-jck-category-toggle]').forEach(function (input) {
      var id = input.getAttribute('data-jck-category-toggle');
      if (id === 'necessary') {
        input.checked = true;
        input.disabled = true;
        return;
      }
      input.checked = !!(categories && categories[id]);
    });
  }

  function readToggles() {
    var categories = categoriesFrom(null);
    root.querySelectorAll('[data-jck-category-toggle]').forEach(function (input) {
      var id = input.getAttribute('data-jck-category-toggle');
      if (id === 'necessary') {
        categories.necessary = true;
        return;
      }
      categories[id] = !!input.checked;
    });
    return categories;
  }

  function scriptKey(el) {
    return (el.getAttribute('data-jck-src') || '') + '|' + (el.getAttribute('data-jck-service') || '') + '|' + (el.textContent || '').slice(0, 80);
  }

  function activateScripts(categories) {
    document.querySelectorAll('script[type="text/plain"][data-jck-managed="1"]').forEach(function (el) {
      var category = el.getAttribute('data-jck-category');
      if (!categories[category]) {
        return;
      }
      var key = scriptKey(el);
      if (el.getAttribute('data-jck-loaded') === '1' || loadedKeys[key]) {
        return;
      }
      var script = document.createElement('script');
      var originalType = el.getAttribute('data-jck-type') || 'text/javascript';
      script.type = originalType;
      Array.prototype.forEach.call(el.attributes, function (attr) {
        if (attr.name === 'type' || attr.name.indexOf('data-jck') === 0) {
          return;
        }
        script.setAttribute(attr.name, attr.value);
      });
      var src = el.getAttribute('data-jck-src');
      if (src) {
        script.src = src;
      } else {
        script.text = el.textContent || '';
      }
      el.setAttribute('data-jck-loaded', '1');
      loadedKeys[key] = true;
      el.parentNode.insertBefore(script, el.nextSibling);
      log('JoomCookies: activated script', category, src || '[inline]');
    });
  }

  function iframeFromPayload(payload) {
    var iframe = document.createElement('iframe');
    iframe.src = payload.src;
    iframe.setAttribute('data-jck-managed', '1');
    iframe.setAttribute('title', payload.title || '');
    iframe.setAttribute('allowfullscreen', 'allowfullscreen');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    if (payload.attrs) {
      Object.keys(payload.attrs).forEach(function (name) {
        if (name.toLowerCase() === 'src') {
          return;
        }
        iframe.setAttribute(name, payload.attrs[name]);
      });
    }
    return iframe;
  }

  function activateEmbeds(categories, onlyNode) {
    var nodes = onlyNode ? [onlyNode] : document.querySelectorAll('[data-jck-embed="1"]');
    nodes.forEach(function (node) {
      var category = node.getAttribute('data-jck-category') || 'embeds';
      if (!onlyNode && !categories[category]) {
        return;
      }
      if (node.getAttribute('data-jck-loaded') === '1') {
        return;
      }
      var raw = node.getAttribute('data-jck-iframe');
      if (!raw) {
        return;
      }
      var payload;
      try {
        payload = JSON.parse(raw);
      } catch (e) {
        return;
      }
      if (!payload || !payload.src) {
        return;
      }
      var iframe = iframeFromPayload(payload);
      node.setAttribute('data-jck-loaded', '1');
      node.classList.add('jck-embed--active');
      node.innerHTML = '';
      node.appendChild(iframe);
      log('JoomCookies: activated embed', payload.service || category, payload.src);
    });
  }

  function applyConsent(categories, options) {
    options = options || {};
    var previous = readState();
    var downgrade = previous && (
      (previous.c.statistics && !categories.statistics) ||
      (previous.c.marketing && !categories.marketing) ||
      (previous.c.embeds && !categories.embeds)
    );

    persist({
      v: config.version,
      t: Math.floor(Date.now() / 1000),
      c: categories
    });

    document.dispatchEvent(new CustomEvent('joomcookies:change', { detail: { categories: categories, version: config.version } }));

    if (downgrade) {
      deleteCookies(optionalCookieNames());
      if (config.reloadOnRevoke !== false) {
        location.reload();
        return;
      }
    }

    activateScripts(categories);
    activateEmbeds(categories);
    closeDialogs(false);

    if (options.reload) {
      location.reload();
    }
  }

  function acceptAll() {
    applyConsent({
      necessary: true,
      statistics: true,
      marketing: true,
      embeds: true
    });
  }

  function rejectAll() {
    applyConsent({
      necessary: true,
      statistics: false,
      marketing: false,
      embeds: false
    });
  }

  function saveCustom() {
    applyConsent(readToggles());
  }

  function onKey(event) {
    if (event.key === 'Escape' && (!banner.hidden || !panel.hidden)) {
      var state = readState();
      if (state) {
        closeDialogs(false);
      }
    }
    if (!banner.hidden) {
      trapFocus(banner, event);
    }
    if (!panel.hidden) {
      trapFocus(panel, event);
    }
  }

  function bind() {
    root.addEventListener('click', function (event) {
      var action = event.target.closest('[data-jck-action]');
      if (action) {
        var name = action.getAttribute('data-jck-action');
        if (name === 'accept') {
          acceptAll();
        } else if (name === 'reject') {
          rejectAll();
        } else if (name === 'customize') {
          fillToggles((readState() && readState().c) || categoriesFrom(null));
          openDialog('panel');
        } else if (name === 'save') {
          saveCustom();
        } else if (name === 'close') {
          if (readState()) {
            closeDialogs(false);
          } else {
            openDialog('banner');
          }
        }
      }
      if (event.target.closest('[data-jck-open-prefs]')) {
        fillToggles((readState() && readState().c) || categoriesFrom(null));
        openDialog('panel');
      }
    });

    document.addEventListener('click', function (event) {
      var opener = event.target.closest('[data-jck-open-prefs]');
      if (opener && !root.contains(opener)) {
        fillToggles((readState() && readState().c) || categoriesFrom(null));
        openDialog('panel');
      }
      var loadBtn = event.target.closest('[data-jck-load-embed]');
      if (loadBtn) {
        activateEmbeds(categoriesFrom({ embeds: true }), loadBtn.closest('[data-jck-embed="1"]'));
      }
    });

    document.addEventListener('keydown', onKey);
  }

  function currentCategories() {
    var state = config ? readState() : null;
    return state ? state.c : categoriesFrom(null);
  }

  function openPreferences() {
    if (!root || !config) {
      start();
    }
    if (!root) {
      return;
    }
    fillToggles(currentCategories());
    openDialog('panel');
  }

  function start() {
    if (window.JoomCookies && window.JoomCookies._booted) {
      return;
    }
    root = document.querySelector('[data-jck-root="1"]');
    config = loadConfig();
    if (!root || !config || config.enabled === false || config.enabled === 0) {
      return;
    }
    attachToBody();
    banner = root.querySelector('[data-jck-banner]');
    panel = root.querySelector('[data-jck-panel]');
    overlay = root.querySelector('[data-jck-overlay]');
    bind();
    if (banner) {
      banner.addEventListener('wheel', forwardPageScroll, { passive: true });
    }
    window.JoomCookies._booted = true;

    var state = readState();
    if (!state) {
      fillToggles(categoriesFrom(null));
      openDialog('banner');
      document.dispatchEvent(new CustomEvent('joomcookies:ready', { detail: { categories: categoriesFrom(null), version: config.version } }));
      return;
    }

    activateScripts(state.c);
    activateEmbeds(state.c);
    closeDialogs(false);
    document.dispatchEvent(new CustomEvent('joomcookies:ready', { detail: { categories: state.c, version: config.version } }));
    log('JoomCookies: restored consent', state);
  }

  window.JoomCookies = {
    _attached: true,
    _booted: false,
    hasConsent: function (category) {
      if (!config) {
        config = loadConfig();
      }
      if (category === 'necessary') {
        return true;
      }
      return !!currentCategories()[category];
    },
    getConsent: function () {
      if (!config) {
        config = loadConfig();
      }
      return currentCategories();
    },
    openPreferences: openPreferences,
    version: function () {
      if (!config) {
        config = loadConfig();
      }
      return config && config.version ? String(config.version) : '';
    }
  };

  ready(start);
})(window, document);
