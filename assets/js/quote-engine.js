// assets/js/quote-engine.js
// Shared calculation, catalog autocomplete, and invoice engine for Auger Rénovation

let availableCatalog = [];
let MARKUP_RATE = 0.15;

// 1. SETTINGS & CATALOG INITIALIZATION
async function loadMarkupSetting() {
  const apiPath = window.location.pathname.includes('/admin/') 
    ? 'api-materials.php?action=get_settings' 
    : 'admin/api-materials.php?action=get_settings';
  try {
    const res = await fetch(apiPath + '&t=' + Date.now(), { cache: 'no-store' });
    const settings = await res.json();
    if (settings && typeof settings.markup_rate !== 'undefined') {
      MARKUP_RATE = parseFloat(settings.markup_rate);
    }
  } catch (err) {
    console.warn('Impossible de charger le taux de marge personnalisé, repli sur 15%.');
  }
}

async function initCatalogAutocomplete() {
  try {
    await loadMarkupSetting();
    const apiPath = window.location.pathname.includes('/admin/') 
      ? 'api-materials.php?action=list' 
      : 'admin/api-materials.php?action=list';

    const res = await fetch(apiPath + '&t=' + Date.now(), { cache: 'no-store' });
    const data = await res.json();
    
    if (Array.isArray(data)) {
      availableCatalog = data.map(item => {
        const base = parseFloat(item.price) || 0;
        const billed = base * (1 + MARKUP_RATE);

        return {
          id: item.id || '',
          name: item.name || '',
          supplier: item.supplier || 'Home Depot',
          category: item.category || '',
          unit: item.unit || 'unité',
          base_price: base,
          billed_price: parseFloat(billed.toFixed(2))
        };
      });
    }
  } catch (err) {
    console.error('Erreur chargement catalogue:', err);
  }
}

// 2. FORMATTING & CORE CALCULATIONS
function formatMoney(value) {
  const num = typeof value === 'number' ? value : parseFloat(value) || 0;
  return new Intl.NumberFormat('fr-CA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(num) + ' $';
}

function updateCalculations() {
  let matTotal = 0;
  document.querySelectorAll('#materials-list .quote-row').forEach(row => {
    const q = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const u = parseFloat(row.querySelector('.mat-unit')?.value) || 0;
    const total = q * u;
    const totalSpan = row.querySelector('.mat-total');
    if (totalSpan) totalSpan.textContent = formatMoney(total);
    matTotal += total;
  });

  let labTotal = 0;
  document.querySelectorAll('#labor-list .quote-row').forEach(row => {
    const h = parseFloat(row.querySelector('.lab-hours')?.value) || 0;
    const r = parseFloat(row.querySelector('.lab-rate')?.value) || 0;
    const total = h * r;
    const totalSpan = row.querySelector('.lab-total');
    if (totalSpan) totalSpan.textContent = formatMoney(total);
    labTotal += total;
  });

  const subtotal = matTotal + labTotal;
  const tps = subtotal * 0.05;
  const tvq = subtotal * 0.09975;
  const grandTotal = subtotal + tps + tvq;

  const elMat = document.getElementById('materials-total');
  const elLab = document.getElementById('labor-total');
  const elSub = document.getElementById('subtotal');
  const elTps = document.getElementById('tps');
  const elTvq = document.getElementById('tvq');
  const elGrand = document.getElementById('grand-total');

  if (elMat) elMat.textContent = formatMoney(matTotal);
  if (elLab) elLab.textContent = formatMoney(labTotal);
  if (elSub) elSub.textContent = formatMoney(subtotal);
  if (elTps) elTps.textContent = formatMoney(tps);
  if (elTvq) elTvq.textContent = formatMoney(tvq);
  if (elGrand) elGrand.textContent = formatMoney(grandTotal);

  if (typeof generatePreview === 'function') {
    generatePreview();
  }
}

window.recalcSummary = updateCalculations;
window.calculateTotals = updateCalculations;

// 3. AUTOCOMPLETE COMPONENT
function attachAutocomplete(input) {
  if (!input) return;

  input.dataset.hasAutocomplete = 'true';
  input.setAttribute('autocomplete', 'off');

  let wrapper = input.parentElement;
  if (!wrapper.classList.contains('autocomplete-wrapper')) {
    const parent = input.parentNode;
    const div = document.createElement('div');
    div.className = 'autocomplete-wrapper';
    div.style.position = 'relative';
    div.style.width = '100%';
    parent.insertBefore(div, input);
    div.appendChild(input);
    wrapper = div;
  }

  let box = wrapper.querySelector('.autocomplete-dropdown');
  if (!box) {
    box = document.createElement('div');
    box.className = 'autocomplete-dropdown';
    box.style.position = 'absolute';
    box.style.top = '100%';
    box.style.left = '0';
    box.style.right = '0';
    box.style.backgroundColor = '#ffffff';
    box.style.border = '1px solid #c3c4c7';
    box.style.borderRadius = '4px';
    box.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    box.style.zIndex = '9999';
    box.style.maxHeight = '220px';
    box.style.overflowY = 'auto';
    box.style.display = 'none';
    wrapper.appendChild(box);
  }

  input.addEventListener('input', () => {
    const term = input.value.trim().toLowerCase();
    if (!term || availableCatalog.length === 0) {
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }

    const matches = availableCatalog.filter(item => {
      const targetText = `${item.name} ${item.id} ${item.category} ${item.supplier}`.toLowerCase();
      return targetText.includes(term);
    });

    if (matches.length === 0) {
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }

    box.innerHTML = '';
    matches.forEach(item => {
      const opt = document.createElement('div');
      opt.style.padding = '8px 12px';
      opt.style.cursor = 'pointer';
      opt.style.borderBottom = '1px solid #f0f0f0';
      opt.style.fontSize = '13px';
      opt.style.display = 'flex';
      opt.style.justifyContent = 'space-between';
      opt.style.alignItems = 'center';

      opt.innerHTML = `
        <div style="text-align: left; padding-right: 10px;">
          <strong style="color: #111;">${item.name}</strong>
          <span style="display: block; font-size: 11px; color: #777;">${item.supplier} • Code: ${item.id} (${item.category})</span>
        </div>
        <div style="text-align: right; white-space: nowrap;">
          <strong style="color: #15803d; font-size: 13px;">${item.billed_price.toFixed(2)} $</strong>
          <small style="color: #888; display: block; font-size: 10px;">/${item.unit}</small>
        </div>
      `;

      opt.addEventListener('mouseenter', () => { opt.style.backgroundColor = '#f3f4f6'; });
      opt.addEventListener('mouseleave', () => { opt.style.backgroundColor = '#ffffff'; });

      opt.addEventListener('mousedown', (e) => {
        e.preventDefault();
        input.value = item.name;
        
        const row = input.closest('.quote-row');
        if (row) {
          const unitInput = row.querySelector('.mat-unit');
          const qtyInput = row.querySelector('.mat-qty');
          if (unitInput) unitInput.value = item.billed_price.toFixed(2);
          if (qtyInput && (!qtyInput.value || parseFloat(qtyInput.value) === 0)) qtyInput.value = 1;
          updateCalculations();
        }
        box.style.display = 'none';
      });

      box.appendChild(opt);
    });

    box.style.display = 'block';
  });

  input.addEventListener('blur', () => {
    setTimeout(() => { box.style.display = 'none'; }, 200);
  });

  input.addEventListener('focus', () => {
    if (input.value.trim().length > 0) {
      input.dispatchEvent(new Event('input'));
    }
  });
}

function getCatalogBilledPrice(keywords, fallbackBasePrice) {
  const words = Array.isArray(keywords) ? keywords : [keywords];
  const found = availableCatalog.find(item => {
    const name = item.name.toLowerCase();
    const id = (item.id || '').toLowerCase();
    return words.every(w => name.includes(w.toLowerCase()) || id.includes(w.toLowerCase()));
  });

  if (found && found.billed_price > 0) {
    return { name: found.name, price: found.billed_price };
  }
  const billedFallback = parseFloat((fallbackBasePrice * (1 + MARKUP_RATE)).toFixed(2));
  return { name: null, price: billedFallback };
}

// 4. ROW BUILDERS
function addMaterialRow(desc = '', qty = 0, unit = 0) {
  const container = document.getElementById('materials-list');
  if (!container) return;

  const row = document.createElement('div');
  row.className = 'quote-row calc-grid';

  const numQty = parseFloat(qty) || 0;
  const numUnit = parseFloat(unit) || 0;
  const lineTotal = formatMoney(numQty * numUnit);

  row.innerHTML = `
    <input type="text"
           class="mat-desc"
           placeholder="Sélectionner ou saisir un matériau"
           value="${desc ? String(desc).replace(/"/g, '&quot;') : ''}">

    <input type="number"
           class="mat-qty"
           placeholder="0"
           value="${numQty > 0 ? numQty : 0}"
           step="any">

    <input type="number"
           class="mat-unit"
           placeholder="0.00"
           value="${numUnit > 0 ? numUnit.toFixed(2) : '0.00'}"
           step="any">

    <span class="mat-total line-total">${lineTotal}</span>

    <button type="button"
            class="row-delete delete-material"
            title="Supprimer cette ligne">
      <i class="fa-solid fa-trash"></i>
    </button>
  `;

  attachAutocomplete(row.querySelector('.mat-desc'));

  row.querySelector('.delete-material').addEventListener('click', () => {
    row.remove();
    if (container.children.length === 0) addMaterialRow();
    updateCalculations();
  });

  container.appendChild(row);
}

function addLaborRow(desc = '', hours = 0, rate = 0) {
  const container = document.getElementById('labor-list');
  if (!container) return;

  const row = document.createElement('div');
  row.className = 'quote-row calc-grid';

  const numHours = parseFloat(hours) || 0;
  const numRate = parseFloat(rate) || 0;
  const lineTotal = formatMoney(numHours * numRate);

  row.innerHTML = `
    <input type="text"
           class="lab-desc"
           placeholder="Description du travail"
           value="${desc ? String(desc).replace(/"/g, '&quot;') : ''}">

    <input type="number"
           class="lab-hours"
           placeholder="0"
           value="${numHours > 0 ? numHours : 0}"
           step="any">

    <input type="number"
           class="lab-rate"
           placeholder="0.00"
           value="${numRate > 0 ? numRate.toFixed(2) : '0.00'}"
           step="any">

    <span class="lab-total line-total">${lineTotal}</span>

    <button type="button"
            class="row-delete delete-labor"
            title="Supprimer cette ligne">
      <i class="fa-solid fa-trash"></i>
    </button>
  `;

  row.querySelector('.delete-labor').addEventListener('click', () => {
    row.remove();
    if (container.children.length === 0) addLaborRow();
    updateCalculations();
  });

  container.appendChild(row);
}

// 5. PRINT & INVOICE TRANSMISSION
function printDetailedBreakdown() {
  document.body.classList.remove('print-quote-only');
  document.body.classList.add('print-breakdown-only');
  window.print();
}

function printClientQuote() {
  if (typeof generatePreview === 'function') generatePreview();
  document.body.classList.remove('print-breakdown-only');
  document.body.classList.add('print-quote-only');
  window.print();
}

async function sendQuote() {
  const sendBtn = document.getElementById('send-quote');
  if (!sendBtn) return;

  const requestId = new URLSearchParams(window.location.search).get('request_id') || 'REQ-DEV';
  const clientName = document.getElementById('client-name')?.textContent.trim() || 'Client';
  const clientEmail = document.getElementById('client-email')?.textContent.trim() || '';
  const clientPhone = document.getElementById('client-phone')?.textContent.trim() || '';
  const clientAddress = document.getElementById('client-address')?.textContent.trim() || '';

  const materials = [];
  document.querySelectorAll('#materials-list .quote-row').forEach(row => {
    const desc = row.querySelector('.mat-desc')?.value.trim();
    const qty = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const unit = parseFloat(row.querySelector('.mat-unit')?.value) || 0;
    if (desc && qty > 0) {
      materials.push({ desc, qty, unit, total: qty * unit });
    }
  });

  const labor = [];
  document.querySelectorAll('#labor-list .quote-row').forEach(row => {
    const desc = row.querySelector('.lab-desc')?.value.trim();
    const hours = parseFloat(row.querySelector('.lab-hours')?.value) || 0;
    const rate = parseFloat(row.querySelector('.lab-rate')?.value) || 0;
    if (desc && hours > 0) {
      labor.push({ desc, hours, rate, total: hours * rate });
    }
  });

  if (materials.length === 0 && labor.length === 0) {
    alert('Veuillez ajouter au moins un matériau ou une tâche de main-d’œuvre avant d’envoyer la soumission.');
    return;
  }

  const matTotal = materials.reduce((sum, item) => sum + item.total, 0);
  const labTotal = labor.reduce((sum, item) => sum + item.total, 0);
  const subtotal = matTotal + labTotal;
  const tps = subtotal * 0.05;
  const tvq = subtotal * 0.09975;
  const grandTotal = subtotal + tps + tvq;

  const payload = {
    request_id: requestId,
    client: { name: clientName, email: clientEmail, phone: clientPhone, address: clientAddress },
    materials,
    labor,
    totals: { materials: matTotal, labor: labTotal, subtotal, tps, tvq, grand_total: grandTotal }
  };

  const originalText = sendBtn.innerHTML;
  sendBtn.disabled = true;
  sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Génération du devis...';

  try {
    const apiPath = window.location.pathname.includes('/admin/') ? 'send-quote.php' : 'admin/send-quote.php';
    const res = await fetch(apiPath, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await res.json();
    if (result.success) {
      if (result.view_url) window.open(result.view_url, '_blank');
      alert(`Soumission #${result.quote_number} générée et enregistrée avec succès !`);
    } else {
      alert('Erreur: ' + (result.error || 'Impossible de générer la soumission.'));
    }
  } catch (err) {
    console.error('Erreur lors de l’envoi:', err);
    alert('Erreur de communication avec le serveur.');
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = originalText;
  }
}