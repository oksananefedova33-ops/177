// /ui/export-seo-lang/export-seo-lang.js
(function () {
  'use strict';

  // Коды языков для селекта и подписей
  const KNOWN_LANGS = [
    ['en','🇬🇧 English'], ['ru','🇷🇺 Русский'], ['de','🇩🇪 Deutsch'],
    ['fr','🇫🇷 Français'], ['es','🇪🇸 Español'], ['it','🇮🇹 Italiano'],
    ['pt','🇵🇹 Português'], ['pt-BR','🇧🇷 Português (Brasil)'],
    ['nl','🇳🇱 Nederlands'], ['pl','🇵🇱 Polski'], ['tr','🇹🇷 Türkçe'],
    ['uk','🇺🇦 Українська'], ['cs','🇨🇿 Čeština'], ['sv','🇸🇪 Svenska'],
    ['no','🇳🇴 Norsk'], ['fi','🇫🇮 Suomi'], ['da','🇩🇰 Dansk'],
    ['ro','🇷🇴 Română'], ['hu','🇭🇺 Magyar'], ['el','🇬🇷 Ελληνικά'],
    ['sk','🇸🇰 Slovenčina'], ['bg','🇧🇬 Български'], ['sr','🇷🇸 Српски'],
    ['hr','🇭🇷 Hrvatski'], ['sl','🇸🇮 Slovenščina'], ['et','🇪🇪 Eesti'],
    ['lv','🇱🇻 Latviešu'], ['lt','🇱🇹 Lietuvių'], ['zh','🇨🇳 中文'],
    ['zh-Hans','🇨🇳 中文（简体）'], ['zh-Hant','🇹🇼 中文（繁體）'],
    ['ja','🇯🇵 日本語'], ['ko','🇰🇷 한국어'], ['ar','🇸🇦 العربية'],
    ['hi','🇮🇳 हिन्दी'], ['id','🇮🇩 Bahasa Indonesia'], ['vi','🇻🇳 Tiếng Việt'],
  ];

  async function fetchProjectLangs() {
    try {
      const r = await fetch('/editor/export.php?action=langs', {cache:'no-store'});
      const j = await r.json();
      if (j && j.ok && Array.isArray(j.languages) && j.languages.length) return j.languages;
    } catch(e) {}
    return [];
  }

  function optionTags(selected) {
    return KNOWN_LANGS.map(([v, label]) =>
      `<option value="${v}" ${selected===v?'selected':''}>${label}</option>`
    ).join('');
  }

  async function inject() {
    const modal = document.querySelector('.xmodal');
    const dlg   = document.querySelector('.xmodal__dlg');
    const primaryLangSelect = document.getElementById('expLang');
    if (!dlg || !primaryLangSelect) return;

    // 1) Делаем сам диалог компактным и прокручиваемым
    try {
      if (modal) {
        modal.style.alignItems   = 'flex-start';
        modal.style.paddingTop   = '4vh';
        modal.style.paddingBottom= '4vh';
      }
      dlg.style.maxHeight = '88vh';
      dlg.style.overflowY = 'auto';
    } catch (e) {}

    // 2) Восстановим сохранённые настройки
    const projectLangs   = await fetchProjectLangs();
    const savedSeoPerLang= (localStorage.getItem('export_seo_per_lang') || '1') === '1';
    const savedSeoLang   = localStorage.getItem('export_seo_lang') || primaryLangSelect.value || 'ru';
    const savedSeoLangs  = (localStorage.getItem('export_seo_langs') || '').split(',').filter(Boolean);
    const selectedSet    = new Set(savedSeoLangs.length ? savedSeoLangs : (projectLangs.length ? projectLangs : [primaryLangSelect.value]));

    // 3) Рисуем компактный блок
    const anchorRow = primaryLangSelect.closest('.xmodal__row');
    const row = document.createElement('div');
    row.className = 'xmodal__row';
    row.innerHTML = `
      <style>
        .seo-compact *{ box-sizing:border-box; }
        .seo-compact .muted{ opacity:.65 }
        .seo-compact .note{ color:#8993a1; font-size:12px; margin-top:6px; }
        .seo-compact .head-line{ display:flex; flex-wrap:wrap; gap:10px 16px; align-items:center; }
        .seo-compact .head-line > .group{ display:flex; align-items:center; gap:8px; }
        .seo-compact select{ min-width:160px; }
        .seo-compact details{ margin-top:8px; border:1px dashed #2a3342; border-radius:8px; padding:8px 10px; background:rgba(255,255,255,.02); }
        .seo-compact details > summary{ cursor:pointer; user-select:none; outline:none; font-weight:600; }
        .seo-compact .seo-lang-grid{
          display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));
          gap:6px 12px; margin-top:8px; max-height:220px; overflow:auto; padding:4px;
        }
        .seo-compact .seo-lang-chip{ display:flex; align-items:center; gap:6px; white-space:nowrap; }
        .seo-compact .toolbar{ display:flex; gap:8px; margin-top:8px; flex-wrap:wrap; }
        .seo-compact .toolbar button{ padding:4px 8px; font-size:12px; line-height:16px; }
      </style>

      <div class="seo-compact">
        <div class="head-line">
          <label class="xcheck" title="Рекомендовано для мультиязычных сайтов">
            <input type="checkbox" id="expSeoPerLang" ${savedSeoPerLang?'checked':''}>
            <span>SEO по языку страницы (<code>&lt;html lang&gt;</code>, <code>meta[name=language]</code>, <code>og:locale</code>, <code>JSON‑LD inLanguage</code>)</span>
          </label>
          <div class="group">
            <span>Единый SEO‑язык:</span>
            <select id="expSeoLang" ${savedSeoPerLang?'disabled class="muted"':''}>
              ${optionTags(savedSeoLang)}
            </select>
          </div>
        </div>

        <details id="expSeoLangsBlock">
          <summary>Языки для SEO‑навигации (<code>hreflang</code>, <code>sitemap.xml</code>)</summary>
          <div class="toolbar">
            <button type="button" id="seoSelectAll">Все</button>
            <button type="button" id="seoSelectNone">Снять всё</button>
            <button type="button" id="seoSelectPrimary">Только основной + English</button>
          </div>
          <div id="seoLangChips" class="seo-lang-grid"></div>
          <div class="note">Если языка нет в проекте — он будет пропущен автоматически.</div>
        </details>
      </div>
    `;
    anchorRow.insertAdjacentElement('afterend', row);

    // 4) Заполняем чекбоксы
    const chipBox = row.querySelector('#seoLangChips');
    const listForChips = projectLangs.length ? projectLangs : KNOWN_LANGS.map(x => x[0]);
    chipBox.innerHTML = listForChips.map(v => {
      const label = (KNOWN_LANGS.find(x => x[0]===v) || [v, v])[1];
      return `<label class="xcheck seo-lang-chip">
        <input type="checkbox" value="${v}" ${selectedSet.has(v)?'checked':''}>
        <span>${label}</span></label>`;
    }).join('');

    // На высоких экранах — разворачиваем по умолчанию
    const details = row.querySelector('#expSeoLangsBlock');
    if (window.innerHeight > 820) details.setAttribute('open','');

    // 5) Обработчики
    const perLangChk = row.querySelector('#expSeoPerLang');
    const seoLangSel = row.querySelector('#expSeoLang');
    perLangChk.addEventListener('change', () => {
      const on = perLangChk.checked;
      seoLangSel.disabled = on;
      seoLangSel.classList.toggle('muted', on);
      localStorage.setItem('export_seo_per_lang', on ? '1' : '0');
    });
    seoLangSel.addEventListener('change', () => {
      localStorage.setItem('export_seo_lang', seoLangSel.value);
    });

    row.querySelector('#seoSelectAll').addEventListener('click', () => {
      chipBox.querySelectorAll('input[type=checkbox]').forEach(i => i.checked = true);
    });
    row.querySelector('#seoSelectNone').addEventListener('click', () => {
      chipBox.querySelectorAll('input[type=checkbox]').forEach(i => i.checked = false);
    });
    row.querySelector('#seoSelectPrimary').addEventListener('click', () => {
      const prim = primaryLangSelect.value || 'en';
      chipBox.querySelectorAll('input[type=checkbox]').forEach(i => {
        i.checked = (i.value === prim || i.value === 'en');
      });
    });

    // 6) Сбор параметров для export.php
    window.__collectExportParams = function () {
      const chosen = Array.from(chipBox.querySelectorAll('input[type=checkbox]:checked')).map(x => x.value);
      localStorage.setItem('export_seo_langs', chosen.join(','));
      return {
        seo_per_lang: perLangChk.checked ? '1' : '0',
        seo_lang: seoLangSel.value,
        seo_langs: chosen.join(',')
      };
    };

    // На всякий случай прокручиваем начало модалки
    setTimeout(() => { dlg.scrollTop = 0; }, 0);
  }

  // Перехват открытия модалки экспорта, чтобы вшить наш блок
  function patchOpenExportModal() {
    const orig = window.openExportModal;
    if (!orig) return;
    window.openExportModal = function () {
      orig.apply(this, arguments);
      setTimeout(inject, 0);
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', patchOpenExportModal);
  } else {
    patchOpenExportModal();
  }
})();


