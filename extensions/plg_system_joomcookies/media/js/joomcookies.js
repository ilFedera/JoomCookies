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
  var scriptActivationRunning = false;
  var scriptActivationPending = false;
  var youtubeBusy = false;
  var youtubeQueued = null;
  var youtubeObserver = null;

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

  function analyticsCookieNames() {
    var found = [];
    var raw = document.cookie ? document.cookie.split(';') : [];
    for (var i = 0; i < raw.length; i += 1) {
      var name = raw[i].split('=')[0].trim();
      if (!name) {
        continue;
      }
      if (name === '_ga' || name === '_gid' || name === '_gat' || name.indexOf('_ga_') === 0 || name.indexOf('_gcl_') === 0) {
        if (found.indexOf(name) === -1) {
          found.push(name);
        }
      }
    }
    return found;
  }

  function isGoogleAnalyticsNetwork(src) {
    var value = String(src || '').toLowerCase();
    if (!value) {
      return false;
    }
    if (value.indexOf('recaptcha') !== -1 || value.indexOf('maps.googleapis') !== -1 || value.indexOf('google.com/maps') !== -1) {
      return false;
    }
    return value.indexOf('theme-analytics/app/analytics') !== -1
      || value.indexOf('googletagmanager.com/gtag/js') !== -1
      || value.indexOf('googletagmanager.com/gtm.js') !== -1
      || value.indexOf('google-analytics.com') !== -1;
  }

  function pushGtag() {
    window.dataLayer = window.dataLayer || [];
    if (typeof window.gtag !== 'function') {
      window.gtag = function () {
        window.dataLayer.push(arguments);
      };
    }
    window.gtag.apply(null, arguments);
  }

  function updateGoogleConsentMode(categories) {
    try {
      pushGtag('consent', 'update', {
        analytics_storage: categories.statistics ? 'granted' : 'denied',
        ad_storage: categories.marketing ? 'granted' : 'denied',
        ad_user_data: categories.marketing ? 'granted' : 'denied',
        ad_personalization: categories.marketing ? 'granted' : 'denied'
      });
    } catch (e) {
      log('JoomCookies: consent mode update skipped', e);
    }
  }

  function adaptYoothemeLoadQueue() {
    if (typeof window.$load !== 'function' || window.$load.__jckAdapted) {
      return;
    }
    var run = window.$load;
    if (typeof run.push === 'function') {
      return;
    }
    // Preserve the original callable API, including existing properties.
    var proxy = function () {
      return run.apply(this, arguments);
    };
    Object.keys(run).forEach(function (key) {
      proxy[key] = run[key];
    });
    proxy.__jckAdapted = true;
    proxy.push = function (fn) {
      try {
        return run(fn);
      } catch (err) {
        log('JoomCookies: YOOtheme $load callback skipped', err);
        return 0;
      }
    };
    window.$load = proxy;
  }

  function stripUnauthorizedAnalytics(root) {
    if (!root || currentCategories().statistics) {
      return;
    }
    var scripts = [];
    if (root.tagName === 'SCRIPT') {
      scripts = [root];
    } else if (root.querySelectorAll) {
      scripts = root.querySelectorAll('script[src]');
    }
    for (var i = 0; i < scripts.length; i += 1) {
      var el = scripts[i];
      if (el.getAttribute('data-jck-managed') === '1' && (el.getAttribute('type') || '') === 'text/plain') {
        continue;
      }
      var src = el.getAttribute('src') || el.src || '';
      if (!isGoogleAnalyticsNetwork(src)) {
        continue;
      }
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }
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
    if (categories.statistics) {
      adaptYoothemeLoadQueue();
    }
    if (scriptActivationRunning) {
      scriptActivationPending = true;
      return;
    }
    scriptActivationRunning = true;
    var nodes = Array.prototype.slice.call(document.querySelectorAll('script[type="text/plain"][data-jck-managed="1"]'));
    var index = 0;

    function advance() {
      while (index < nodes.length) {
        var el = nodes[index++];
        var category = el.getAttribute('data-jck-category');
        if (!currentCategories()[category]) {
          continue;
        }
        var key = scriptKey(el);
        if (el.getAttribute('data-jck-loaded') === '1' || loadedKeys[key] || !el.parentNode) {
          continue;
        }
        var script = document.createElement('script');
        script.type = el.getAttribute('data-jck-type') || 'text/javascript';
        Array.prototype.forEach.call(el.attributes, function (attr) {
          if (attr.name === 'type' || attr.name === 'async' || attr.name === 'defer' || attr.name.indexOf('data-jck') === 0) {
            return;
          }
          script.setAttribute(attr.name, attr.value);
        });
        var src = el.getAttribute('data-jck-src');
        if (src) {
          // Dynamic scripts are async by default; load dependencies in document order.
          script.async = false;
          script.onload = advance;
          script.onerror = function () {
            log('JoomCookies: managed script failed to load', this.src);
            advance();
          };
          script.src = src;
        } else {
          script.text = el.textContent || '';
        }
        el.setAttribute('data-jck-loaded', '1');
        loadedKeys[key] = true;
        el.parentNode.insertBefore(script, el.nextSibling);
        log('JoomCookies: activated script', category, src || '[inline]');
        if (src) {
          return;
        }
      }
      scriptActivationRunning = false;
      if (scriptActivationPending) {
        scriptActivationPending = false;
        activateScripts(currentCategories());
      }
    }
    advance();
  }

  function withoutAutoplay(src) {
    src = String(src || '');
    try {
      var url = new URL(src, document.baseURI || location.href);
      url.searchParams.delete('autoplay');
      url.searchParams.set('autoplay', '0');
      return url.toString();
    } catch (e) {
      return src.replace(/([?&])autoplay=1/gi, '$1autoplay=0');
    }
  }

  function iframeFromPayload(payload) {
    var iframe = document.createElement('iframe');
    iframe.src = withoutAutoplay(payload.src);
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

  function parseEmbedPayload(node) {
    var raw = node.getAttribute('data-jck-iframe');
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function isYoutubeEmbed(node) {
    return node.getAttribute('data-jck-service') === 'youtube' || node.hasAttribute('data-jck-youtube');
  }

  function youtubeTexts() {
    var texts = (config && config.texts) || {};
    return {
      title: texts.youtubeBlocked || 'Video YouTube bloccato',
      help: texts.youtubeHelp || 'Attiva i contenuti esterni per visualizzarlo.',
      prefs: texts.youtubePrefs || 'Gestisci preferenze',
      load: texts.youtubeLoad || 'Carica il video'
    };
  }

  function youtubeIdFromValue(value) {
    value = (value || '').trim();
    if (/^[A-Za-z0-9_-]{11}$/.test(value)) {
      return value;
    }
    var match = value.match(/[?&]v=([A-Za-z0-9_-]{11})/)
      || value.match(/youtu\.be\/([A-Za-z0-9_-]{11})/)
      || value.match(/\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})/);
    return match ? match[1] : '';
  }

  function youtubePlayerSrc(videoId) {
    var host = config && config.youtube && config.youtube.nocookie === false
      ? 'https://www.youtube.com'
      : 'https://www.youtube-nocookie.com';
    return host + '/embed/' + encodeURIComponent(videoId);
  }

  function localThumbnailUrl(videoId) {
    var endpoint = config && config.youtube && config.youtube.thumbnailEndpoint;
    if (!endpoint) {
      return '';
    }
    return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent(videoId);
  }

  function renderYoutubePlaceholder(node, videoId, title) {
    var texts = youtubeTexts();
    var payload = {
      src: youtubePlayerSrc(videoId),
      title: title || texts.title,
      attrs: {},
      category: 'embeds',
      service: 'youtube',
      videoId: videoId
    };
    node.setAttribute('data-jck-embed', '1');
    node.setAttribute('data-jck-youtube', videoId);
    node.setAttribute('data-jck-category', 'embeds');
    node.setAttribute('data-jck-service', 'youtube');
    node.setAttribute('data-jck-iframe', JSON.stringify(payload));
    node.classList.add('jck-embed', 'jck-embed--youtube');
    node.style.setProperty('--jck-ratio', '16 / 9');
    node.setAttribute('role', 'group');
    node.setAttribute('aria-label', title || texts.title);
    var thumbnail = localThumbnailUrl(videoId);
    node.innerHTML = ''
      + '<div class="jck-embed__surface"><div class="jck-embed__panel">'
      + (thumbnail ? '<img class="jck-embed__thumbnail" src="' + thumbnail + '" alt="" loading="lazy" decoding="async">' : '')
      + '<button type="button" class="jck-youtube-play" data-jck-load-embed="1" aria-label="Riproduci video YouTube"><span aria-hidden="true"></span></button>'
      + '<p class="jck-embed__title">' + (title || texts.title) + '</p>'
      + '<p class="jck-embed__text">Attiva i cookie YouTube per riprodurre il video</p>'
      + '<div class="jck-embed__actions"></div></div></div>';
    refreshYoutubeActions(node);
  }

  function cfYoutubeTitle(card) {
    var title = card.querySelector('.el-title, h1, h2, h3, h4, h5, h6');
    return title ? title.textContent.trim() : '';
  }

  function enhanceCfYoutubeCards(rootNode) {
    var scope = rootNode && rootNode.querySelectorAll ? rootNode : document;
    var cards = [];
    if (rootNode && rootNode.nodeType === 1 && rootNode.classList && rootNode.classList.contains('cf-youtube')) { cards.push(rootNode); }
    if (scope.querySelectorAll) { scope.querySelectorAll('.cf-youtube[data-video-id]').forEach(function (card) { cards.push(card); }); }
    cards.forEach(function (card) {
      var videoId = youtubeIdFromValue(card.getAttribute('data-video-id') || '');
      if (!videoId) { return; }
      var media = null;
      Array.prototype.forEach.call(card.children || [], function (child) {
        if (!media && child.classList && child.classList.contains('jck-cf-youtube__media')) { media = child; }
      });
      if (!media) {
        media = document.createElement('div');
        media.className = 'jck-cf-youtube__media';
        card.insertBefore(media, card.firstChild);
      }
      media.setAttribute('data-jck-manual', '1');
      if (!media.hasAttribute('data-jck-youtube')) { renderYoutubePlaceholder(media, videoId, cfYoutubeTitle(card)); }
      else { refreshYoutubeActions(media); }
    });
  }

  function isOwnYoutubeNode(node) {
    if (!node || node.nodeType !== 1) {
      return true;
    }
    if (node.id === 'joomcookies-root' || (node.closest && node.closest('#joomcookies-root, .jck-embed, .jck-root'))) {
      return true;
    }
    var className = node.className && node.className.toString ? node.className.toString() : '';
    return className.indexOf('jck-') === 0;
  }

  function refreshYoutubeActions(node) {
    if (!isYoutubeEmbed(node) || node.getAttribute('data-jck-loaded') === '1') {
      return;
    }
    var actions = node.querySelector('.jck-embed__actions');
    if (!actions) {
      return;
    }
    actions.innerHTML = '';
  }

  function enhanceYoutubePlayers(rootNode) {
    if (youtubeBusy) {
      youtubeQueued = rootNode || document;
      return;
    }
    youtubeBusy = true;
    if (youtubeObserver) {
      youtubeObserver.disconnect();
      youtubeObserver.takeRecords();
    }
    try {
      var scope = rootNode && rootNode.querySelectorAll ? rootNode : document;
      var hosts = [];
      if (rootNode && rootNode.nodeType === 1 && rootNode.classList && rootNode.classList.contains('youtube-player')) {
        hosts.push(rootNode);
      }
      if (scope.querySelectorAll) {
        scope.querySelectorAll('.youtube-player').forEach(function (node) {
          hosts.push(node);
        });
      }
      hosts.forEach(function (host) {
        if (host.getAttribute('data-jck-yt') === '1') {
          var nested = host.querySelector('[data-jck-youtube]');
          refreshYoutubeActions(nested || host);
          return;
        }
        if (host.hasAttribute('data-jck-youtube')) {
          refreshYoutubeActions(host);
          return;
        }
        var videoId = youtubeIdFromValue(host.getAttribute('data-id') || '');
        if (!videoId) {
          return;
        }
        host.setAttribute('data-jck-yt', '1');
        renderYoutubePlaceholder(host, videoId);
      });
      enhanceCfYoutubeCards(rootNode || document);
      if (currentCategories().embeds) {
        hosts.forEach(function (host) {
          loadYoutubeIfAllowed(host);
        });
      }
    } catch (e) {
      log('JoomCookies: YouTube enhance skipped', e);
    } finally {
      youtubeBusy = false;
      if (youtubeObserver && document.documentElement) {
        youtubeObserver.observe(document.documentElement, { childList: true, subtree: true });
      }
      if (youtubeQueued) {
        var queued = youtubeQueued;
        youtubeQueued = null;
        enhanceYoutubePlayers(queued);
      }
    }
  }

  function observeDynamicContent() {
    if (youtubeObserver || !window.MutationObserver) {
      return;
    }
    youtubeObserver = new MutationObserver(function (mutations) {
      if (youtubeBusy) {
        return;
      }
      var pending = [];
      for (var i = 0; i < mutations.length; i += 1) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j += 1) {
          var node = added[j];
          if (node.nodeType !== 1 || isOwnYoutubeNode(node)) {
            continue;
          }
          stripUnauthorizedAnalytics(node);
          if ((node.classList && (node.classList.contains('youtube-player') || node.classList.contains('cf-youtube'))) || (node.querySelector && node.querySelector('.youtube-player, .cf-youtube'))) {
            pending.push(node);
          }
        }
      }
      if (!pending.length) {
        return;
      }
      pending.forEach(function (node) {
        enhanceYoutubePlayers(node);
      });
    });
    youtubeObserver.observe(document.documentElement, { childList: true, subtree: true });
    if (window.JoomCookies) {
      window.JoomCookies._observer = youtubeObserver;
    }
  }

  function loadYoutubeIfAllowed(node) {
    if (!node || !currentCategories().embeds) {
      return;
    }
    var target = node.hasAttribute('data-jck-embed') ? node : node.querySelector('[data-jck-embed="1"]');
    if (target && isYoutubeEmbed(target)) {
      loadEmbed(target);
    }
  }

  function loadEmbed(node) {
    if (!node) {
      return;
    }
    var category = node.getAttribute('data-jck-category') || 'embeds';
    if (!currentCategories()[category]) {
      openPreferences(category);
      return;
    }
    if (node.getAttribute('data-jck-loaded') === '1') {
      return;
    }
    var payload = parseEmbedPayload(node);
    if (!payload || !payload.src) {
      return;
    }
    var iframe = iframeFromPayload(payload);
    node.setAttribute('data-jck-loaded', '1');
    node.classList.add('jck-embed--active');
    node.innerHTML = '';
    node.appendChild(iframe);
    log('JoomCookies: activated embed', payload.service || node.getAttribute('data-jck-category'), payload.src);
  }

  function activateEmbeds(categories, onlyNode) {
    var nodes = onlyNode ? [onlyNode] : document.querySelectorAll('[data-jck-embed="1"]');
    nodes.forEach(function (node) {
      var category = node.getAttribute('data-jck-category') || 'embeds';
      if (!categories[category] || node.getAttribute('data-jck-manual') === '1') {
        return;
      }
      loadEmbed(node);
    });
  }

  function stopManagedPlayers() {
    document.querySelectorAll('[data-jck-embed="1"][data-jck-loaded="1"]').forEach(function (node) {
      node.querySelectorAll('iframe').forEach(function (frame) {
        frame.removeAttribute('src');
        if (frame.parentNode) {
          frame.parentNode.removeChild(frame);
        }
      });
      node.removeAttribute('data-jck-loaded');
      node.classList.remove('jck-embed--active');
      if (isYoutubeEmbed(node)) {
        var payload = parseEmbedPayload(node);
        var videoId = node.getAttribute('data-jck-youtube') || (payload && payload.videoId) || '';
        if (videoId) {
          renderYoutubePlaceholder(node, videoId, (payload && payload.title) || '');
        }
      }
    });
    enhanceYoutubePlayers(document);
  }

  function syncConsentHints(categories) {
    document.querySelectorAll('[data-jck-consent-hint]').forEach(function (node) {
      var category = node.getAttribute('data-jck-consent-hint');
      node.hidden = !!(categories && categories[category]);
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
    updateGoogleConsentMode(categories);

    if (downgrade) {
      var cookieNames = optionalCookieNames();
      if (previous.c.statistics && !categories.statistics) {
        cookieNames = cookieNames.concat(analyticsCookieNames());
      }
      deleteCookies(cookieNames);
      stopManagedPlayers();
      if (config.reloadOnRevoke !== false) {
        location.reload();
        return;
      }
    }

    activateScripts(categories);
    enhanceYoutubePlayers(document);
    activateEmbeds(categories);
    syncConsentHints(categories);
    debugSnapshot('consent-applied');
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
        event.preventDefault();
        loadEmbed(loadBtn.closest('[data-jck-embed="1"]'));
      }
    });

    document.addEventListener('keydown', onKey);
  }

  function currentCategories() {
    var state = config ? readState() : null;
    return state ? state.c : categoriesFrom(null);
  }

  function assetVersion() {
    var node = document.getElementById('joomcookies-runtime') || document.querySelector('script[src*="joomcookies.js"]');
    var src = node && node.getAttribute('src') ? node.getAttribute('src') : '';
    var match = src.match(/[?&]v=([^&]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  }

  function debugSnapshot(label) {
    if (!config || !config.ui || !config.ui.diagnostic) {
      return;
    }
    log('JoomCookies:', label, {
      asset: assetVersion(),
      consent: currentCategories(),
      blocked: document.querySelectorAll('[data-jck-embed="1"]:not([data-jck-loaded="1"])').length,
      unlocked: document.querySelectorAll('iframe[data-jck-managed="1"]').length
    });
  }

  function openPreferences(category) {
    if (!root || !config) {
      start();
    }
    if (!root) {
      return;
    }
    fillToggles(currentCategories());
    openDialog('panel');
    if (category) {
      var input = root.querySelector('[data-jck-category-toggle="' + String(category).replace(/[^a-z]/g, '') + '"]');
      if (input) { input.focus(); }
    }
  }

  function start() {
    if (window.JoomCookies && window.JoomCookies._booted) {
      return;
    }
    try {
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
      enhanceYoutubePlayers(document);
      observeDynamicContent();

      var state = readState();
      if (!state) {
        fillToggles(categoriesFrom(null));
        updateGoogleConsentMode(categoriesFrom(null));
        openDialog('banner');
        document.dispatchEvent(new CustomEvent('joomcookies:ready', { detail: { categories: categoriesFrom(null), version: config.version } }));
        return;
      }

      updateGoogleConsentMode(state.c);
      activateScripts(state.c);
      enhanceYoutubePlayers(document);
      activateEmbeds(state.c);
      syncConsentHints(state.c);
      closeDialogs(false);
      document.dispatchEvent(new CustomEvent('joomcookies:ready', { detail: { categories: state.c, version: config.version } }));
      debugSnapshot('consent-restored');
    } catch (e) {
      if (window.console && console.error) {
        console.error('JoomCookies: frontend init skipped', e);
      }
    }
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
