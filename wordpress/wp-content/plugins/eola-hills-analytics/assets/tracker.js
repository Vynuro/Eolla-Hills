/* Automatic first-party measurement. No external scripts, fingerprinting or form values. */
(() => {
  'use strict';
  const config = window.eolaAnalytics;
  if (!config || window.eolaAnalyticsLoaded || location.search.includes('elementor-preview') || navigator.globalPrivacyControl || navigator.doNotTrack === '1') return;
  window.eolaAnalyticsLoaded = true;
  const KEY = 'eola_analytics_choice_v1', SESSION = 'eola_analytics_session_v1', TTL = 90 * 86400000;
  const read = (store, key) => { try { return JSON.parse(store.getItem(key)); } catch { return null; } };
  const write = (store, key, value) => { try { store.setItem(key, JSON.stringify(value)); } catch { /* memory-only measurement still works */ } };
  const uuid = () => crypto.randomUUID();
  const slug = (text, max = 80) => /^[a-z0-9_.-]*$/i.test(text || '') ? String(text || '').toLowerCase().slice(0, max) : '';
  let choice = read(localStorage, KEY), session, queue = [], running = false, started = false, sending = false;
  let lastActive = performance.now(), lastTick = performance.now(), activeSeconds = 0, currentSection = 'hero';
  let seen = new Set();
  if (!choice || !choice.expires || choice.expires < Date.now()) choice = null;
  const sections = [...document.querySelectorAll('.elementor > .elementor-element[id], .eola-reveal-section[id], #property, #home, #land, #gallery, #details, #team, #film, #floor-plans')];
  const sectionId = el => slug(el?.closest('[id]')?.id) || 'property';
  const elementId = el => slug(el?.closest('[data-id]')?.getAttribute('data-id')) || 'item';
  const event = (name, target = '', seconds = 0) => {
    if (!running) return;
    queue.push({ id: uuid(), name, page: config.page, target: slug(target), seconds });
    if (queue.length >= 10) flush();
  };
  function attribution() {
    const p = new URLSearchParams(location.search);
    let ref = '';
    try { const url = new URL(document.referrer); if (url.hostname !== location.hostname) ref = url.hostname; } catch { /* no referrer */ }
    return { source: slug(p.get('utm_source'), 100) || slug(ref, 100) || 'direct', medium: slug(p.get('utm_medium'), 40) || (ref ? 'referral' : ''), campaign: slug(p.get('utm_campaign')) };
  }
  function ensureSession() {
    if (session && Date.now() - session.time < 1800000) { session.time = Date.now(); write(sessionStorage, SESSION, session); return false; }
    session = { id: uuid(), time: Date.now(), ...attribution() }; write(sessionStorage, SESSION, session); return true;
  }
  function body(batch) { return JSON.stringify({ mode: 'automatic', visitor: choice.id, session: session.id, source: session.source, medium: session.medium, campaign: session.campaign, device: innerWidth < 768 ? 'mobile' : innerWidth < 1024 ? 'tablet' : 'desktop', events: batch }); }
  async function flush(beacon = false) {
    if (!running || !queue.length || sending) return;
    const batch = queue.splice(0, 15), payload = body(batch);
    if (beacon && navigator.sendBeacon && navigator.sendBeacon(config.endpoint, new Blob([payload], { type: 'application/json' }))) return;
    sending = true;
    try {
      const response = await fetch(config.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true });
      if (response.status === 403 || response.status === 404) { running = false; queue = []; }
      else if (!response.ok && response.status >= 500) queue = batch.concat(queue).slice(0, 30);
    } catch { queue = batch.concat(queue).slice(0, 30); }
    finally { sending = false; }
  }
  function commitTime() {
    const seconds = Math.floor(activeSeconds);
    if (seconds > 0) { event('engagement', currentSection, Math.min(30, seconds)); activeSeconds -= seconds; }
  }
  function tick() {
    const now = performance.now(), elapsed = Math.min(5, (now - lastTick) / 1000); lastTick = now;
    if (!running || document.hidden || now - lastActive > 30000) return;
    if (ensureSession()) { seen = new Set(); event('page_view'); }
    activeSeconds += elapsed;
    let best = null, height = 0;
    for (const el of sections) {
      const rect = el.getBoundingClientRect(), visible = Math.max(0, Math.min(innerHeight, rect.bottom) - Math.max(0, rect.top));
      if (visible > height) { best = el; height = visible; }
    }
    const next = slug(best?.id) || 'hero';
    if (next !== currentSection) { commitTime(); currentSection = next; }
    if (!seen.has(currentSection)) { event('section_view', currentSection); seen.add(currentSection); }
    if (activeSeconds >= 15) { commitTime(); flush(); }
  }
  function start() {
    running = true; session = read(sessionStorage, SESSION); ensureSession();
    lastTick = lastActive = performance.now(); event('page_view'); flush();
    if (started) return;
    started = true;
    for (const type of ['pointerdown', 'keydown', 'scroll', 'touchstart']) addEventListener(type, () => { lastActive = performance.now(); }, { passive: true });
    setInterval(tick, 1000); setInterval(flush, 5000);
    document.addEventListener('visibilitychange', () => { if (document.hidden) { commitTime(); flush(true); } else lastTick = lastActive = performance.now(); });
    addEventListener('pagehide', () => { commitTime(); flush(true); });
    addEventListener('pageshow', e => { if (e.persisted && running) { lastTick = lastActive = performance.now(); event('page_view'); } });
    const click = e => {
      const el = e.target.closest('a,button,[role="button"],summary,.elementor-tab-title'); if (!el || el.closest('.eola-analytics-choice,.eola-analytics-settings')) return;
      let url; try { url = new URL(el.href || '', location.href); } catch { return; }
      const href = el.getAttribute('href') || '', id = elementId(el);
      if (/^(tel:|mailto:)/i.test(href)) { event('contact_click', href.startsWith('tel:') ? 'phone-' + id : 'email-' + id); if (/private.?tour|private%20tour/i.test(href)) event('showing_click', id); }
      else if (el.closest('.eola-filter')) { event('gallery_filter', [...el.closest('.eola-filter').classList].find(x => x.startsWith('eola-filter-'))?.replace('eola-filter-', '') || id); }
      else if (el.closest('.eola-scene-button')) { event('photo_change', id); }
      else if (/\.pdf$/i.test(url.pathname)) { event('download', /amenit/i.test(url.pathname) ? 'amenities' : /booklet|brochure/i.test(url.pathname) ? 'booklet' : id); }
      else if (el.closest('#floor-plans,.eola-floorplan') || /floorplan/i.test(url.pathname)) { event('floorplan_open', id); }
      else if (el.closest('.eola-gallery-tile')) { event('gallery_open', id); }
      else if (el.closest('.elementor-swiper-button,.elementor-swiper-button-next,.elementor-swiper-button-prev')) { event('photo_change', 'carousel'); }
      else if (el.closest('.elementor-tab-title')) { const tab=el.closest('.elementor-tab-title'); if(tab.getAttribute('aria-expanded')!=='true') event('amenity_open', 'amenity-'+id+'-'+slug(tab.getAttribute('data-tab'))); }
      else if (el.matches('summary') && !el.parentElement.open) { event('amenity_open', id); }
      else if (url.hash === '#team') { event('showing_click', id); }
      else if (href.startsWith('#')) { event('navigation_click', slug(url.hash.slice(1))); }
      flush();
    };
    document.addEventListener('click', click, true);
    document.addEventListener('keydown', e => { if (e.code === 'Space' && e.target.closest('.eola-filter,.eola-scene-button')) click(e); }, true);
    // Actual HTML5 playback only. Third-party iframe players require their own API adapter.
    document.addEventListener('play', e => { if (e.target.tagName === 'VIDEO') { event('video_start', elementId(e.target)); flush(); } }, true);
    document.addEventListener('ended', e => { if (e.target.tagName === 'VIDEO') { event('video_complete', elementId(e.target)); flush(); } }, true);
  }
  const settings = document.createElement('button'); settings.className = 'eola-analytics-settings'; settings.textContent = 'Analytics preferences'; settings.type = 'button';
  function showChoice(focus = false) {
    if (document.querySelector('.eola-analytics-choice')) return;
    const box = document.createElement('section'); box.className = 'eola-analytics-choice'; box.setAttribute('aria-label', 'Website analytics preferences');
    box.innerHTML = '<h2>Website analytics preferences</h2><p>This site measures visits and interactions automatically using a random browser ID for up to 90 days to recognise return visits. We do not record form entries or send this data to Google. You can turn measurement off here.</p><button type="button" data-choice="enabled">Enable analytics</button><button type="button" data-choice="declined">Disable analytics</button>';
    if (config.privacy) { const p = document.createElement('p'), a = document.createElement('a'); a.href = config.privacy; a.textContent = 'Privacy policy'; p.append(a); box.append(p); }
    box.addEventListener('click', e => {
      const button = e.target.closest('[data-choice]'); if (!button) return;
      const accept = button.dataset.choice === 'enabled', wasRunning = running;
      choice = { state: accept ? 'enabled' : 'declined', id: accept ? (choice?.id || uuid()) : '', expires: Date.now() + TTL };
      write(localStorage, KEY, choice); box.remove(); settings.hidden = false;
      if (accept && !wasRunning) start();
      if (!accept) { running = false; queue = []; activeSeconds = 0; session = null; try { sessionStorage.removeItem(SESSION); } catch {} }
      if (focus) settings.focus();
    });
    settings.hidden = true; document.body.append(box); if (focus) box.querySelector('button').focus();
  }
  settings.addEventListener('click', () => showChoice(true)); document.body.append(settings);
  addEventListener('storage', e => { if (e.key === KEY) { const next = read(localStorage, KEY); if (!next || !['enabled','accepted'].includes(next.state)) { choice = next; running = false; queue = []; activeSeconds = 0; session = null; try { sessionStorage.removeItem(SESSION); } catch {} } } });
  // Automatic mode is not represented as consent. Preserve any prior explicit opt-out.
  if (!choice) { choice = { state: 'enabled', id: uuid(), expires: Date.now() + TTL }; write(localStorage, KEY, choice); }
  if (['enabled','accepted'].includes(choice.state)) start();
})();
