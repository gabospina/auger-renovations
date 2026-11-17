// assets/js/quote-engine.js
// Shared calculation, catalog autocomplete, and invoice engine for Auger Rénovation

let availableCatalog = [];
let MARKUP_RATE = 0;

// Expose on window object
window.QuoteEngine = window.QuoteEngine || {};
window.QuoteEngine.markupRate = MARKUP_RATE;

window.QuoteEngine.getBilledPrice = function(baseCost) {
  const base = parseFloat(baseCost) || 0;
  return parseFloat((base * (1 + MARKUP_RATE)).toFixed(2));
};

// 1. SETTINGS & CATALOG INITIALIZATION
// 1. SETTINGS & CATALOG INITIALIZATION
async function loadMarkupSetting() {
  const apiPath = window.location.pathname.includes('/admin/') 
    ? 'api-materials.php?action=get_settings' 
    : 'admin/api-materials.php?action=get_settings';
  try {
    const res = await fetch(apiPath + '&t=' + Date.now(), { cache: 'no-store' });
    const settings = await res.json();

    if (settings && typeof settings.markup_rate !== 'undefined') {
      // Trust whatever the server returns, including 0.
      MARKUP_RATE = parseFloat(settings.markup_rate);
      window.QuoteEngine.markupRate = MARKUP_RATE;
    }

    // Propagate the warning flag so the estimate page can display it.
    if (settings && settings.settings_warning) {
      window.__SETTINGS_WARNING = settings.settings_warning;
    } else {
      window.__SETTINGS_WARNING = null;
    }

  } catch (err) {
    // Network failure only — do NOT silently apply 0.15.
    // Use 0 so nothing is overcharged; the estimate page will show
    // the same "no markup applied" warning when the calculator runs.
    MARKUP_RATE = 0;
    window.QuoteEngine.markupRate = 0;
    window.__SETTINGS_WARNING = 'no_markup_due_to_error';
    console.warn('Impossible de charger le taux de marge. Aucune marge ne sera appliquée.');
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
      window.QuoteEngine.catalog = availableCatalog;
    }
  } catch (err) {
    console.error('Erreur chargement catalogue:', err);
  }
}

// 2. FORMATTING & CORE CALCULATIONS
function formatMoney(value) {
  const num = typeof value === 'number' ? value : parseFloat(value) || 0;
  
  // Format using 'en-US' style to enforce commas for thousands and dots for decimals
  return num.toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }) + ' $';
}

function updateCalculations() {
  let matTotal = 0;
  document.querySelectorAll('#materials-list .quote-row').forEach(row => {
    const q = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const u = parseFloat((row.querySelector('.mat-unit')?.value || '0').replace(/,/g, '')) || 0;
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
window.formatMoney = formatMoney;

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

/**
 * Resolves the correct hourly rate from labor-rates.json injected via window.APP_LABOR_RATES
 */
function getRateFromJSON(desc) {
  if (!window.APP_LABOR_RATES) return 0.00; // Default to 0.00 if rates aren't loaded

  const lowerDesc = String(desc || '').toLowerCase();
  
  for (const [key, data] of Object.entries(window.APP_LABOR_RATES)) {
    const keyMatch = lowerDesc.includes(key);
    const labelMatch = data.label && lowerDesc.includes(data.label.toLowerCase());
    
    if (keyMatch || labelMatch) {
      return parseFloat(data.hourly_rate) || 0.00;
    }
  }
  
  return 0.00; // Returns 0.00 if no keyword matches, keeping manual/blank rows at zero
}

function addLaborRow(desc = '', hours = 0, rate = 0) {
  const container = document.getElementById('labor-list');
  if (!container) return;

  const numHours = parseFloat(hours) || 0;
  
  // If rate is 0 or not passed, check if we can automatically look it up from labor-rates.json based on description
  let numRate = parseFloat(rate) || 0.00;
  if (numRate === 0 && desc) {
    numRate = getRateFromJSON(desc);
  }

  const lineTotal = formatMoney(numHours * numRate);

  const row = document.createElement('div');
  row.className = 'quote-row calc-grid';

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
  const materialsTotal = document.getElementById('materials-total')?.innerText || '0.00 $';
  const laborTotal = document.getElementById('labor-total')?.innerText || '0.00 $';
  const subtotal = document.getElementById('subtotal')?.innerText || '0.00 $';
  const tps = document.getElementById('tps')?.innerText || '0.00 $';
  const tvq = document.getElementById('tvq')?.innerText || '0.00 $';
  const grandTotal = document.getElementById('grand-total')?.innerText || '0.00 $';

  const clientName = document.getElementById('client-name')?.value || 'Client';
  const clientAddress = document.getElementById('client-address')?.value || '';
  const clientEmail = document.getElementById('client-email')?.value || '';
  const clientPhone = document.getElementById('client-phone')?.value || '';

  // Detect language from <html lang="...">
  const isEn = (document.documentElement.lang || '').toLowerCase().startsWith('en');

  // Multi-language dictionary
  const txt = isEn ? {
    pageTitle: 'Detailed Worksheet | Auger Renovation',
    bannerTitle: 'DETAILED SITE WORKSHEET — Auger Renovation',
    clientLabel: 'Client',
    addressLabel: 'Address',
    emailLabel: 'Email',
    phoneLabel: 'Phone',
    sec1: '1. Materials',
    sec2: '2. Labor',
    colDesc: 'Description',
    colQty: 'Qty',
    colPrice: 'Unit Price',
    colHours: 'Hours',
    colRate: 'Rate / Hr',
    colTotal: 'Total',
    totMat: 'Materials Total',
    totLab: 'Labor Total',
    subtotal: 'Subtotal',
    tps: 'GST (5%)',
    tvq: 'QST (9.975%)',
    total: 'Total'
  } : {
    pageTitle: 'Bordereau Détaillé | Auger Rénovation',
    bannerTitle: 'BORDEREAU DÉTAILLÉ DE CHANTIER — Auger Rénovation',
    clientLabel: 'Client',
    addressLabel: 'Adresse',
    emailLabel: 'Courriel',
    phoneLabel: 'Téléphone',
    sec1: '1. Matériaux',
    sec2: '2. Main-d\'œuvre',
    colDesc: 'Description',
    colQty: 'Qté',
    colPrice: 'Prix Unitaire',
    colHours: 'Heures',
    colRate: 'Taux / Heure',
    colTotal: 'Total',
    totMat: 'Total Matériaux',
    totLab: 'Total Main-d\'œuvre',
    subtotal: 'Sous-total',
    tps: 'TPS (5%)',
    tvq: 'TVQ (9.975%)',
    total: 'Total'
  };

  const printWindow = window.open('', '_blank', 'width=850,height=1100');
  if (!printWindow) return;

  const doc = printWindow.document;
  doc.open();
  doc.write(`<!DOCTYPE html><html lang="${isEn ? 'en' : 'fr'}"><head><meta charset="UTF-8"><title>${txt.pageTitle}</title></head><body></body></html>`);
  doc.close();

  const style = doc.createElement('style');
  style.textContent = `
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #1f2937; margin: 0; padding: 30px; line-height: 1.5; background: #ffffff; }
    .logo-banner { text-align: center !important; margin-bottom: 20px !important; width: 100% !important; }
    .logo-banner img { max-height: 90px !important; width: auto !important; display: inline-block !important; }
    h1 { font-size: 20px; color: #111827; border-bottom: 2px solid #b45309; padding-bottom: 8px; margin-bottom: 20px; }
    h3 { font-size: 16px; margin-top: 25px; color: #374151; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0 20px 0; font-size: 13px; }
    th, td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    th { background: #f3f4f6; font-weight: 700; }
    .summary-box { width: 300px; margin-left: auto; font-size: 14px; margin-top: 20px; border-top: 2px solid #111827; padding-top: 10px; }
    .summary-row { display: flex; justify-content: space-between; padding: 4px 0; }
    .summary-row.grand { font-weight: 800; font-size: 16px; margin-top: 6px; }
  `;
  doc.head.appendChild(style);

  const container = doc.createElement('div');

  const materialRows = Array.from(document.querySelectorAll('#materials-list .quote-row')).map(row => {
    const desc = row.querySelector('.mat-desc')?.value || '';
    const qty = row.querySelector('.mat-qty')?.value || '0';
    const unit = parseFloat((row.querySelector('.mat-unit')?.value || '0').replace(/,/g, '')) || 0;
    const lineTotal = row.querySelector('.mat-total')?.innerText || '0.00 $';
    if (!desc) return '';
    return `<tr><td>${desc}</td><td style="text-align:center;">${qty}</td><td style="text-align:right;">${unit} $</td><td style="text-align:right;">${lineTotal}</td></tr>`;
  }).join('');

  const laborRows = Array.from(document.querySelectorAll('#labor-list .quote-row')).map(row => {
    const desc = row.querySelector('.lab-desc')?.value || '';
    const hours = row.querySelector('.lab-hours')?.value || '0';
    const rate = row.querySelector('.lab-rate')?.value || '0';
    const lineTotal = row.querySelector('.lab-total')?.innerText || '0.00 $';
    if (!desc) return '';
    return `<tr><td>${desc}</td><td style="text-align:center;">${hours}</td><td style="text-align:right;">${rate} $</td><td style="text-align:right;">${lineTotal}</td></tr>`;
  }).join('');

  container.innerHTML = `
    <div class="logo-banner">
      <img src="../assets/images/Auger_Logo.png" alt="Auger Renovation">
    </div>

    <h1>${txt.bannerTitle}</h1>
    <p style="margin-bottom: 4px;">
      <strong>${txt.clientLabel} :</strong> ${clientName}
      &nbsp;|&nbsp;
      <strong>${txt.addressLabel} :</strong> ${clientAddress}
    </p>
    <p style="margin-top: 0; margin-bottom: 20px;">
      <strong>${txt.emailLabel} :</strong> ${clientEmail || '—'}
      &nbsp;|&nbsp;
      <strong>${txt.phoneLabel} :</strong> ${clientPhone || '—'}
    </p>
    
    <h3>${txt.sec1}</h3>
    <table>
      <thead><tr><th>${txt.colDesc}</th><th style="text-align:center;">${txt.colQty}</th><th style="text-align:right;">${txt.colPrice}</th><th style="text-align:right;">${txt.colTotal}</th></tr></thead>
      <tbody>${materialRows}</tbody>
    </table>
    <div style="text-align: right; font-weight: bold;">${txt.totMat} : ${materialsTotal}</div>

    <h3>${txt.sec2}</h3>
    <table>
      <thead><tr><th>${txt.colDesc}</th><th style="text-align:center;">${txt.colHours}</th><th style="text-align:right;">${txt.colRate}</th><th style="text-align:right;">${txt.colTotal}</th></tr></thead>
      <tbody>${laborRows}</tbody>
    </table>
    <div style="text-align: right; font-weight: bold;">${txt.totLab} : ${laborTotal}</div>

    <div class="summary-box">
      <div class="summary-row"><span>${txt.subtotal} :</span><span>${subtotal}</span></div>
      <div class="summary-row"><span>${txt.tps} :</span><span>${tps}</span></div>
      <div class="summary-row"><span>${txt.tvq} :</span><span>${tvq}</span></div>
      <div class="summary-row grand"><span>${txt.total} :</span><span>${grandTotal}</span></div>
    </div>
  `;

  doc.body.appendChild(container);
  printWindow.focus();

  setTimeout(() => {
    printWindow.print();
  }, 350);
}

function printClientQuote() {
  if (typeof generatePreview === 'function') generatePreview();

  const isEn = (document.documentElement.lang || '').toLowerCase().startsWith('en');
  const alertMsg = isEn 
    ? "Please click 'Refresh Preview' first to compile the quote." 
    : "Veuillez d'abord cliquer sur « Actualiser l'aperçu » pour compiler la soumission.";
  const docTitle = isEn 
    ? "Official Quote | Auger Renovation" 
    : "Soumission Officielle | Auger Rénovation";

  const previewBox = document.getElementById('quote-preview');
  if (!previewBox || !previewBox.innerHTML.trim() || previewBox.innerHTML.includes('Cliquez') || previewBox.innerHTML.includes('Click')) {
    alert(alertMsg);
    return;
  }

  const printWindow = window.open('', '_blank', 'width=850,height=1100');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html lang="${isEn ? 'en' : 'fr'}">
    <head>
      <meta charset="UTF-8">
      <title>${docTitle}</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #1f2937; margin: 0; padding: 30px; line-height: 1.5; background: #ffffff; }
        .logo-banner img { max-height: 90px; width: auto; display: block; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-weight: 700; }
        .text-right { text-align: right; }
      </style>
    </head>
    <body>
      ${previewBox.innerHTML}
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
  }, 350);
}

async function sendQuote() {
  const sendBtn = document.getElementById('send-quote');
  if (!sendBtn) return;

  const requestId = new URLSearchParams(window.location.search).get('request_id') || 'REQ-DEV';
  const clientName = document.getElementById('client-name')?.value?.trim() || 'Client';
  const clientEmail = document.getElementById('client-email')?.value?.trim() || '';
  const clientPhone = document.getElementById('client-phone')?.value?.trim() || '';
  const clientAddress = document.getElementById('client-address')?.value?.trim() || '';

  const materials = [];
  document.querySelectorAll('#materials-list .quote-row').forEach(row => {
    const desc = row.querySelector('.mat-desc')?.value.trim();
    const qty = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const unit = row.querySelector('.mat-unit')?.value || 0;
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

      // Open the quote tab FIRST (before any blocking call),
      // so the tab is not held blank while an alert is displayed.
      if (result.view_url) {
        window.open(result.view_url, '_blank');
      }

      // Non-blocking toast — does not freeze the event loop.
      showToast(
        `Soumission #${result.quote_number} générée et enregistrée avec succès !`,
        'success'
      );

    } else {
      showToast(
        'Erreur: ' + (result.error || 'Impossible de générer la soumission.'),
        'error'
      );
    }
  } catch (err) {
    console.error('Erreur lors de l’envoi:', err);
    showToast('Erreur de communication avec le serveur.', 'error');
  } finally {
    sendBtn.disabled = false;
    sendBtn.innerHTML = originalText;
  }
}

// ==========================================================
// Non-blocking toast notification
// ==========================================================
function showToast(message, type = 'success', duration = 4000) {

  // Container (reused across calls)
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      z-index: 99999;
      pointer-events: none;
    `;
    document.body.appendChild(container);
  }

  const colors = {
    success: { bg: '#16a34a', border: '#15803d' },
    error:   { bg: '#dc2626', border: '#b91c1c' },
    info:    { bg: '#2563eb', border: '#1d4ed8' }
  };
  const palette = colors[type] || colors.info;

  const toast = document.createElement('div');
  toast.textContent = message;
  toast.style.cssText = `
    background: ${palette.bg};
    color: #ffffff;
    padding: 14px 22px;
    border-radius: 6px;
    border-left: 4px solid ${palette.border};
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    font-weight: 600;
    font-size: 14px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    pointer-events: auto;
    opacity: 0;
    transform: translateX(20px);
    transition: opacity 0.25s ease, transform 0.25s ease;
  `;

  container.appendChild(toast);

  // Animate in on the next frame
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(0)';
  });

  // Auto-dismiss
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// Expose so other modules can use it if needed
window.showToast = showToast;