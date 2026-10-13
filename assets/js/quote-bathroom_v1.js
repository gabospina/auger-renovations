// assets/js/quote-bathroom.js

let availableCatalog = [];

let MARKUP_RATE = 0.15;

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

// Fetch materials directly on script execution
async function initCatalogAutocomplete() {
  try {
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
          category: item.category || '',
          unit: item.unit || 'unité',
          base_price: base,
          billed_price: parseFloat(billed.toFixed(2))
        };

      });
      console.log('Catalogue chargé pour autocomplétion:', availableCatalog.length, 'articles');
        console.log(availableCatalog);
    }
  } catch (err) {
    console.error('Erreur chargement catalogue:', err);
  }
}

// BELOW Attach custom floating autocomplete dropdown to any input field
function attachAutocomplete(input) {
  // if (!input || input.dataset.hasAutocomplete) return;
  if (!input) return;

  input.dataset.hasAutocomplete = 'true';
  input.setAttribute('autocomplete', 'off');

  // Wrap input in a relative container if not already done
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

  // Create suggestion box
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

  // Show filtered suggestions as user types
  input.addEventListener('input', () => {
    const term = input.value.trim().toLowerCase();
    if (!term || availableCatalog.length === 0) {
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }

    // Filter items matching ANY keyword typed
    const matches = availableCatalog.filter(item => {
      
      const targetText = `${item.name} ${item.id} ${item.category}`.toLowerCase();
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
          <span style="display: block; font-size: 11px; color: #777;">Code: ${item.id} (${item.category})</span>
        </div>
        <div style="text-align: right; white-space: nowrap;">
          <strong style="color: #15803d; font-size: 13px;">${item.billed_price.toFixed(2)} $</strong>
          <small style="color: #888; display: block; font-size: 10px;">/${item.unit}</small>
        </div>
      `;

      opt.addEventListener('mouseenter', () => { opt.style.backgroundColor = '#f3f4f6'; });
      opt.addEventListener('mouseleave', () => { opt.style.backgroundColor = '#ffffff'; });

      // When clicked, auto-fill unit price and trigger recalculation
      opt.addEventListener('mousedown', (e) => {
        e.preventDefault(); // Prevents input blur before click registers
        input.value = item.name;
        
        const row = input.closest('.quote-row');
        if (row) {
          const unitInput = row.querySelector('.mat-unit');
          const qtyInput = row.querySelector('.mat-qty');
          if (unitInput) unitInput.value = item.billed_price.toFixed(2);
          if (qtyInput && (!qtyInput.value || parseFloat(qtyInput.value) === 0)) qtyInput.value = 1;
          recalcSummary();
        }
        box.style.display = 'none';
      });

      box.appendChild(opt);
    });

    box.style.display = 'block';
  });

  // Hide on blur
  input.addEventListener('blur', () => {
    setTimeout(() => { box.style.display = 'none'; }, 200);
  });

  // Show suggestions again on focus if input has value
  input.addEventListener('focus', () => {
    if (input.value.trim().length > 0) {
      input.dispatchEvent(new Event('input'));
    }
  });
}

function formatMoney(value) {
  const num = typeof value === 'number' ? value : parseFloat(value) || 0;
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(num) + ' $';
}

function recalcMaterials() {
  const rows = document.querySelectorAll('#materials-list .quote-row');
  let total = 0;
  rows.forEach(row => {
    const qty = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const unit = parseFloat(row.querySelector('.mat-unit')?.value) || 0;
    const rowTotal = qty * unit;
    const totalEl = row.querySelector('.mat-total');
    if (totalEl) totalEl.textContent = formatMoney(rowTotal);
    total += rowTotal;
  });
  const el = document.getElementById('materials-total');
  if (el) el.textContent = formatMoney(total);
  return total;
}

function recalcLabor() {
  const rows = document.querySelectorAll('#labor-list .quote-row');
  let total = 0;
  rows.forEach(row => {
    const hours = parseFloat(row.querySelector('.lab-hours')?.value) || 0;
    const rate = parseFloat(row.querySelector('.lab-rate')?.value) || 0;
    const rowTotal = hours * rate;
    const totalEl = row.querySelector('.lab-total');
    if (totalEl) totalEl.textContent = formatMoney(rowTotal);
    total += rowTotal;
  });
  const el = document.getElementById('labor-total');
  if (el) el.textContent = formatMoney(total);
  return total;
}

// Helper to look up an item's billed price by fuzzy keyword matching
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
  // Fallback: apply 15% to provided fallback base price
  const billedFallback = parseFloat((fallbackBasePrice * (1 + MARKUP_RATE)).toFixed(2));
  return { name: null, price: billedFallback };
}

function printDetailedBreakdown() {
  document.body.classList.remove('print-quote-only');
  document.body.classList.add('print-breakdown-only');
  window.print();
}

function printClientQuote() {
  generatePreview();
  document.body.classList.remove('print-breakdown-only');
  document.body.classList.add('print-quote-only');
  window.print();
}

function recalcSummary() {
  const mat = recalcMaterials();
  const lab = recalcLabor();
  const subtotal = mat + lab;

  // Quebec Separate Taxes
  const tps = subtotal * 0.05;
  const tvq = subtotal * 0.09975;
  const grand = subtotal + tps + tvq;

  const subEl = document.getElementById('subtotal');
  const tpsEl = document.getElementById('tps');
  const tvqEl = document.getElementById('tvq');
  const grandEl = document.getElementById('grand-total');

  if (subEl) subEl.textContent = formatMoney(subtotal);
  if (tpsEl) tpsEl.textContent = formatMoney(tps);
  if (tvqEl) tvqEl.textContent = formatMoney(tvq);
  if (grandEl) grandEl.textContent = formatMoney(grand);
}

// DELETE LIGNE
function setupRowDeletion() {

  document.addEventListener('click', (e) => {

    const deleteBtn = e.target.closest('.row-delete');
    if (!deleteBtn) return;

    const row = deleteBtn.closest('.quote-row');
    if (!row) return;

    const materialsList = row.parentElement.id === 'materials-list';
    const laborList = row.parentElement.id === 'labor-list';

    if (materialsList) {

      const rows = document.querySelectorAll('#materials-list .quote-row');

      if (rows.length <= 1) {
        alert('Au moins une ligne de matériaux doit demeurer.');
        return;
      }
    }

    if (laborList) {

      const rows = document.querySelectorAll('#labor-list .quote-row');

      if (rows.length <= 1) {
        alert('Au moins une ligne de main-d’œuvre doit demeurer.');
        return;
      }
    }

    row.remove();

    recalcSummary();
  });
}

// GENERATE INVOICE
function generatePreview() {
  const name = document.getElementById('client-name')?.value || 'Client';
  const address = document.getElementById('client-address')?.value || '—';
  const email = document.getElementById('client-email')?.value || '—';
  const phone = document.getElementById('client-phone')?.value || '—';

  const bathType = document.getElementById('bath-type')?.value || '—';
  const bathSize = document.getElementById('bath-size')?.value || '—';
  const bathNotes = document.getElementById('bath-notes')?.value || '—';

  const matTotal = document.getElementById('materials-total')?.textContent || '0.00 $';
  const labTotal = document.getElementById('labor-total')?.textContent || '0.00 $';
  const subtotal = document.getElementById('subtotal')?.textContent || '0.00 $';
  const tps = document.getElementById('tps')?.textContent || '0.00 $';
  const tvq = document.getElementById('tvq')?.textContent || '0.00 $';
  const grand = document.getElementById('grand-total')?.textContent || '0.00 $';

  const preview = document.getElementById('quote-preview');
  if (!preview) return;

  const todayStr = new Date().toISOString().split('T')[0];
  const logoUrl = '../assets/images/Auger_Logo.png';

  preview.innerHTML = `
    <div class="invoice-container" style="background: #ffffff; padding: 12px 25px 25px 25px; border: 1px solid #d0d7de; border-radius: 8px;">
      
      <!-- TOP HEADER: FULL-COLOR LOGO & DETAILS -->
      <div style="margin-bottom: 16px; padding-bottom: 14px; border-bottom: 2px solid #e5e7eb;">
        
        <!-- Full Color Logo (tight to top border) -->
        <div style="text-align: center; margin-bottom: 10px; margin-top: 0;">
          <img src="${logoUrl}" alt="Auger Rénovation" style="max-height: 95px; width: auto; display: inline-block; object-fit: contain;">
        </div>

        <!-- Info row below photo: Company info left, Official Quote title right -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
          <div style="color: #374151; font-size: 13px; line-height: 1.5;">
            Habitations A Inc. | RBQ : 58392747-01<br>
            514-236-7959 | auger.renovation@gmail.com
          </div>
          <div style="text-align: right;">
            <div style="color: #b45309; font-size: 17px; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 2px;">SOUMISSION OFFICIELLE</div>
            <div style="color: #374151; font-size: 13px; font-weight: 600;">Date : ${todayStr}</div>
          </div>
        </div>

      </div>

      <!-- CLIENT & PROJECT DETAILS -->
      <div style="display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 13px; line-height: 1.6;">
        <div>
          <div style="color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700; margin-bottom: 2px;">Client</div>
          <strong style="font-size: 14px; color: #111;">${name}</strong><br>
          <span>${address}</span><br>
          <span>${email}</span> • <span>${phone}</span>
        </div>
        <div style="text-align: right;">
          <div style="color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700; margin-bottom: 2px;">Projet</div>
          <strong style="font-size: 14px; color: #111;">Rénovation salle de bain (${bathType})</strong><br>
          <span>Superficie approx. : ${bathSize} pi²</span>
        </div>
      </div>

      ${bathNotes ? `
      <div style="margin-bottom: 16px; background: #f9fafb; padding: 10px 14px; border-radius: 6px; border: 1px solid #f3f4f6;">
        <strong style="font-size: 12px; color: #374151; text-transform: uppercase;">Notes & Portée des travaux :</strong>
        <p style="margin: 4px 0 0 0; color: #4b5563; font-size: 13px;">${bathNotes}</p>
      </div>` : ''}

      <!-- PRICING BREAKDOWN -->
      <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px;">
        <thead>
          <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb; text-align: left;">
            <th style="padding: 10px 12px; color: #374151;">Poste budgétaire</th>
            <th style="padding: 10px 12px; text-align: right; color: #374151;">Montant</th>
          </tr>
        </thead>
        <tbody>
          <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 10px 12px; color: #1f2937;">Fourniture des matériaux sélectionnés</td>
            <td style="padding: 10px 12px; text-align: right; font-weight: 600; color: #1f2937;">${matTotal}</td>
          </tr>
          <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 10px 12px; color: #1f2937;">Main-d'œuvre qualifiée & installation</td>
            <td style="padding: 10px 12px; text-align: right; font-weight: 600; color: #1f2937;">${labTotal}</td>
          </tr>
        </tbody>
      </table>

      <!-- TAXES & TOTALS -->
      <div style="width: 280px; margin-left: auto; margin-top: 18px; font-size: 13px;">
        <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #4b5563;">
          <span>Sous-total :</span> <span>${subtotal}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #6b7280;">
          <span>TPS (5.000%) :</span> <span>${tps}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #6b7280;">
          <span>TVQ (9.975%) :</span> <span>${tvq}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 8px 0; font-weight: 800; font-size: 16px; border-top: 2px solid #111; color: #111; margin-top: 4px;">
          <span>TOTAL :</span> <span>${grand}</span>
        </div>
      </div>
      
      <!-- FOOTER NOTE -->
      <div style="margin-top: 20px; font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 8px;">
        * Cette soumission est valide pour une durée de 30 jours à compter de sa date d'émission.
      </div>

    </div>
  `;
}

// assets/js/quote-bathroom.js

async function sendQuote() {
  const sendBtn = document.getElementById('send-quote');
  if (!sendBtn) return;

  // 1. Gather Client & Project Details
  const requestId = new URLSearchParams(window.location.search).get('request_id') || 'REQ-DEV';
  const clientName = document.getElementById('client-name')?.textContent.trim() || 'Client';
  const clientEmail = document.getElementById('client-email')?.textContent.trim() || '';
  const clientPhone = document.getElementById('client-phone')?.textContent.trim() || '';
  const clientAddress = document.getElementById('client-address')?.textContent.trim() || '';

  // 2. Gather Material Rows
  const materials = [];
  document.querySelectorAll('#materials-list .quote-row').forEach(row => {
    const desc = row.querySelector('.mat-desc')?.value.trim();
    const qty = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const unit = parseFloat(row.querySelector('.mat-unit')?.value) || 0;
    if (desc && qty > 0) {
      materials.push({ desc, qty, unit, total: qty * unit });
    }
  });

  // 3. Gather Labor Rows
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

  // 4. Calculate Financial Totals
  const matTotal = materials.reduce((sum, item) => sum + item.total, 0);
  const labTotal = labor.reduce((sum, item) => sum + item.total, 0);
  const subtotal = matTotal + labTotal;
  const tps = subtotal * 0.05;
  const tvq = subtotal * 0.09975;
  const grandTotal = subtotal + tps + tvq;

  const payload = {
    request_id: requestId,
    client: {
      name: clientName,
      email: clientEmail,
      phone: clientPhone,
      address: clientAddress
    },
    materials: materials,
    labor: labor,
    totals: {
      materials: matTotal,
      labor: labTotal,
      subtotal: subtotal,
      tps: tps,
      tvq: tvq,
      grand_total: grandTotal
    }
  };

  // UI Feedback
  const originalText = sendBtn.innerHTML;
  sendBtn.disabled = true;
  sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Génération du devis...';

  try {
    const apiPath = window.location.pathname.includes('/admin/')
      ? 'send-quote.php'
      : 'admin/send-quote.php';

    const res = await fetch(apiPath, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await res.json();

    if (result.success) {
      // Open generated quote / invoice in a new browser tab for printing or downloading
      if (result.view_url) {
        window.open(result.view_url, '_blank');
      }
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

// Prefill room calculations using live catalog pricing (+15% markup)
function prefillFromClientRequest() {
  const spec = window.CURRENT_PROJECT_SPEC || { sqft: 0, workItems: [] };
  const sqft = spec.sqft || 80;
  const work = spec.workItems || [];

  const matList = document.getElementById('materials-list');
  const labList = document.getElementById('labor-list');
  if (!matList || !labList) return;

  matList.innerHTML = '';
  labList.innerHTML = '';

  const materialsToAdd = [];
  const laborToAdd = [];

  // Live lookup for standard materials with fallback
  const durock = getCatalogBilledPrice(['durock'], 17.47);
  const kerdi = getCatalogBilledPrice(['kerdi', 'membrane'], 149.00);
  const sheetrock = getCatalogBilledPrice(['sheetrock'], 18.98);

  // Always: Basic plumbing supplies
  materialsToAdd.push({
    desc: 'Fournitures de plomberie de base, raccords PEX & évacuation',
    qty: 1,
    unit: parseFloat((220.00 * (1 + MARKUP_RATE)).toFixed(2))
  });

  const approxWallSqft = Math.round(Math.sqrt(sqft) * 4 * 8);
  const drywallSheets = Math.max(4, Math.ceil(approxWallSqft / 32));

  const hasShower = work.some(w => w.toLowerCase().includes('douche'));
  const hasVanity = work.some(w => w.toLowerCase().includes('vanit'));
  const hasToilet = work.some(w => w.toLowerCase().includes('toilet'));
  const hasPaint = work.some(w => w.toLowerCase().includes('peint'));
  const hasFloor = work.some(w => w.toLowerCase().includes('plancher'));

  if (hasShower) {
    materialsToAdd.push(
      {
        desc: durock.name || 'Panneau de ciment Durock 1/2 po x 3 pi x 5 pi',
        qty: 6,
        unit: durock.price
      },
      {
        desc: kerdi.name || "Membrane d'étanchéité Schluter Kerdi 108 pi²",
        qty: 1,
        unit: kerdi.price
      },
      {
        desc: 'Céramique de douche & coulis scellant',
        qty: Math.round(sqft * 0.8),
        unit: parseFloat((4.50 * (1 + MARKUP_RATE)).toFixed(2))
      }
    );

    laborToAdd.push(
      {
        desc: 'Démolition douche existante et évacuation',
        hours: 6,
        rate: 0.00
      },
      {
        desc: 'Pose membrane Kerdi, ciment-board et étanchéité',
        hours: 8,
        rate: 0.00
      },
      {
        desc: 'Pose carrelage mural et coulis douche',
        hours: 14,
        rate: 0.00
      }
    );
  }

  if (drywallSheets > 0) {
    materialsToAdd.push({
      desc: sheetrock.name || 'Panneau de gypse Sheetrock 1/2 po x 4 pi x 8 pi',
      qty: drywallSheets,
      unit: sheetrock.price
    });
  }

  if (hasFloor) {
    materialsToAdd.push({
      desc: 'Carrelage ou vinyle pour plancher',
      qty: Math.round(sqft * 1.15),
      unit: parseFloat((5.50 * (1 + MARKUP_RATE)).toFixed(2))
    });

    laborToAdd.push({
      desc: 'Préparation du sous-plancher et pose de carrelage',
      hours: Math.max(6, Math.round(sqft * 0.12)),
      rate: 0.00
    });
  }

  if (hasVanity) {
    laborToAdd.push({
      desc: 'Installation vanité, robinetterie et raccordements',
      hours: 4.5,
      rate: 0.00
    });
  }

  if (hasToilet) {
    laborToAdd.push({
      desc: 'Remplacement toilette et bride de plancher',
      hours: 2,
      rate: 0.00
    });
  }

  if (hasPaint) {
    materialsToAdd.push({
      desc: 'Peinture salle de bain anti-moisissure (apprêt + 2 couches)',
      qty: 2,
      unit: parseFloat((68.00 * (1 + MARKUP_RATE)).toFixed(2))
    });

    laborToAdd.push({
      desc: 'Plâtrage, tirage de joints et peinture complète',
      hours: Math.max(8, Math.round(sqft * 0.15)),
      rate: 0.00
    });
  }

  if (laborToAdd.length === 0) {
    laborToAdd.push({
      desc: 'Travaux généraux de menuiserie et rénovation',
      hours: 12,
      rate: 0.00
    });
  }

  // Populate Materials DOM
  materialsToAdd.forEach(m => {
    const row = document.createElement('div');
    row.className = 'quote-row calc-grid';

    row.innerHTML = `
      <input type="text" class="mat-desc" list="catalog-materials" value="${m.desc}">
      <input type="number" class="mat-qty" value="${m.qty}" step="any">
      <input type="number" class="mat-unit" value="${m.unit.toFixed(2)}" step="any">
      <span class="mat-total line-total">${formatMoney(m.qty * m.unit)}</span>

      <button type="button"
              class="row-delete delete-material"
              title="Supprimer cette ligne">
        <i class="fa-solid fa-trash"></i>
      </button>
    `;

    matList.appendChild(row);
  });

  // Populate Labor DOM
  laborToAdd.forEach(l => {
    const row = document.createElement('div');
    row.className = 'quote-row calc-grid';

    row.innerHTML = `
      <input type="text" class="lab-desc" value="${l.desc}">
      <input type="number" class="lab-hours" value="${l.hours}" step="any">
      <input type="number" class="lab-rate" value="${l.rate.toFixed(2)}" step="any">
      <span class="lab-total line-total">${formatMoney(l.hours * l.rate)}</span>

      <button type="button"
              class="row-delete delete-labor"
              title="Supprimer cette ligne">
        <i class="fa-solid fa-trash"></i>
      </button>
    `;

    labList.appendChild(row);
  });

  recalcSummary();
}

// ==========================================
// TIER PRESETS VALIDATION
// ==========================================
function validateTierPresets(data) {

  const tiers = ['budget', 'standard', 'premium'];

  const safeData = {};

  tiers.forEach(tier => {

    const source = data?.[tier] || {};

    safeData[tier] = {

      label: source.label || tier,

      materials: Array.isArray(source.materials)
        ? source.materials.map(m => ({
            desc: String(m?.desc || ''),
            qty: parseFloat(m?.qty) || 0,
            unit: parseFloat(m?.unit) || 0
          }))
        : [],

      labor: Array.isArray(source.labor)
        ? source.labor.map(l => ({
            desc: String(l?.desc || ''),
            hours: parseFloat(l?.hours) || 0,
            rate: parseFloat(l?.rate) || 0
          }))
        : []
    };

  });

  return safeData;
}

// ==========================================
// 1. TIER PRESETS DEFINITION
// ==========================================
let TIER_PRESETS = {
  budget: {
    label: 'Économique',
    materials: [],
    labor: []
  },
  standard: {
    label: 'Standard',
    materials: [],
    labor: []
  },
  premium: {
    label: 'Haut de gamme',
    materials: [],
    labor: []
  }
};

async function loadTierPresets() {

  try {

    const apiPath =
      window.location.pathname.includes('/admin/')
        ? 'api-materials.php?action=get_tiers&t=' + Date.now()
        : 'admin/api-materials.php?action=get_tiers&t=' + Date.now();

    const response = await fetch(apiPath, {
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error('Erreur HTTP: ' + response.status);
    }

    const data = await response.json();

    TIER_PRESETS = validateTierPresets(data);

    console.log('Tier presets loaded:', TIER_PRESETS);

  }
  catch (err) {

    console.error(
      'Erreur chargement des niveaux de finition:',
      err
    );

    TIER_PRESETS = validateTierPresets({});
  }
}

// ==========================================
// 2. UNIFIED RECALCULATION & SUMMARY HELPER
// ==========================================
function updateCalculations() {
  let matTotal = 0;
  document.querySelectorAll('#materials-list .quote-row').forEach(row => {
    const q = parseFloat(row.querySelector('.mat-qty')?.value) || 0;
    const u = parseFloat(row.querySelector('.mat-unit')?.value) || 0;
    const total = q * u;
    const totalSpan = row.querySelector('.mat-total');
    if (totalSpan) totalSpan.textContent = total.toFixed(2) + ' $';
    matTotal += total;
  });

  let labTotal = 0;
  document.querySelectorAll('#labor-list .quote-row').forEach(row => {
    const h = parseFloat(row.querySelector('.lab-hours')?.value) || 0;
    const r = parseFloat(row.querySelector('.lab-rate')?.value) || 0;
    const total = h * r;
    const totalSpan = row.querySelector('.lab-total');
    if (totalSpan) totalSpan.textContent = total.toFixed(2) + ' $';
    labTotal += total;
  });

  const subtotal = matTotal + labTotal;
  const tps = subtotal * 0.05;
  const tvq = subtotal * 0.09975;
  const grandTotal = subtotal + tps + tvq;

  // Update DOM Display Elements
  const elMat = document.getElementById('materials-total');
  const elLab = document.getElementById('labor-total');
  const elSub = document.getElementById('subtotal');
  const elTps = document.getElementById('tps');
  const elTvq = document.getElementById('tvq');
  const elGrand = document.getElementById('grand-total');

  if (elMat) elMat.textContent = matTotal.toFixed(2) + ' $';
  if (elLab) elLab.textContent = labTotal.toFixed(2) + ' $';
  if (elSub) elSub.textContent = subtotal.toFixed(2) + ' $';
  if (elTps) elTps.textContent = tps.toFixed(2) + ' $';
  if (elTvq) elTvq.textContent = tvq.toFixed(2) + ' $';
  if (elGrand) elGrand.textContent = grandTotal.toFixed(2) + ' $';

  // Keep preview in sync if function exists
  if (typeof generatePreview === 'function') {
    generatePreview();
  }
}

// Global aliases to protect older function calls
window.recalcSummary = updateCalculations;
window.calculateTotals = updateCalculations;

// ==========================================
// 3. ROW GENERATORS
// ==========================================
function addMaterialRow(desc = '', qty = 0, unit = 0) {
  const container = document.getElementById('materials-list');
  if (!container) return;

  const row = document.createElement('div');
  row.className = 'quote-row calc-grid';

  const numQty = parseFloat(qty) || 0;
  const numUnit = parseFloat(unit) || 0;
  const lineTotal = (numQty * numUnit).toFixed(2);

  row.innerHTML = `
    <input type="text"
           class="mat-desc"
           list="catalog-materials"
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

    <span class="mat-total line-total">${lineTotal} $</span>

    <button type="button"
            class="row-delete delete-material"
            title="Supprimer cette ligne">
      <i class="fa-solid fa-trash"></i>
    </button>
  `;

  // Attach autocomplete if available
  const inputDesc = row.querySelector('.mat-desc');
  if (typeof attachAutocomplete === 'function') {
    attachAutocomplete(inputDesc);
  }

  // Bind trash delete
  const deleteBtn = row.querySelector('.delete-material');
  deleteBtn.addEventListener('click', () => {
    row.remove();
    if (container.children.length === 0) {
      addMaterialRow();
    }
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
  const lineTotal = (numHours * numRate).toFixed(2);

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

    <span class="lab-total line-total">${lineTotal} $</span>

    <button type="button"
            class="row-delete delete-labor"
            title="Supprimer cette ligne">
      <i class="fa-solid fa-trash"></i>
    </button>
  `;

  const deleteBtn = row.querySelector('.delete-labor');
  deleteBtn.addEventListener('click', () => {
    row.remove();
    if (container.children.length === 0) {
      addLaborRow();
    }
    updateCalculations();
  });

  container.appendChild(row);
}

// ==========================================
// 4. PRESET TIER TOGGLE (FIXED FOR SWITCHING)
// ==========================================
function applyPresetTier(tierKey) {
  const preset = TIER_PRESETS?.[tierKey];

  if (!preset) {

    console.error(
      'Preset introuvable:',
      tierKey
    );

    return;
  }

  // 1. Toggle Button Active Class
  document.querySelectorAll('.btn-tier').forEach(b => b.classList.remove('active'));
  const activeBtn = document.getElementById(`tier-btn-${tierKey}`);
  if (activeBtn) activeBtn.classList.add('active');

  // 2. Safe rate resolution (guaranteed never to crash with NaN/undefined)
  let activeRate = 0.15;
  if (typeof currentMarkupRate === 'number' && !isNaN(currentMarkupRate)) {
    activeRate = currentMarkupRate;
  } else if (typeof MARKUP_RATE === 'number' && !isNaN(MARKUP_RATE)) {
    activeRate = MARKUP_RATE;
  }

  // 3. Clear & Populate Materials
  const matContainer = document.getElementById('materials-list');
  if (matContainer) {
    matContainer.innerHTML = '';
    if (Array.isArray(preset.materials) && preset.materials.length > 0) {
      (preset.materials || []).forEach(m => {
        const billedUnit = (parseFloat(m.unit) || 0) * (1 + activeRate);
        addMaterialRow(m.desc, m.qty, billedUnit);
      });
    } else {
      addMaterialRow();
    }
  }

  // 4. Clear & Populate Labor
  const labContainer = document.getElementById('labor-list');
  if (labContainer) {
    labContainer.innerHTML = '';
    if (Array.isArray(preset.labor) && preset.labor.length > 0) {
      (preset.labor || []).forEach(l => {
        addLaborRow(l.desc, l.hours, l.rate);
      });
    } else {
      addLaborRow();
    }
  }

  // 5. Update All Calculations
  updateCalculations();
}

// ==========================================
// 5. DYNAMIC ROWS & INPUT EVENT DELEGATION
// ==========================================
function setupDynamicRows() {
  const addMatBtn = document.getElementById('add-material');
  if (addMatBtn) {
    // Clone and replace to prevent duplicate click listeners
    const newMatBtn = addMatBtn.cloneNode(true);
    addMatBtn.parentNode.replaceChild(newMatBtn, addMatBtn);
    newMatBtn.addEventListener('click', () => {
      addMaterialRow();
      const list = document.getElementById('materials-list');
      const lastInput = list.lastElementChild?.querySelector('.mat-desc');
      if (lastInput) lastInput.focus();
    });
  }

  const addLabBtn = document.getElementById('add-labor');
  if (addLabBtn) {
    const newLabBtn = addLabBtn.cloneNode(true);
    addLabBtn.parentNode.replaceChild(newLabBtn, addLabBtn);
    newLabBtn.addEventListener('click', () => {
      addLaborRow();
      const list = document.getElementById('labor-list');
      const lastInput = list.lastElementChild?.querySelector('.lab-desc');
      if (lastInput) lastInput.focus();
    });
  }

  // Single delegated listener for all number inputs
  document.addEventListener('input', (e) => {
    if (e.target.type === 'file') return;
    if (
      e.target.classList.contains('mat-qty') ||
      e.target.classList.contains('mat-unit') ||
      e.target.classList.contains('lab-hours') ||
      e.target.classList.contains('lab-rate')
    ) {
      updateCalculations();
    }
  });

  // Attach autocomplete to existing items
  document.querySelectorAll('#materials-list .mat-desc').forEach(input => {
    if (typeof attachAutocomplete === 'function') attachAutocomplete(input);
  });
}

// Initial bootstrap on load
document.addEventListener('DOMContentLoaded', async () => {
  setupDynamicRows();
  await loadTierPresets();
  applyPresetTier('budget');
});

// ======================

// Auto-fill price and unit when item is typed or picked
function setupCatalogAutoFill() {
  const handleAutoFill = (e) => {
    if (!e.target.classList.contains('mat-desc')) return;

    const val = e.target.value.trim().toLowerCase();
    if (!val || availableCatalog.length === 0) return;

    // Match full name, or match startsWith, or match includes if length >= 4
    const match = availableCatalog.find(m => {
      const n = (m.name || '').toLowerCase();
      return n === val || n.startsWith(val) || (val.length >= 4 && n.includes(val));
    });

    if (match) {
      const row = e.target.closest('.quote-row');
      if (row) {
        const unitInput = row.querySelector('.mat-unit');
        const qtyInput = row.querySelector('.mat-qty');

        if (unitInput) {
          unitInput.value = match.billed_price.toFixed(2);
        }
        if (qtyInput && (!qtyInput.value || parseFloat(qtyInput.value) === 0)) {
          qtyInput.value = 1;
        }

        recalcSummary();
      }
    }
  };

  document.addEventListener('input', handleAutoFill);
  document.addEventListener('change', handleAutoFill);
}

// set up upload photos in form when a quote
function setupPhotoPreviews() {
  function handleFileInput(inputId, gridId) {
    const input = document.getElementById(inputId);
    const grid = document.getElementById(gridId);

    if (!input || !grid) return;

    input.addEventListener('change', () => {
      grid.innerHTML = ''; // Reset container on new selection

      const files = Array.from(input.files);
      files.forEach(file => {
        if (!file.type.startsWith('image/')) return;

        const thumbWrapper = document.createElement('div');
        thumbWrapper.className = 'photo-thumb-item';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = file.name;
        img.title = file.name;

        // Clean up object URLs when the image finishes loading
        img.onload = () => URL.revokeObjectURL(img.src);

        thumbWrapper.appendChild(img);
        grid.appendChild(thumbWrapper);
      });
    });
  }

  handleFileInput('photo-current', 'photo-current-grid');
  handleFileInput('photo-desired', 'photo-desired-grid');
}

document.addEventListener('DOMContentLoaded', async () => {
  // 1. Fetch materials catalog first
  await initCatalogAutocomplete();

  // 2. Setup dynamic rows, autocomplete triggers, and financial calculations
  setupDynamicRows();
  if (typeof setupCatalogAutoFill === 'function') {
    setupCatalogAutoFill();
  }
  setupRowDeletion();
  recalcSummary();

  // 3. Setup photo upload thumbnail previews (handles both multi-input and single-grid layouts)
  if (typeof setupPhotoPreviews === 'function') {
    setupPhotoPreviews();
  }

  const projectPhotosInput = document.getElementById('project-photos');
  const projectPhotosGrid = document.getElementById('photo-preview-grid');

  if (projectPhotosInput && projectPhotosGrid) {
    projectPhotosInput.addEventListener('change', () => {
      projectPhotosGrid.innerHTML = '';

      const files = Array.from(projectPhotosInput.files);
      files.forEach(file => {
        if (!file.type.startsWith('image/')) return;

        const thumbWrapper = document.createElement('div');
        thumbWrapper.style.position = 'relative';
        thumbWrapper.style.width = '75px';
        thumbWrapper.style.height = '75px';
        thumbWrapper.style.borderRadius = '8px';
        thumbWrapper.style.overflow = 'hidden';
        thumbWrapper.style.boxShadow = '0 2px 6px rgba(0,0,0,0.15)';
        thumbWrapper.style.border = '1px solid #ddd';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = file.name;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'cover';
        img.onload = () => URL.revokeObjectURL(img.src);

        thumbWrapper.appendChild(img);
        projectPhotosGrid.appendChild(thumbWrapper);
      });
    });
  }

  // 4. Attach action button listeners
  const generateBtn = document.getElementById('generate-quote');
  if (generateBtn) {
    generateBtn.addEventListener('click', () => {
      recalcSummary();
      generatePreview();
    });
  }

  const prefillBtn = document.getElementById('btn-prefill-quote');
  if (prefillBtn) {
    prefillBtn.addEventListener('click', () => {
      prefillFromClientRequest();
      if (typeof attachAutocomplete === 'function') {
        document.querySelectorAll('#materials-list .mat-desc').forEach(attachAutocomplete);
      }
    });
  }

  const sendBtn = document.getElementById('send-quote');
  if (sendBtn) {
    sendBtn.addEventListener('click', sendQuote);
  }
});
