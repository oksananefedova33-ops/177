/*
 * Модуль "Языки" — кнопка в верхней панели + плашка на сцене.
 * Работает автономно, без правок core-редактора.
 */
(function(){
  // Перехват функции gatherElementData
  const originalGatherElementData = window.gatherElementData;
  if (typeof originalGatherElementData === 'function') {
    window.gatherElementData = function(el) {
      const result = originalGatherElementData.call(this, el);
      if (el.dataset.type === 'langbadge') {
  result.langs = el.dataset.langs || '';
  result.label = el.dataset.label || 'Языки';
  result.badgeColor = el.dataset.badgeColor || '';
}
      return result;
    };
  }

  // Перехват функции createElement
  const originalCreateElement = window.createElement;
  if (typeof originalCreateElement === 'function') {
    window.createElement = function(type, opts = {}) {
      if (type === 'langbadge') {
  const el = document.createElement('div');
  el.className = 'el langbadge';
  el.dataset.type = 'langbadge';
  el.dataset.id = opts.id || ('el_' + Math.random().toString(36).slice(2,9));
  el.dataset.langs = opts.langs || '';
  el.dataset.label = opts.label || 'Языки';
  el.dataset.badgeColor = opts.badgeColor || (opts.color || '');

    el.style.left   = (opts.left ?? 10) + '%';
  el.style.top    = (opts.top  ?? 10) + 'px';
  // дефолты в PX (как у картинка/видео/блок); проценты — только если пришли из сохранения
  if (opts.width == null) {
    el.style.width  = (opts.pxWidth  ?? 220) + 'px';
  } else {
    el.style.width  = opts.width + '%';
  }
  if (opts.height == null) {
    el.style.height = (opts.pxHeight ?? 40) + 'px';
  } else {
    el.style.height = opts.height + '%';
  }
  el.style.zIndex = (opts.z ?? 2);
  el.style.borderRadius = (opts.radius ?? 8) + 'px';

  const chipStyle = el.dataset.badgeColor
  ? ` style="background:${el.dataset.badgeColor};border:1px solid ${el.dataset.badgeColor};color:#fff"`
  : '';

  el.innerHTML = `
    <div class="langbadge__wrap">
      <button type="button" class="lang-chip"${chipStyle}>🌐 ${opts.label || 'Языки'}</button>
      <div class="lang-dropdown"></div>
    </div>
  `;

  const stage = document.querySelector('#stage');
  if (stage) stage.appendChild(el);

  if (typeof window.ensureTools === 'function') window.ensureTools(el);
  if (typeof window.ensureHandle === 'function') window.ensureHandle(el);
  if (typeof window.attachDragResize === 'function') window.attachDragResize(el);

  return el;
}

      return originalCreateElement.call(this, type, opts);
    };
  }

  const onReady = fn => (document.readyState==='loading' ? document.addEventListener('DOMContentLoaded',fn) : fn());
  const uid = ()=> 'el_'+Math.random().toString(36).slice(2,9);
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  // Список языков (полный список DeepL)
const LANGS = [
  {code:'ru',      name:'🇷🇺 Русский',     flag:'🇷🇺'},
  {code:'en',      name:'🇬🇧 English',     flag:'🇬🇧'},
  {code:'zh-Hans', name:'🇨🇳 中文',        flag:'🇨🇳'},
  {code:'es',      name:'🇪🇸 Español',     flag:'🇪🇸'},
  {code:'fr',      name:'🇫🇷 Français',    flag:'🇫🇷'},
  {code:'de',      name:'🇩🇪 Deutsch',     flag:'🇩🇪'},
  {code:'it',      name:'🇮🇹 Italiano',    flag:'🇮🇹'},
  {code:'pt',      name:'🇵🇹 Português',   flag:'🇵🇹'},
  {code:'ja',      name:'🇯🇵 日本語',       flag:'🇯🇵'},
  {code:'ko',      name:'🇰🇷 한국어',       flag:'🇰🇷'},
  {code:'nl',      name:'🇳🇱 Nederlands',  flag:'🇳🇱'},
  {code:'pl',      name:'🇵🇱 Polski',      flag:'🇵🇱'},
  {code:'tr',      name:'🇹🇷 Türkçe',      flag:'🇹🇷'},
  {code:'ar',      name:'🇸🇦 العربية',     flag:'🇸🇦'},
  {code:'cs',      name:'🇨🇿 Čeština',     flag:'🇨🇿'},
  {code:'da',      name:'🇩🇰 Dansk',       flag:'🇩🇰'},
  {code:'el',      name:'🇬🇷 Ελληνικά',    flag:'🇬🇷'},
  {code:'fi',      name:'🇫🇮 Suomi',       flag:'🇫🇮'},
  {code:'hu',      name:'🇭🇺 Magyar',      flag:'🇭🇺'},
  {code:'id',      name:'🇮🇩 Indonesia',   flag:'🇮🇩'},
  {code:'no',      name:'🇳🇴 Norsk',       flag:'🇳🇴'},
  {code:'ro',      name:'🇷🇴 Română',      flag:'🇷🇴'},
  {code:'sv',      name:'🇸🇪 Svenska',     flag:'🇸🇪'},
  {code:'uk',      name:'🇺🇦 Українська',  flag:'🇺🇦'},
  {code:'bg',      name:'🇧🇬 Български',   flag:'🇧🇬'},
  {code:'et',      name:'🇪🇪 Eesti',       flag:'🇪🇪'},
  {code:'lt',      name:'🇱🇹 Lietuvių',    flag:'🇱🇹'},
  {code:'lv',      name:'🇱🇻 Latviešu',    flag:'🇱🇻'},
  {code:'sk',      name:'🇸🇰 Slovenčina',  flag:'🇸🇰'},
  {code:'sl',      name:'🇸🇮 Slovenščina', flag:'🇸🇮'}
];
  const nameByCode = c => (LANGS.find(x=>x.code===c)?.name || c);

  /* ---------------- Кнопка в тулбаре ---------------- */
  function insertToolbarBtn(){
    const topbar = document.querySelector('.topbar');
    if(!topbar || $('#btnLangs')) return;
    const refBtn = Array.from(topbar.querySelectorAll('button')).find(b=>/Экспорт|Export/i.test(b.textContent||''));
    const btn = document.createElement('button');
    btn.type='button'; btn.id='btnLangs'; btn.className='btn'; btn.textContent='Языки';
    btn.addEventListener('click', openModal);
    if(refBtn && refBtn.parentNode){
      refBtn.parentNode.insertBefore(btn, refBtn.nextSibling);
    } else {
      topbar.appendChild(btn);
    }
  }

  /* ---------------- Модальное окно ---------------- */
  function buildModal(){
  if($('#langsModalBackdrop')) return;
  const backdrop = document.createElement('div');
  backdrop.id='langsModalBackdrop';
  backdrop.className='langs-backdrop hidden';
  const modal = document.createElement('div');
  modal.className='langs-modal';
  modal.innerHTML = `
    <div class="langs-modal__header">
      <div class="langs-modal__title">Выбор языков</div>
      <button type="button" class="langs-close" aria-label="Закрыть">×</button>
    </div>
    <div class="langs-modal__body">
      <div class="langs-color">
        <span>Цвет плашки:</span>
        <input type="color" id="langBadgeColor" value="#2ea8ff" />
      </div>
      <label class="langs-checkall"><input type="checkbox" id="langsSelectAll"> Выбрать все</label>
      <div class="langs-list" style="max-height: 400px; overflow-y: auto;">
       ${LANGS.map(l=>`
  <label class="langs-item">
    <input type="checkbox" class="langs-chk" value="${l.code}">
    <span class="langs-name">${l.name}</span>
  </label>
`).join('')}
      </div>
    </div>
    <div class="langs-modal__footer">
      <button type="button" class="btn langs-ok">Применить</button>
      <button type="button" class="btn langs-cancel">Отмена</button>
    </div>
  `;
  backdrop.appendChild(modal);
  document.body.appendChild(backdrop);

  const close = ()=> backdrop.classList.add('hidden');
  modal.querySelector('.langs-close').addEventListener('click', close);
  modal.querySelector('.langs-cancel').addEventListener('click', close);
  backdrop.addEventListener('click', e=>{ if(e.target===backdrop) close(); });
  modal.querySelector('#langsSelectAll').addEventListener('change', function(){
    $$('.langs-chk').forEach(ch => ch.checked = this.checked);
  });
  modal.querySelector('.langs-ok').addEventListener('click', function(){
    const selected = $$('.langs-chk').filter(ch=>ch.checked).map(ch=>ch.value);
    const color = document.getElementById('langBadgeColor')?.value || '';
    applyLanguages(selected, color);
    close();
  });
}

  
  function openModal(){
  buildModal();
  const cur = getCurrentLangs();
  $$('.langs-chk').forEach(ch => ch.checked = cur.includes(ch.value));
  const all = $('#langsSelectAll');
  if(all) all.checked = $$('.langs-chk').every(ch=>ch.checked);

  // Установим текущий цвет плашки
  const el = document.querySelector('#stage .el[data-type="langbadge"]');
  const colorInput = document.getElementById('langBadgeColor');
  if (colorInput) {
    colorInput.value = (el && el.dataset && el.dataset.badgeColor) || '#2ea8ff';
  }

  $('#langsModalBackdrop').classList.remove('hidden');
}


  function getCurrentLangs(){
    const el = document.querySelector('#stage .el[data-type="langbadge"]');
    if(el && el.dataset.langs) return String(el.dataset.langs).split(',').filter(Boolean);
    try{
      const dev = (window.currentDevice || 'desktop');
      const arr = (window.deviceData && window.deviceData[dev] && window.deviceData[dev].elements) ? window.deviceData[dev].elements : [];
      const it = arr.find(x => (x && x.type==='langbadge'));
      if(it && it.langs) return String(it.langs).split(',').filter(Boolean);
    }catch(e){}
    return [];
  }

  /* ---------------- Элемент на сцене ---------------- */
  function ensureStage(){ 
    return document.querySelector('#stage') || document.querySelector('.stage') || document.querySelector('.editor-canvas') || document.body; 
  }

  function addHandlesAndTools(el){
    try{ if(typeof window.ensureTools==='function') ensureTools(el); }catch(e){}
    try{ if(typeof window.ensureHandle==='function') ensureHandle(el); }catch(e){}
    try{ if(typeof window.attachDragResize==='function') attachDragResize(el); }catch(e){}
  }

  function renderBadgeInner(el){
  const codes = (el.dataset.langs||'').split(',').filter(Boolean);
  const chipInline = el.dataset.badgeColor
  ? ` style="background:${el.dataset.badgeColor};border:1px solid ${el.dataset.badgeColor};color:#fff"`
  : '';
  el.innerHTML = `
    <div class="langbadge__wrap">
      <button type="button" class="lang-chip"${chipInline}>🌐 ${el.dataset.label || 'Языки'}</button>
      <div class="lang-dropdown">
        ${codes.length
  ? codes.map(c=>`<div class="lang-item" data-code="${c}"><span>${nameByCode(c)}</span></div>`).join('')
  : '<div class="lang-empty">Нет выбранных языков</div>'}
      </div>
    </div>
  `;
  const chip = el.querySelector('.lang-chip');
  const dd = el.querySelector('.lang-dropdown');
  chip?.addEventListener('click', function(e){ e.stopPropagation(); dd?.classList.toggle('open'); });
  document.addEventListener('click', ()=> dd?.classList.remove('open'));
  addHandlesAndTools(el);
}


  function createBadge(opts={}){
  const stage = ensureStage();

  const el = document.createElement('div');
  el.className = 'el langbadge';
  el.dataset.type = 'langbadge';
  el.dataset.id = opts.id || uid();
  el.dataset.label = opts.label || 'Языки';
  // ВАЖНО: сохраняем языки и цвет плашки
  el.dataset.langs = Array.isArray(opts.langs) ? opts.langs.join(',') : (opts.langs || '');
  el.dataset.badgeColor = opts.badgeColor || '';

  
  // Геометрия
  el.style.left   = (opts.left ?? 10) + '%';
  el.style.top    = (opts.top  ?? 10) + 'px';
  if (opts.width == null) {
    el.style.width  = (opts.pxWidth  ?? 220) + 'px';
  } else {
    el.style.width  = opts.width + '%';
  }
  if (opts.height == null) {
    el.style.height = (opts.pxHeight ?? 40) + 'px';
  } else {
    el.style.height = opts.height + '%';
  }
  el.style.zIndex = (opts.z ?? 2);
  el.style.borderRadius = (opts.radius ?? 8) + 'px';

  // Рендер содержимого
  renderBadgeInner(el);

  // Добавляем на сцену
  if (stage) stage.appendChild(el);
  addHandlesAndTools(el);
  return el;
}


  function applyLanguages(codes, badgeColor){
  let el = document.querySelector('#stage .el[data-type="langbadge"]');
  if(!codes || !codes.length){
    if(el) el.remove();
    syncToDeviceData(null);
    return;
  }
  if(!el){
    el = createBadge({ langs: codes, badgeColor: badgeColor });
  } else {
    const currentStyle = {
      left: el.style.left,
      top: el.style.top,
      width: el.style.width,
      height: el.style.height,
      zIndex: el.style.zIndex,
      borderRadius: el.style.borderRadius
    };
    el.dataset.langs = codes.join(',');
    if (badgeColor) el.dataset.badgeColor = badgeColor;
    renderBadgeInner(el);
    Object.assign(el.style, currentStyle);
  }
  syncToDeviceData(el);
}


  function syncToDeviceData(el){
  try{
    const id = el ? el.dataset.id : (function(){
      const e = (window.deviceData?.[window.currentDevice||'desktop']?.elements||[]).find(x=>x && x.type==='langbadge');
      return e && e.id;
    })();
    const langs = el ? (el.dataset.langs || '') : '';
    const color = el ? (el.dataset.badgeColor || '') : '';

    ['desktop','tablet','mobile'].forEach(dev=>{
      if (!window.deviceData) window.deviceData = {};
      if (!window.deviceData[dev]) window.deviceData[dev] = { elements: [] };
      const arr = window.deviceData[dev].elements;
      let it = arr.find(x => x && x.type==='langbadge' && (x.id === id || !id));
      if(!it && el){
        it = { id: el.dataset.id, type: 'langbadge',
               left: parseFloat(el.style.left)||10, top: parseFloat(el.style.top)||10,
               width: parseFloat(el.style.width)||18, height: parseFloat(el.style.height)||6,
               z: parseInt(el.style.zIndex||2,10), radius: parseInt(el.style.borderRadius||8,10) };
        arr.push(it);
      }
      if(it){ it.langs = langs; it.badgeColor = color; }
    });
  }catch(e){}
}


  /* ---------------- Перехват сохранения ---------------- */
  function patchFetchOnce(){
    if(window.__langs_fetch_patched__) return;
    window.__langs_fetch_patched__ = true;
    const _fetch = window.fetch;
    window.fetch = function(input, init){
      try{
        const url = (typeof input==='string') ? input : (input && input.url) || '';
        const isSave = /\/editor\/api\.php/.test(url) && /action=savePage(Adaptive)?/i.test(url);
        if(isSave && init && init.body instanceof FormData){
          const fd = init.body;
          ['data_json','data_tablet','data_mobile'].forEach(key=>{
            const raw = fd.get(key); if(!raw) return;
            try{
              const obj = JSON.parse(raw);
              if(obj && Array.isArray(obj.elements)){
                obj.elements = obj.elements.map(it=>{
                  if(it && it.type==='langbadge'){
                    const dom = document.querySelector(`.el[data-id="${it.id}"]`);
                    const langs = (dom && dom.dataset && dom.dataset.langs) ? dom.dataset.langs : (it.langs || '');
                    const badgeColor = (dom && dom.dataset && dom.dataset.badgeColor) ? dom.dataset.badgeColor : (it.badgeColor || '');
                     return { ...it, langs, badgeColor };

                  }
                  return it;
                });
                fd.set(key, JSON.stringify(obj));
              }
            }catch(e){ /* ignore */ }
          });
        }
      }catch(e){ /* ignore */ }
      return _fetch(input, init);
    };
  }

  /* ---------------- Гидрация при загрузке/переключении ---------------- */
  function hydrateExisting(){
    const stage = ensureStage();
    if(!stage) return;
    stage.querySelectorAll('.el[data-type="langbadge"]').forEach(el=>{
      try{
        const dev = (window.currentDevice || 'desktop');
        const arr = (window.deviceData && window.deviceData[dev] && window.deviceData[dev].elements) ? window.deviceData[dev].elements : [];
        const data = arr.find(x=> x && x.id === el.dataset.id);
        if(data){ if(data.langs) el.dataset.langs = data.langs; if(data.badgeColor) el.dataset.badgeColor = data.badgeColor; }
      }catch(e){}
      if(!el.dataset.label) el.dataset.label = 'Языки';
      renderBadgeInner(el);
      addHandlesAndTools(el);
    });
  }

  function observeStage(){
    const stage = ensureStage(); if(!stage || stage.__langs_observing__) return;
    stage.__langs_observing__ = true;
    const mo = new MutationObserver(muts=>{
      muts.forEach(m=>{
        m.addedNodes && m.addedNodes.forEach(node=>{
          if(node.nodeType===1 && node.matches && node.matches('.el[data-type="langbadge"]')){
            renderBadgeInner(node);
            addHandlesAndTools(node);
          }
        });
      });
    });
    mo.observe(stage, { childList:true, subtree:false });
  }

  onReady(function(){
    insertToolbarBtn();
    buildModal();
    patchFetchOnce();
    hydrateExisting();
    observeStage();

    document.addEventListener('click', function(e){
      const t = e.target;
      if(t && t.matches && t.matches('[data-device]')){
        setTimeout(hydrateExisting, 60);
      }
    });
  });
})();