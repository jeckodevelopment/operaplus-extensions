(() => {
  'use strict';
  const prefix = 'Opera+ Image Optimizer: ';
  const j = window.Joomla;
  if (!j || typeof j.request !== 'function' || j.opImageFeedbackInstalled) return;
  j.opImageFeedbackInstalled = true;
  const request = j.request;
  function show(body, filename) {
    if (!body || body.success !== true || !body.data || body.data.type !== 'file') return;
    const options = j.getOptions('operaplus.imagefeedback', {});
    const entries = [];
    for (const [type, values] of Object.entries(body.messages || {})) {
      if (!Array.isArray(values)) continue;
      for (const value of values) {
        if (typeof value === 'string' && value.startsWith(prefix)) {
          entries.push([type, value]);
        }
      }
    }
    if (!entries.length) entries.push(['warning', prefix + (options.missing || 'No conversion result returned by the server.')]);
    let target = document.getElementById('op-image-feedback');
    if (!target) {
      target = document.createElement('section');
      target.id = 'op-image-feedback';
      target.setAttribute('aria-live', 'polite');
      const media = document.getElementById('com-media');
      if (!media) return;
      media.before(target);
    }
    for (const [type, message] of entries) {
      const alert = document.createElement('joomla-alert');
      alert.setAttribute('type', type === 'message' ? 'success' : type === 'notice' ? 'info' : 'warning');
      alert.setAttribute('dismiss', 'true');
      alert.setAttribute('close-text', j.Text._('JCLOSE') || 'Close');
      const content = document.createElement('div');
      content.className = 'alert-message';
      content.textContent = filename + ' → ' + (body.data.name || filename) + ': ' + message;
      alert.append(content);
      target.append(alert);
    }
  }
  j.request = function (options) {
    try {
      const u = new URL(options.url, location.href);
      if (u.origin !== location.origin || u.searchParams.get('option') !== 'com_media'
          || u.searchParams.get('task') !== 'api.files'
          || String(options.method).toUpperCase() !== 'POST' || options.promise
          || typeof options.data !== 'string') return request.call(this, options);
      const data = JSON.parse(options.data);
      if (!data.content || typeof data.name !== 'string') return request.call(this, options);
      const original = options.onSuccess;
      return request.call(this, { ...options, onSuccess: function(response, xhr) {
        try { show(JSON.parse(response), data.name); } catch (_) { /* Never interrupt native success. */ }
        if (typeof original === 'function') return original.call(this, response, xhr);
      } });
    } catch (_) { return request.call(this, options); }
  };
})();
