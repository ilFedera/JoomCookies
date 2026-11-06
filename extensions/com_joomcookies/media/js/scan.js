(function () {
  'use strict';

  var form = document.getElementById('jck-scan-run');
  var bar = document.getElementById('jck-scan-progress');
  if (!form || !bar) {
    return;
  }

  var running = false;
  var timer = null;

  function field(id) {
    return document.getElementById(id);
  }

  function setText(id, value) {
    var el = field(id);
    if (el) {
      el.textContent = String(value);
    }
  }

  function post(task) {
    var data = new FormData(form);
    data.set('task', task);
    return fetch(form.getAttribute('action') || 'index.php?option=com_joomcookies', {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return response.json();
    });
  }

  function render(payload) {
    var progress = payload.progress || {};
    var status = progress.status || payload.status || '';
    var percent = status === 'complete' ? 100 : (progress.percent_final || progress.percent || 0);
    bar.style.width = percent + '%';
    bar.setAttribute('aria-valuenow', String(percent));
    setText('jck-scan-status', status);
    setText('jck-scan-discovered', progress.discovered || 0);
    setText('jck-scan-pages', progress.pages || 0);
    setText('jck-scan-pending', progress.pending || 0);
    setText('jck-scan-errors', progress.errors || 0);
    setText('jck-scan-elapsed', (progress.elapsed || 0) + 's');
    setText('jck-scan-percent', (progress.estimated ? '~' : '') + percent + '%');
    if (payload.scan_id) {
      form.querySelector('[name="scan_id"]').value = payload.scan_id;
    }
    running = status === 'running';
    field('jck-scan-start').disabled = running;
    field('jck-scan-pause').disabled = !running;
    field('jck-scan-resume').disabled = status !== 'paused';
    field('jck-scan-cancel').disabled = status !== 'running' && status !== 'paused';
    if (status === 'complete' || status === 'cancelled') {
      stopTimer();
    }
  }

  function loop() {
    if (!running) {
      return;
    }
    post('scan.batch').then(function (payload) {
      render(payload);
      if (payload.status === 'running' || (payload.progress && payload.progress.status === 'running')) {
        timer = window.setTimeout(loop, 250);
      }
    }).catch(function () {
      stopTimer();
    });
  }

  function stopTimer() {
    running = false;
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    stopTimer();
    post('scan.start').then(function (payload) {
      render(payload);
      if (payload.status === 'running' || (payload.progress && payload.progress.status === 'running')) {
        running = true;
        loop();
      }
    });
  });

  field('jck-scan-pause').addEventListener('click', function () {
    post('scan.pause').then(render);
    stopTimer();
  });
  field('jck-scan-resume').addEventListener('click', function () {
    post('scan.resume').then(function (payload) {
      render(payload);
      running = true;
      loop();
    });
  });
  field('jck-scan-cancel').addEventListener('click', function () {
    post('scan.cancel').then(render);
    stopTimer();
  });
})();
