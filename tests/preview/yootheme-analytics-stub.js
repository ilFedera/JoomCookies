(function () {
  'use strict';
  function dataLayerPush() {
    var layer = window.dataLayer = window.dataLayer || [];
    layer.push(arguments);
  }
  function track(id, options) {
    window.__jckTest.loadCallbacks += 1;
    dataLayerPush('js', new Date());
    dataLayerPush('config', id, options || {});
    var node = document.createElement('script');
    node.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    node.async = true;
    window.__jckTest.gtagLoads += 1;
    document.head.appendChild(node);
  }
  (window.$load || (window.$load = [])).push(function (config, done) {
    track(config.google_analytics, { anonymize_ip: config.google_analytics_anonymize });
    if (typeof done === 'function') {
      done();
    }
  });
})();
