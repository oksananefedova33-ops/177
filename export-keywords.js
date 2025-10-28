// /ui/export-keywords/export-keywords.js
(function () {
  'use strict';

  function injectKeywordsRow() {
    const dlg = document.querySelector('.xmodal__dlg');
    const primaryLangSelect = document.getElementById('expLang');
    if (!dlg || !primaryLangSelect) return;

    const savedKeywords    = localStorage.getItem('export_meta_keywords') || '';
    const savedBreadcrumbs = localStorage.getItem('export_breadcrumbs') ?? '1';

    const anchorRow = primaryLangSelect.closest('.xmodal__row');
    const row = document.createElement('div');
    row.className = 'xmodal__row';
    row.innerHTML = `
      <div style="display:flex; flex-direction:column; gap:8px; width:100%">
        <label>Meta keywords / author (необязательно):</label>
        <textarea id="expKeywords" rows="2" placeholder="keyword1, keyword2, ...">${savedKeywords}</textarea>
        <label class="xcheck">
          <input type="checkbox" id="expBreadcrumbs" ${savedBreadcrumbs==='0'?'':'checked'}>
          <span>Добавить BreadcrumbList Schema (JSON‑LD)</span>
        </label>
        <div class="note">Ключи попадут в &lt;meta name="keywords"&gt; и &lt;meta name="author"&gt;.</div>
      </div>
    `;
    anchorRow.insertAdjacentElement('afterend', row);
  }

  function patchOpenExportModal() {
    const orig = window.openExportModal;
    if (!orig) return;
    window.openExportModal = function () {
      orig.apply(this, arguments);
      setTimeout(function () {
        injectKeywordsRow();

        const goBtn = document.getElementById('expGo');
        if (!goBtn) return;

        goBtn.onclick = function () {
          const d = (document.getElementById('expDomain').value || '').trim();
          const https = document.getElementById('expHttps').checked;
          const www = (document.querySelector('input[name="expWWW"]:checked') || {}).value || 'keep';
          const force = document.getElementById('expForce').checked;
          const lang = document.getElementById('expLang').value;

          const keywords = (document.getElementById('expKeywords').value || '').trim();
          const breadcrumbs = document.getElementById('expBreadcrumbs').checked ? '1' : '0';

          // SEO‑параметры собираем из модуля export-seo-lang (если он подключён)
          const seoParams = (typeof window.__collectExportParams === 'function')
            ? window.__collectExportParams()
            : { seo_lang: lang, seo_per_lang: '1', seo_langs: lang };

          localStorage.setItem('export_domain', d);
          localStorage.setItem('export_https', https ? '1' : '0');
          localStorage.setItem('export_www_mode', www);
          localStorage.setItem('export_force_host', force ? '1' : '0');
          localStorage.setItem('export_primary_lang', lang);
          localStorage.setItem('export_meta_keywords', keywords);
          localStorage.setItem('export_breadcrumbs', breadcrumbs);

          const params = {
            action: 'export',
            domain: d,
            https: String(https),
            www_mode: www,
            force_host: String(force),
            primary_lang: lang,
            meta_keywords: keywords,
            breadcrumbs: breadcrumbs,
            // из модуля SEO:
            seo_lang: seoParams.seo_lang,
            seo_per_lang: seoParams.seo_per_lang,
            seo_langs: seoParams.seo_langs
          };

          const qs = new URLSearchParams(params);
          window.location.href = '/editor/export.php?' + qs.toString();
          document.querySelector('.xmodal')?.remove();
        };
      }, 0);
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', patchOpenExportModal);
  } else {
    patchOpenExportModal();
  }
})();

