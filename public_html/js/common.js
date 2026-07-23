/* ==========================================================================
   BRASA & AGULHA EDITORIAL — utilitários compartilhados
   Usado tanto pelo site público (site.js) quanto pelo painel (admin.js).
   ========================================================================== */

const TYPE_LABELS  = { livro: 'Livro', monografia: 'Monografia', liturgico: 'Litúrgico' };
const TYPE_ACCENTS = { livro: '#d4af37', monografia: '#a29c8f', liturgico: '#4d7ea8' };
const TYPE_SIGILS  = { livro: 'ᛟ', monografia: 'ᚠ', liturgico: 'ᚨ' };

function esc(str){
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}

function normalize(str){
  return String(str ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
}

function slugify(str){
  const s = normalize(str).replace(/[^a-z0-9]+/g, '-').replace(/(^-+|-+$)/g, '');
  return s || ('colecao-' + Date.now().toString(36));
}

function formatDateBR(isoDate){
  if(!isoDate) return '';
  const parts = String(isoDate).split('-');
  if(parts.length !== 3) return isoDate;
  const [y, m, d] = parts;
  return `${d}/${m}/${y}`;
}

function formatBRL(v){
  return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function isPromoWithinWindow(promo){
  if(!promo.startDate && !promo.endDate) return true;
  const now = new Date();
  if(promo.startDate && now < new Date(promo.startDate)) return false;
  if(promo.endDate){
    const end = new Date(promo.endDate);
    end.setHours(23,59,59,999);
    if(now > end) return false;
  }
  return true;
}

function getPricing(volume){
  const base = Number(volume.price) || 0;
  const promo = volume.promotion;
  if(!promo || !promo.active || !isPromoWithinWindow(promo)){
    return { original: base, final: base, hasPromo: false, label: '' };
  }
  let final = base;
  if(promo.type === 'percent'){
    final = base * (1 - (Number(promo.value) || 0) / 100);
  } else if(promo.type === 'fixed'){
    final = base - (Number(promo.value) || 0);
  }
  final = Math.max(Math.round(final * 100) / 100, 0);
  return { original: base, final, hasPromo: final < base, label: promo.label || '' };
}

function getAllVolumes(collections){
  const list = [];
  collections.forEach(col => {
    (col.volumes || []).forEach(vol => list.push({ vol, col }));
  });
  return list;
}

function findVolumeIn(collections, volId){
  for(const col of collections){
    const vol = (col.volumes || []).find(v => v.id === volId);
    if(vol) return { vol, col };
  }
  return null;
}

function setDeep(obj, path, value){
  const parts = path.split('.');
  let cur = obj;
  for(let i = 0; i < parts.length - 1; i++){
    if(!cur[parts[i]]) cur[parts[i]] = {};
    cur = cur[parts[i]];
  }
  cur[parts[parts.length - 1]] = value;
}
