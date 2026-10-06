(() => {
const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
const C = window.CFG, T = C.t, L = C.lang;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const safe = u => /^(https?:\/\/|tel:)/i.test(u || '') ? u : '';
const img = s => !s ? '' : (/^(https?:)?\/\//.test(s) || s[0] === '/') ? s : C.base + '/' + s;
const imgs = r => String(r.images || r.image_url || r.cover_image || '').split('|').map(s => s.trim()).filter(Boolean).map(img);
const nm = (r, f = 'name') => (L === 'kh' && r[f + '_kh']) || r[f + '_en'] || r[f] || '';
const get = (a, p = {}) => fetch(`${C.base}/api.php?a=${a}&` + new URLSearchParams(p)).then(r => r.json());
const post = (a, b) => fetch(`${C.base}/api.php?a=${a}`, { method: 'POST', keepalive: true, headers: { 'Content-Type': 'application/json', 'X-CSRF': C.csrf }, body: JSON.stringify(b) }).then(async r => ({ ok: r.ok, status: r.status, data: await r.json() }));
const slides = r => { const l = imgs(r); return l.length ? `<div class="slides">${l.map((s, i) => `<img src="${esc(s)}" alt="" loading="lazy" class="${i ? '' : 'on'}" onerror="this.remove()">`).join('')}</div>` : ''; };
const needLogin = () => location.href = `${C.base}/login.php?next=${encodeURIComponent(location.pathname)}`;
const lines = s => String(s || '').split(/\n|\|/).map(x => x.trim()).filter(Boolean);
const fmt = n => { n = +n || 0; return n >= 1e6 ? (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M' : n >= 1e3 ? (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K' : String(n); };
const eye = n => n != null ? `<span class="tag">👁 ${fmt(n)}</span>` : '';
const clipN = () => innerWidth <= 640 ? 2 : innerWidth <= 1024 ? 3 : innerWidth < 1700 ? 4 : innerWidth < 2200 ? 5 : 6;

$('#theme')?.addEventListener('click', () => { const l = document.body.classList.toggle('theme-light'); localStorage.theme = l ? 'light' : 'dark'; });
const panel = $('#navpanel'), burger = $('#burger');
const closePanel = () => { panel?.classList.remove('open'); if (burger) { burger.textContent = '☰'; burger.setAttribute('aria-expanded', 'false'); } };
burger?.addEventListener('click', e => { e.stopPropagation(); const o = panel.classList.toggle('open'); burger.textContent = o ? '✕' : '☰'; burger.setAttribute('aria-expanded', String(o)); });
panel?.addEventListener('click', e => { if (e.target.closest('.links a')) closePanel(); });

const topnav = $('#topnav');
const onScroll = () => topnav && topnav.classList.toggle('shrunk', window.scrollY > 40);
onScroll(); addEventListener('scroll', onScroll, { passive: true });

const io = 'IntersectionObserver' in window ? new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .05, rootMargin: '0px 0px -20px 0px' }) : null;
const observeReveal = (root = document) => { if (!io) return; $$('.reveal', root).forEach(el => io.observe(el)); };
observeReveal();
setTimeout(() => $$('.reveal:not(.in)').forEach(el => el.classList.add('in')), 2500);

const counters = $$('.stats b[data-count]');
if (counters.length && 'IntersectionObserver' in window) {
  const co = new IntersectionObserver(es => es.forEach(e => { if (!e.isIntersecting) return;
    const el = e.target, target = parseFloat(el.dataset.count) || 0; let start = null;
    const step = ts => { if (!start) start = ts; const p = Math.min(1, (ts - start) / 1200);
      el.textContent = Math.floor(target * (1 - Math.pow(1 - p, 3))) + (el.dataset.count.includes('.') ? '' : '');
      if (p < 1) requestAnimationFrame(step); else el.textContent = target + (el.dataset.count.endsWith('+') ? '+' : ''); };
    requestAnimationFrame(step); co.unobserve(el); }), { threshold: .5 });
  counters.forEach(c => co.observe(c));
}

const KHM = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
const monthName = w => L === 'kh' && KHM[+w.month_num - 1] ? KHM[+w.month_num - 1] : String(w.month_name).slice(0, 3);

function initSlides(root = document) {
  $$('.slides', root).forEach(el => {
    if (el._s) return; el._s = 1; let i = 0;
    const first = el.querySelector('img'); if (first) first.classList.add('on');
    setInterval(() => {
      const a = $$('img', el); if (a.length < 2) { if (a[0]) a[0].classList.add('on'); return; }
      a.forEach(x => x.classList.remove('on')); i = (i + 1) % a.length; a[i].classList.add('on');
    }, 4200 + Math.random() * 900);
  });
}
initSlides();

const sp = $$('#sponsor a');
if (sp.length > 1) {
  let k = 0, hover = false; const box = $('#sponsor'), dots = $$('#sponsor .sdots i');
  const go = n => { sp[k].classList.remove('on'); dots[k]?.classList.remove('on'); k = (n + sp.length) % sp.length; sp[k].classList.add('on'); dots[k]?.classList.add('on'); };
  box.addEventListener('mouseenter', () => hover = true); box.addEventListener('mouseleave', () => hover = false);
  $('.sarrow.prev', box)?.addEventListener('click', () => go(k - 1));
  $('.sarrow.next', box)?.addEventListener('click', () => go(k + 1));
  dots.forEach((d, n) => d.addEventListener('click', () => go(n)));
  setInterval(() => { if (!hover && !document.hidden) go(k + 1); }, 4500);
}

let st; const q = $('#q'), qres = $('#qres');
q?.addEventListener('input', () => { clearTimeout(st); st = setTimeout(async () => {
  if (q.value.trim().length < 2) return qres.hidden = true;
  const r = await get('search', { q: q.value.trim() });
  qres.innerHTML = r.length ? r.map(x => `<button data-${x.type === 'place' ? 'place' : 'prov'}="${esc(x.type === 'place' ? x.id : x.slug)}">${esc(x.label)}<small>${esc(x.sub)}</small></button>`).join('') : '<p>—</p>';
  qres.hidden = false; }, 250); });

const bell = $('#bell'), notes = $('#notes'); let maxId = 0;
async function loadNotes() {
  const r = await get('notes'); maxId = r.length ? +r[0].id : 0;
  $('#dot').hidden = !(maxId > (+localStorage.seen || 0));
  notes.innerHTML = r.length ? r.map(n => `<a href="${esc(n.link || '#')}"><b>${esc(nm(n, 'title'))}</b><small>${esc(nm(n, 'body'))}</small></a>`).join('') : `<p>${esc(T.nonotes)}</p>`;
}
loadNotes();
bell?.addEventListener('click', e => { e.stopPropagation(); notes.hidden = !notes.hidden; if (!notes.hidden) { localStorage.seen = maxId; $('#dot').hidden = true; } });

const modal = $('#modal'), sheet = $('#sheet');
const show = html => { sheet.innerHTML = html; modal.hidden = false; document.body.style.overflow = 'hidden'; sheet.scrollTop = 0; initSlides(sheet); };
const close = () => { modal.hidden = true; document.body.style.overflow = ''; };

const contact = (h, type) => { const info = safe(h.facebook_url || h.telegram_link);
  return (info ? `<a class="chip" target="_blank" rel="noopener" data-count="${type}:${h.id}" href="${esc(info)}">${T.info}</a>` : '') + `<a class="chip on" data-count="${type}:${h.id}" href="${C.base}/book.php?type=${type}&id=${h.id}">${T.booking}</a>`; };
const hotelRow = h => `<div class="row2" data-type="${esc(h.type)}"><div class="rimg">${slides(h)}</div><div class="rtxt"><b>${esc(h.name)}</b><div>${h.type ? `<span class="tag">${esc(h.type)}</span>` : ''}${h.rating ? `<span class="tag">★ ${esc(h.rating)}</span>` : ''}${h.distance_km != null ? `<span class="tag">📍 ${esc(h.distance_km)} km</span>` : ''}${eye(h.views)}</div><small>${esc(h.address)}</small><div class="racts">${contact(h, 'hotel')}</div></div>${h.price_range ? `<span class="price">${esc(h.price_range)}</span>` : ''}</div>`;
const foodRow = f => `<div class="row2" data-type="${esc(f.cuisine_type)}"><div class="rimg">${slides(f)}</div><div class="rtxt"><b>${esc(nm(f, 'restaurant_name'))}</b><div>${f.cuisine_type ? `<span class="tag">${esc(f.cuisine_type)}</span>` : ''}${eye(f.views)}</div><small>${esc(f.opening_hours)}</small><div class="racts"><button class="chip" data-menu="${f.id}" data-count="restaurant:${f.id}">🍽 ${T.menu}</button>${contact(f, 'restaurant')}</div><div class="menu" id="menu-${f.id}" hidden></div></div>${f.price_range ? `<span class="price">${esc(f.price_range)}</span>` : ''}</div>`;
const shopRow = s => `<div class="row2"><div class="rimg">${slides(s)}</div><div class="rtxt"><b>${esc(nm(s))}</b><div>${s.shop_type ? `<span class="tag">${esc(s.shop_type)}</span>` : ''}</div><small>${esc(L === 'kh' && s.merchandise_kh ? s.merchandise_kh : s.merchandise_en)}</small><small>${esc(nm(s, 'address'))} ${esc(s.opening_hours)}</small><div class="racts">${safe(s.website_url) ? `<a class="chip" target="_blank" rel="noopener" href="${esc(s.website_url)}">${esc(T.website)}</a>` : ''}${s.phone ? `<a class="chip on" href="tel:${esc(s.phone)}">${esc(T.call)}</a>` : ''}</div></div>${s.price_level ? `<span class="price">${esc(s.price_level)}</span>` : ''}</div>`;
const list = (items, row) => !items.length ? '<p>—</p>' : `<div class="more-list">${items.slice(0, 2).map(row).join('')}<div class="extra">${items.slice(2).map(row).join('')}</div></div>` + (items.length > 2 ? `<button class="more upper" data-more>${esc(T.show_more)} (${items.length - 2})</button>` : '');

async function openPlace(id) {
  show('<div class="body">…</div>');
  const d = await get('place', { id }), p = d.place; if (!p) return close();
  const col = p.cat_color || '#7A3B10';
  const infoItems = [[T.province, nm({ name_en: p.prov_en, name_kh: p.prov_kh })], [T.category, nm({ name_en: p.cat_en, name_kh: p.cat_kh })], [T.price, p.entry_fee], [T.time, p.duration], [T.difficulty, p.difficulty], [T.opening, p.opening_hours], [T.best_time, nm(p, 'best_time')], [T.main_product, nm(p, 'main_product')]].filter(x => x[1]);
  const ul = s => lines(s).length ? `<ul>${lines(s).map(x => `<li>${esc(x)}</li>`).join('')}</ul>` : '';
  const sec = (title, html) => html ? `<h4>${title}</h4>${html}` : '';
  const maps = p.latitude ? `https://www.google.com/maps?q=${p.latitude},${p.longitude}` : `https://www.google.com/maps/search/${encodeURIComponent(p.name_en)}`;
  const rt = d.rating && +d.rating.n ? `<span class="tag">★ ${esc(d.rating.avg)} (${+d.rating.n})</span>` : '';
  const vw = p.views != null ? `<span class="tag">👁 ${fmt(p.views)}</span>` : '';
  const revs = d.reviews.map(r => `<div class="rev"><b>${'★'.repeat(+r.rating)}${'☆'.repeat(5 - r.rating)} ${esc(r.name)}</b>${r.comment ? `<p>${esc(r.comment)}</p>` : ''}</div>`).join('');
  show(`<button class="x" data-close>✕</button>
  <div class="hdr" style="background:linear-gradient(160deg,${col},${col}88)">${slides(p)}<div class="grad"></div><div class="hdr-t"><span class="pill upper">${esc(nm({ name_en: p.cat_en, name_kh: p.cat_kh }))}</span> ${rt} ${vw}<h2>${esc(p.name_en)}</h2><small>${esc(p.name_kh)}</small></div></div>
  <div class="body"><div class="acts"><a class="chip on" target="_blank" rel="noopener" href="${maps}">📍 ${T.map}</a><button class="chip" data-bm="${p.id}">${d.bookmarked ? '★' : '☆'} ${T.bookmark}</button><button class="chip" data-trip="${p.id}">🧭 ${T.trip}</button><button class="chip" data-review="${p.id}">✎ ${T.review}</button></div>
  <div id="rv"></div>
  <div class="split"><div class="col"><h4>${T.about}</h4><p>${esc(L === 'kh' && p.description_kh ? p.description_kh : p.description_en)}</p>
  <div class="info">${infoItems.map(([k, v]) => `<div><small>${esc(k)}</small>${esc(v)}</div>`).join('')}</div>
  ${sec(T.features, ul(L === 'kh' && p.highlights_kh ? p.highlights_kh : p.highlights_en))}
  ${sec(T.things_do, ul(nm(p, 'things_to_do')))}${sec(T.things_avoid, ul(nm(p, 'things_avoid')))}
  ${sec(T.getting_there, nm(p, 'getting_there') ? `<p>${esc(nm(p, 'getting_there'))}</p>` : '')}${sec(T.services_h, nm(p, 'services') ? `<p>${esc(nm(p, 'services'))}</p>` : '')}
  ${sec(T.reviews_h, revs)}
  </div><div class="col"><h4>${T.hotels_near}</h4>${list(d.hotels, hotelRow)}
  <h4>${T.food_near}</h4>${list(d.food, foodRow)}</div></div></div>`);
  initSlides(sheet);
}

async function openProvince(slug) {
  show('<div class="body">…</div>');
  const d = await get('province', { slug }), pv = d.province; if (!pv) return close();
  const col = pv.cover_color || '#7A3B10';
  const chips = (arr, key) => { const s = [...new Set(arr.map(x => x[key]).filter(Boolean))]; return s.length > 1 ? `<div class="chiprow"><button class="on" data-chip="">${T.all}</button>${s.map(x => `<button data-chip="${esc(x)}">${esc(x)}</button>`).join('')}</div>` : ''; };
  const wx = d.weather.length ? `<h4>${T.weather}</h4><div class="weather">${d.weather.map(w => `<div class="wm ${esc(String(w.season).toLowerCase())}" title="${esc(/dry/i.test(w.season) ? T.dry : T.wet)}">${esc(monthName(w).toUpperCase())}<br>${esc(w.avg_temp_c)}°</div>`).join('')}</div>` : '';
  const hasShops = d.shops && d.shops.length;
  show(`<button class="x" data-close>✕</button>
  <div class="hdr" style="background:linear-gradient(160deg,${col},${col}88);height:170px">${slides(pv)}<div class="grad"></div><div class="hdr-t"><small class="upper">${esc(pv.region)}</small><h2>${esc(pv.name_en)}</h2><small>${esc(pv.name_kh)}</small></div></div>
  <div class="tabs"><button class="on" data-tab="places">${T.places}</button><button data-tab="hotels">${T.hotels}</button><button data-tab="food">${T.food}</button>${hasShops ? `<button data-tab="shops">${T.shops}</button>` : ''}<button class="x2" data-close aria-label="Close">✕</button></div>
  <div class="body">
   <div class="pane" data-pane="places"><p>${esc(L === 'kh' && pv.description_kh ? pv.description_kh : pv.description_en)}</p>${wx}<div class="tgrid" style="margin-top:14px">${d.places.map(p => `<div class="card" data-place="${p.id}" style="background:linear-gradient(160deg,${p.cat_color || '#7A3B10'},${p.cat_color || '#7A3B10'}88)">${slides(p)}<div class="grad"></div>${p.views != null ? `<span class="views">👁 ${fmt(p.views)}</span>` : ''}<div class="txt"><b>${esc(p.name_en)}</b><span>${esc(p.name_kh)}</span></div><div class="cta"><button class="upper">${T.open}</button></div></div>`).join('')}</div></div>
   <div class="pane" data-pane="hotels" hidden><h4>${T.where_stay}</h4>${chips(d.hotels, 'type')}<div class="rows2">${d.hotels.map(hotelRow).join('') || '<p>—</p>'}</div></div>
   <div class="pane" data-pane="food" hidden>${chips(d.food, 'cuisine_type')}<div class="rows2">${d.food.map(foodRow).join('') || '<p>—</p>'}</div></div>
   ${hasShops ? `<div class="pane" data-pane="shops" hidden><div class="rows2">${d.shops.map(shopRow).join('')}</div></div>` : ''}
  </div>`);
  initSlides(sheet);
}

$$('.mdot').forEach(d => d.addEventListener('mouseenter', () => { $('#mselName').textContent = d.dataset.name; $('#mselHint').textContent = T.tap; const o = $('#mselOpen'); o.dataset.prov = d.dataset.prov; o.disabled = false; $$('.mdot').forEach(x => x.classList.toggle('active', x === d)); }));

const clog = $('#chatLog'), copts = $('#chatOpts'); let provs;
const say = (h, me) => { clog.insertAdjacentHTML('beforeend', `<div class="msg${me ? ' me' : ''}">${h}</div>`); clog.scrollTop = clog.scrollHeight; };
const choices = l => copts.innerHTML = l.map(([label, act, arg]) => `<button data-bot="${act}" data-arg="${esc(arg || '')}">${esc(label)}</button>`).join('');
const ROOT = () => [[T.bot_plan, 'plan'], [T.bot_hotels, 'hotels'], [T.bot_food, 'food'], [T.bot_explore, 'explore'], [T.bot_best, 'best'], [T.bot_about, 'about'], [T.bot_contact, 'contact']];
const botRoot = () => { say(esc(T.bot_hello)); choices(ROOT()); };
async function pickProv(next) { provs ??= await get('provinces_list'); say(esc(T.bot_which)); choices([...provs.map(p => [nm(p), next, p.slug]), [T.bot_back, 'root']]); }
async function botAct(act, arg, label) {
  if (label) say(esc(label), true);
  const again = () => choices([[T.bot_again, 'root']]);
  if (act === 'root') return botRoot();
  if (['plan', 'hotels', 'food', 'explore', 'best'].includes(act)) return pickProv(act + '2');
  if (act === 'plan2') { say(`<a href="${C.base}/planner.php?provinces=${encodeURIComponent(arg)}">${esc(T.bot_plan2)}</a>`); return again(); }
  if (act === 'explore2') { $('#chatPanel').hidden = true; return openProvince(arg); }
  if (['hotels2', 'food2', 'best2'].includes(act)) {
    const d = await get('province', { slug: arg });
    if (act === 'hotels2') { const top = d.hotels.slice().sort((a, b) => (+b.rating || 0) - (+a.rating || 0)).slice(0, 3);
      say(top.length ? top.map(h => `🏨 <b>${esc(h.name)}</b> ${esc(h.type || '')} ${esc(h.price_range || '')} · <a href="${C.base}/book.php?type=hotel&id=${h.id}">${esc(T.book_btn)}</a>`).join('<br>') : esc(T.bot_nohotel)); }
    if (act === 'food2') { const top = d.food.slice(0, 3);
      say(top.length ? top.map(f => `🍜 <b>${esc(nm(f, 'restaurant_name'))}</b> ${esc(f.cuisine_type || '')} ${esc(f.price_range || '')} · <a href="${C.base}/book.php?type=restaurant&id=${f.id}">${esc(T.reserve_btn)}</a>`).join('<br>') : esc(T.bot_nofood)); }
    if (act === 'best2') { const dry = d.weather.filter(w => /dry/i.test(w.season)).map(monthName);
      say(dry.length ? esc(T.bot_best2.replace('{name}', nm(d.province)).replace('{months}', dry.join(', '))) : esc(T.bot_noweather)); }
    return again();
  }
  if (act === 'about') { say(esc(T.bot_about_text)); return again(); }
  if (act === 'contact') { const s = await get('settings'); const bits = [];
    if (safe(s.contact_telegram)) bits.push(`<a target="_blank" rel="noopener" href="${esc(s.contact_telegram)}">Telegram</a>`);
    if (s.contact_email) bits.push(`<a href="mailto:${esc(s.contact_email)}">${esc(s.contact_email)}</a>`);
    say(bits.length ? esc(T.bot_contact_on) + ' ' + bits.join(' · ') : esc(T.bot_contact_none)); return again(); }
}

document.addEventListener('click', async e => {
  const t = e.target;
  if (t.closest('#chatX')) { $('#chatPanel').hidden = true; return; }
  if (!$('#chatPanel').hidden && !t.closest('.chat')) $('#chatPanel').hidden = true;
  const cn = t.closest('[data-count]');
  if (cn) { const [ty, cid] = cn.dataset.count.split(':'); post('view', { type: ty, id: +cid }); }
  if (panel && !t.closest('#navpanel') && !t.closest('#burger')) closePanel();
  if (t.closest('[data-place],[data-prov]')) closePanel();
  if (!t.closest('.search')) qres && (qres.hidden = true);
  if (!t.closest('#notes') && !t.closest('#bell')) notes && (notes.hidden = true);
  if (t === modal || t.closest('[data-close]')) return close();
  const clipBtn = t.closest('[data-clip-btn]'); if (clipBtn) {
    const clip = clipBtn.previousElementSibling;
    if (clip && clip.classList.contains('clip')) {
      const open = clip.classList.toggle('open');
      const total = clip.children.length;
      const n = clipN();
      clipBtn.textContent = open ? (T.show_less || 'Show less') : (T.show_more || 'Show more') + ' (' + Math.max(0, total - n) + ')';
    }
    return;
  }
  const bot = t.closest('[data-bot]'); if (bot) return botAct(bot.dataset.bot, bot.dataset.arg, bot.textContent);
  const pl = t.closest('[data-place]'); if (pl) { qres && (qres.hidden = true); return openPlace(pl.dataset.place); }
  const pr = t.closest('[data-prov]'); if (pr && pr.dataset.prov) { qres && (qres.hidden = true); return openProvince(pr.dataset.prov); }
  const mm = t.closest('[data-more]'); if (mm) { const l = mm.previousElementSibling; l.classList.toggle('open'); mm.textContent = l.classList.contains('open') ? T.show_less : T.show_more; return; }
  const tab = t.closest('[data-tab]'); if (tab) { $$('[data-tab]', sheet).forEach(b => b.classList.toggle('on', b === tab)); $$('.pane', sheet).forEach(p => p.hidden = p.dataset.pane !== tab.dataset.tab); return; }
  const ch = t.closest('[data-chip]'); if (ch) { const box = ch.closest('.pane'); $$('[data-chip]', box).forEach(b => b.classList.toggle('on', b === ch)); $$('.row2', box).forEach(r => r.hidden = ch.dataset.chip && r.dataset.type !== ch.dataset.chip); return; }
  const mn = t.closest('[data-menu]'); if (mn) { const box = $('#menu-' + mn.dataset.menu);
    if (box.dataset.loaded) { box.hidden = !box.hidden; return; }
    const items = await get('menu', { restaurant_id: mn.dataset.menu }); box.dataset.loaded = 1; box.hidden = false;
    box.innerHTML = items.length ? items.map(i => `<div class="mi"><span>${esc(nm(i))}${i.category ? ` <small class="tag">${esc(i.category)}</small>` : ''}</span><b>${i.price_usd != null ? '$' + (+i.price_usd).toFixed(2) : ''}</b></div>`).join('') : `<small class="muted">${esc(T.menu_none)}</small>`; return; }
  const bm = t.closest('[data-bm]'); if (bm) { if (!C.user) return needLogin();
    const r = await post('bookmark', { place_id: +bm.dataset.bm }); if (r.ok) bm.textContent = (r.data.bookmarked ? '★ ' : '☆ ') + T.bookmark; return; }
  const tr = t.closest('[data-trip]'); if (tr) { if (!C.user) return needLogin(); const r = await post('trip_add', { place_id: +tr.dataset.trip });
    $('#rv').innerHTML = r.ok ? `<p class="ok">${esc(T.trip_added)} <a href="${C.base}/profile.php#trips">${esc(T.my_trips)}</a> · <a href="${C.base}/planner.php">${esc(T.planner)}</a></p>` : '<p class="err">Error</p>'; return; }
  const rv = t.closest('[data-review]'); if (rv) { if (!C.user) return needLogin();
    $('#rv').innerHTML = `<div class="panelbox" style="margin-top:12px"><select id="rvR">${[5, 4, 3, 2, 1].map(n => `<option value="${n}">${'★'.repeat(n)}</option>`).join('')}</select><textarea id="rvC" rows="2"></textarea><button class="btn-gold upper" data-rvsend="${rv.dataset.review}">${T.send}</button></div>`; return; }
  const rs = t.closest('[data-rvsend]'); if (rs) { const r = await post('review', { place_id: +rs.dataset.rvsend, rating: +$('#rvR').value, comment: $('#rvC').value }); $('#rv').innerHTML = `<p class="${r.ok ? 'ok' : 'err'}">${r.ok ? T.saved : 'Error'}</p>`; return; }
  if (t.closest('#chatBtn')) { const p = $('#chatPanel'); p.hidden = !p.hidden; if (!p.hidden && !clog.children.length) botRoot(); return; }
  if (t.closest('#chatReset')) { clog.innerHTML = ''; return botRoot(); }
  if (t.closest('#chatContact')) return botAct('contact', '', 'Contact');
  const sos = t.closest('#sosPolice,#sosFire'); if (sos) { const w = sos.id === 'sosPolice' ? 'police' : 'fire', s = await get('settings'), tg = s[w + '_telegram'], tel = s[w + '_call'];
    if (safe(tg)) window.open(tg, '_blank', 'noopener'); else if (tg) window.open('https://t.me/' + tg.replace('@', ''), '_blank', 'noopener'); else if (tel) location.href = 'tel:' + tel; }
});
addEventListener('keydown', e => { if (e.key === 'Escape') { close(); $('#chatPanel').hidden = true; } });

(function () {
  if (C.user || /login\.php|contact\.php|apply\.php/.test(location.pathname)) return;
  try { if (sessionStorage.lp_shown || (+localStorage.lp_until || 0) > Date.now()) return; } catch (e) {}
  const open = () => {
    try { if (sessionStorage.lp_shown) return; sessionStorage.lp_shown = 1; } catch (e) {}
    const box = document.createElement('div'); box.className = 'lp';
    box.innerHTML = `<button class="lp-x" aria-label="Close">✕</button><b>${esc(T.lp_title)}</b><p>${esc(T.lp_text)}</p>
      <div class="lp-b"><a class="btn-gold upper" data-lp="login" href="${C.base}/login.php?from=prompt">${esc(T.lp_login)}</a><a class="chip on upper" data-lp="signup" href="${C.base}/login.php?signup=1&from=prompt">${esc(T.lp_signup)}</a><button class="chip" data-lp="later">${esc(T.lp_later)}</button></div>`;
    document.body.appendChild(box);
    post('event', { name: 'login_prompt_shown' });
    box.addEventListener('click', e => {
      const b = e.target.closest('[data-lp],.lp-x'); if (!b) return;
      const w = b.dataset.lp;
      if (w === 'login' || w === 'signup') { post('event', { name: 'login_prompt_' + w }); return; }
      try { localStorage.lp_until = Date.now() + 24 * 3600 * 1000; } catch (e) {}
      box.remove();
    });
  };
  setTimeout(() => document.hidden ? document.addEventListener('visibilitychange', function f() { if (!document.hidden) { document.removeEventListener('visibilitychange', f); setTimeout(open, 3000); } }) : open(), 10000);
})();
})();