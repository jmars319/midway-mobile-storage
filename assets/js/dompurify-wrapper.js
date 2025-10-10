// dompurify-wrapper.js
// Ensure a consistent `window.DOMPurify.sanitize` API is available.
// Prefer the vendor-provided DOMPurify (if included as dompurify.min.js)
// otherwise provide a minimal safe fallback that escapes HTML.
(function(){
  function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
  if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
    try { window.DOMPurify.isFallback = false; } catch (e) { /* ignore */ }
    return;
  }
  // Provide a minimal sanitizer fallback. This is intentionally conservative:
  // it escapes markup rather than attempting to whitelist tags. For production,
  // include the official DOMPurify build instead. Mark the fallback so calling
  // code can special-case line-break handling if required.
  window.DOMPurify = {
    sanitize: function(input){
      try { return escapeHtml(String(input)); } catch(e) { return ''; }
    },
    isFallback: true
  };
})();
