(function () {
  'use strict';

  if (window.JoomCookiesScan && window.JoomCookiesScan._attached) {
    return;
  }

  window.JoomCookiesScan = { _attached: true, looping: false };

  var SCAN_TASKS = ['scan.start', 'scan.batch', 'scan.pause', 'scan.resume', 'scan.cancel'];
  var inFlight = false;
  var looping = false;
  var paused = false;
  var timer = null;
  var tickTimer = null;
  var elapsedBase = 0;
  var elapsedAt = 0;

  function $(id) {
    return document.getElementById(id);
  }

  function runForm() {
    return $('jck-scan-run');
  }

  function showError(message) {
    var box = $('jck-scan-error');
    if (!box) {
      return;
    }
    box.hidden = !message;
    box.textContent = message || '';
  }

  function setText(id, value) {
    var el = $(id);
    if (el) {
      el.textContent = String(value);
    }
  }

  function taskOf(form) {
    var field = form && form.querySelector ? form.querySelector('[name="task"]') : null;
    return field ? String(field.value || '') : '';
  }

  function tokenFields(from) {
    var data = new FormData(from);
    var out = new FormData();
    data.forEach(function (value, key) {
      if (key === 'task' || key === 'scan_mode' || key === 'discovery' || key === 'start_url' || key === 'scan_id' || value === '1') {
        out.set(key, value);
      }
    });
    return out;
  }

  function mergeTokens() {
    var data = new FormData();
    ['jck-scan-run', 'jck-scan-continue', 'jck-scan-pause-form', 'jck-scan-resume-form', 'jck-scan-cancel-form'].forEach(function (id) {
      var form = $(id);
      if (!form) {
        return;
      }
      tokenFields(form).forEach(function (value, key) {
        if (!data.has(key) || key === 'scan_id') {
          data.set(key, value);
        }
      });
    });
    return data;
  }

  function endpoint() {
    var form = runForm() || $('jck-scan-continue');
    return (form && form.getAttribute('action')) || 'index.php?option=com_joomcookies';
  }

  function post(task) {
    var data = mergeTokens();
    data.set('task', task);
    if (task === 'scan.start') {
      var start = $('start_url');
      var mode = $('scan_mode');
      if (start) {
        data.set('start_url', start.value);
      }
      if (mode) {
        data.set('scan_mode', mode.value);
      }
      var discovery = $('discovery');
      if (discovery) {
        data.set('discovery', discovery.value);
      }
    }
    return fetch(endpoint(), {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Cache-Control': 'no-cache'
      }
    }).then(function (response) {
      var type = response.headers.get('content-type') || '';
      if (response.status === 403) {
        throw new Error((runForm() && runForm().getAttribute('data-error-http')) || 'Session expired.');
      }
      if (type.indexOf('json') === -1) {
        throw new Error((runForm() && runForm().getAttribute('data-error-http')) || 'Scan request failed.');
      }
      return response.json();
    }).then(function (payload) {
      if (!payload || payload.ok === false) {
        throw new Error((payload && payload.error) || 'Scan request failed.');
      }
      return payload;
    });
  }

  function applyButtons(status) {
    var startBtn = $('jck-scan-start');
    var pauseBtn = $('jck-scan-pause');
    var resumeBtn = $('jck-scan-resume');
    var cancelBtn = $('jck-scan-cancel');
    if (startBtn) {
      startBtn.disabled = status === 'running';
    }
    if (pauseBtn) {
      pauseBtn.disabled = status !== 'running';
    }
    if (resumeBtn) {
      resumeBtn.disabled = status !== 'paused';
    }
    if (cancelBtn) {
      cancelBtn.disabled = status !== 'running' && status !== 'paused';
    }
  }

  function startTick(elapsed) {
    elapsedBase = parseInt(elapsed, 10) || 0;
    elapsedAt = Date.now();
    if (tickTimer) {
      window.clearInterval(tickTimer);
    }
    tickTimer = window.setInterval(function () {
      if (!looping) {
        return;
      }
      setText('jck-scan-elapsed', (elapsedBase + Math.floor((Date.now() - elapsedAt) / 1000)) + 's');
    }, 1000);
  }

  function render(payload) {
    showError('');
    var progress = payload.progress || {};
    var status = progress.status || payload.status || '';
    var percent = status === 'complete' ? 100 : (progress.percent_final || progress.percent || 0);
    var bar = $('jck-scan-progress');
    if (bar) {
      bar.style.width = percent + '%';
      bar.setAttribute('aria-valuenow', String(percent));
    }
    setText('jck-scan-status', status);
    setText('jck-scan-discovered', progress.discovered || 0);
    setText('jck-scan-pages', progress.pages || 0);
    setText('jck-scan-pending', progress.pending || 0);
    setText('jck-scan-errors', progress.errors || 0);
    setText('jck-scan-elapsed', (progress.elapsed_active || progress.elapsed || 0) + 's');
    setText('jck-scan-percent', (progress.estimated ? '~' : '') + percent + '%');
    var reasonEl = $('jck-scan-complete-reason');
    if (reasonEl) {
      var reason = progress.complete_reason || '';
      reasonEl.textContent = reason === 'page_cap'
        ? 'Completed because the page limit was reached.'
        : (reason === 'queue_empty' ? 'Completed: the queue is empty.' : '');
    }
    if ($('jck-scan-findings')) {
      setText('jck-scan-findings', progress.findings || 0);
    }
    var scanId = payload.scan_id;
    if (scanId) {
      document.querySelectorAll('[name="scan_id"]').forEach(function (el) {
        el.value = String(scanId);
      });
    }
    applyButtons(status);
    if (status === 'running') {
      startTick(progress.elapsed_active || progress.elapsed || 0);
    }
    return status;
  }

  function stopLoop() {
    looping = false;
    window.JoomCookiesScan.looping = false;
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
  }

  function schedule() {
    if (!looping || paused || inFlight) {
      return;
    }
    timer = window.setTimeout(runBatch, 400);
  }

  function runBatch() {
    if (!looping || paused || inFlight) {
      return;
    }
    inFlight = true;
    post('scan.batch').then(function (payload) {
      inFlight = false;
      if (payload.busy) {
        timer = window.setTimeout(runBatch, payload.retry_ms || 2000);
        return;
      }
      var status = render(payload);
      if (status === 'running' && looping && !paused) {
        schedule();
        return;
      }
      stopLoop();
      if (status === 'complete') {
        window.location.reload();
      }
    }).catch(function (err) {
      inFlight = false;
      stopLoop();
      showError(err && err.message ? err.message : 'Scan request failed.');
    });
  }

  function beginLoop(payload) {
    var status = render(payload);
    paused = status === 'paused';
    if (status === 'running') {
      looping = true;
      window.JoomCookiesScan.looping = true;
      schedule();
    } else {
      stopLoop();
      if (status === 'complete') {
        window.location.reload();
      }
    }
  }

  function runTask(task) {
    if (task === 'scan.pause') {
      paused = true;
      stopLoop();
    }
    if (task === 'scan.start' || task === 'scan.resume') {
      paused = false;
      stopLoop();
    }
    if (task === 'scan.cancel') {
      paused = true;
      stopLoop();
    }
    if (inFlight && task !== 'scan.pause' && task !== 'scan.cancel') {
      return;
    }
    inFlight = true;
    post(task).then(function (payload) {
      inFlight = false;
      if (task === 'scan.start' || task === 'scan.resume' || task === 'scan.batch') {
        beginLoop(payload);
        return;
      }
      render(payload);
    }).catch(function (err) {
      inFlight = false;
      showError(err && err.message ? err.message : 'Scan request failed.');
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    var task = taskOf(form);
    if (SCAN_TASKS.indexOf(task) === -1) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    runTask(task);
  }, true);

  function boot() {
    var status = (($('jck-scan-status') && $('jck-scan-status').textContent) || '').trim();
    applyButtons(status);
    if (status === 'running') {
      looping = true;
      window.JoomCookiesScan.looping = true;
      schedule();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.JoomCookiesScan.isLooping = function () {
    return looping;
  };
})();
