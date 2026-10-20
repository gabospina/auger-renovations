// assets/js/quote-bathroom.js
// Bathroom-specific room estimator & invoice layout

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

  // 1. Detect language from <html lang="...">
  const isEn = (document.documentElement.lang || '').toLowerCase().startsWith('en');

  // 2. Language dictionary for preview labels
  const txt = isEn ? {
    title: 'OFFICIAL QUOTE',
    date: 'Date :',
    client: 'Client',
    project: 'Project',
    projectDesc: `Bathroom Renovation (${bathType})`,
    approxArea: `Approx. Area: ${bathSize} sqft`,
    notesHeader: 'Notes & Scope of Work :',
    colItem: 'Budget Item',
    colAmount: 'Amount',
    materialsRow: 'Supply of selected materials',
    laborRow: 'Skilled labor & installation',
    subtotal: 'Subtotal :',
    tps: 'GST (5.000%) :',
    tvq: 'QST (9.975%) :',
    total: 'TOTAL :',
    validity: '* This quote is valid for 30 days from its date of issuance.'
  } : {
    title: 'SOUMISSION OFFICIELLE',
    date: 'Date :',
    client: 'Client',
    project: 'Projet',
    projectDesc: `Rénovation salle de bain (${bathType})`,
    approxArea: `Superficie approx. : ${bathSize} pi²`,
    notesHeader: 'Notes & Portée des travaux :',
    colItem: 'Poste budgétaire',
    colAmount: 'Montant',
    materialsRow: 'Fourniture des matériaux sélectionnés',
    laborRow: 'Main-d\'œuvre qualifiée & installation',
    subtotal: 'Sous-total :',
    tps: 'TPS (5.000%) :',
    tvq: 'TVQ (9.975%) :',
    total: 'TOTAL :',
    validity: '* Cette soumission est valide pour une durée de 30 jours à compter de sa date d\'émission.'
  };

  preview.innerHTML = `
    <div class="invoice-container" style="background: #ffffff; padding: 12px 25px 25px 25px; border: 1px solid #d0d7de; border-radius: 8px;">
      <div style="margin-bottom: 16px; padding-bottom: 14px; border-bottom: 2px solid #e5e7eb;">
        <div style="text-align: center; margin-bottom: 10px;">
          <img src="${logoUrl}" alt="Auger Rénovation" style="max-height: 95px; width: auto; object-fit: contain;">
        </div>
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
          <div style="color: #374151; font-size: 13px; line-height: 1.5;">
            Habitations A Inc. | RBQ : 58392747-01<br>
            514-236-7959 | auger.renovation@gmail.com
          </div>
          <div style="text-align: right;">
            <div style="color: #b45309; font-size: 17px; font-weight: 800;">${txt.title}</div>
            <div style="color: #374151; font-size: 13px; font-weight: 600;">${txt.date} ${todayStr}</div>
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 13px;">
        <div>
          <div style="color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700;">${txt.client}</div>
          <strong style="font-size: 14px; color: #111;">${name}</strong><br>
          <span>${address}</span><br>
          <span>${email}</span> • <span>${phone}</span>
        </div>
        <div style="text-align: right;">
          <div style="color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700;">${txt.project}</div>
          <strong style="font-size: 14px; color: #111;">${txt.projectDesc}</strong><br>
          <span>${txt.approxArea}</span>
        </div>
      </div>

      ${bathNotes && bathNotes !== '—' ? `
      <div style="margin-bottom: 16px; background: #f9fafb; padding: 10px 14px; border-radius: 6px; border: 1px solid #f3f4f6;">
        <strong style="font-size: 12px; color: #374151; text-transform: uppercase;">${txt.notesHeader}</strong>
        <p style="margin: 4px 0 0 0; color: #4b5563; font-size: 13px;">${bathNotes}</p>
      </div>` : ''}

      <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px;">
        <thead>
          <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb; text-align: left;">
            <th style="padding: 10px 12px; color: #374151;">${txt.colItem}</th>
            <th style="padding: 10px 12px; text-align: right; color: #374151;">${txt.colAmount}</th>
          </tr>
        </thead>
        <tbody>
          <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 10px 12px; color: #1f2937;">${txt.materialsRow}</td>
            <td style="padding: 10px 12px; text-align: right; font-weight: 600;">${matTotal}</td>
          </tr>
          <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 10px 12px; color: #1f2937;">${txt.laborRow}</td>
            <td style="padding: 10px 12px; text-align: right; font-weight: 600;">${labTotal}</td>
          </tr>
        </tbody>
      </table>

      <div style="width: 280px; margin-left: auto; margin-top: 18px; font-size: 13px;">
        <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #4b5563;">
          <span>${txt.subtotal}</span> <span>${subtotal}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #6b7280;">
          <span>${txt.tps}</span> <span>${tps}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 4px 0; color: #6b7280;">
          <span>${txt.tvq}</span> <span>${tvq}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 8px 0; font-weight: 800; font-size: 16px; border-top: 2px solid #111; color: #111;">
          <span>${txt.total}</span> <span>${grand}</span>
        </div>
      </div>
      
      <div style="margin-top: 20px; font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 8px;">
        ${txt.validity}
      </div>
    </div>
  `;
}

// Core automatic estimator based on actual requested scope & room measurements
function prefillFromClientRequest() {
  const spec = window.CURRENT_PROJECT_SPEC || {};
  const length = parseFloat(spec.length) || 0;
  const width = parseFloat(spec.width) || 0;
  const ceilingHeight = parseFloat(spec.ceilingHeight) || 8;

  // Surface breakdowns
  const floorSqft = (length > 0 && width > 0) ? (length * width) : (parseFloat(spec.sqft) || 80);
  const ceilingSqft = floorSqft;
  const wallPerimeter = (length > 0 && width > 0) ? (length + width) * 2 : (Math.sqrt(floorSqft) * 4);
  const wallSqft = wallPerimeter * ceilingHeight;

  const plumbing = spec.plumbing || [];
  const electrical = spec.electrical || [];
  const shower = spec.shower || [];
  const floor = spec.floor || [];
  const painting = spec.painting || [];
  const demo = spec.demo || [];
  const work = spec.workItems || [];

  const matList = document.getElementById('materials-list');
  const labList = document.getElementById('labor-list');
  if (!matList || !labList) return;

  matList.innerHTML = '';
  labList.innerHTML = '';

  const materialsToAdd = [];
  const laborToAdd = [];

  // Checkbox detections
  const hasShower = shower.length > 0 || work.some(w => w.toLowerCase().includes('douche'));
  const hasFloor = floor.length > 0 || work.some(w => w.toLowerCase().includes('plancher'));
  const hasPaint = painting.length > 0 || work.some(w => w.toLowerCase().includes('peint'));
  const hasVanity = plumbing.includes('replace_vanity') || work.some(w => w.toLowerCase().includes('vanit'));
  const hasToilet = plumbing.includes('replace_toilet') || work.some(w => w.toLowerCase().includes('toilet'));

  // 1. Démolition (Calibrated to realistic room size)
  const demoHours = Math.max(6, Math.min(16, Math.round(floorSqft * 0.08)));
  laborToAdd.push({
    desc: `Démolition, arrachage et disposition des rebuts (${floorSqft} pi²)`,
    hours: demoHours,
    rate: 0.00
  });

  // 2. Douche & Murs de Douche (Standard 36"x48" shower = ~80 pi² of tile walls)
  if (hasShower) {
    const showerWallSqft = 80; 
    const durockSheets = Math.ceil((showerWallSqft * 1.1) / 15); // 15 pi² per 3x5 sheet

    const durockItem = getCatalogBilledPrice(['durock'], 27.97);
    materialsToAdd.push({
      desc: durockItem.name || "CGC Durock Panneau de ciment 1/2 po x 3 pi x 5 pi",
      qty: durockSheets,
      unit: durockItem.price
    });

    const kerdiItem = getCatalogBilledPrice(['kerdi'], 685.00);
    materialsToAdd.push({
      desc: kerdiItem.name || "Ensemble complet douche étanche Schluter-Kerdi",
      qty: 1,
      unit: kerdiItem.price
    });

    materialsToAdd.push({
      desc: "Carrelage mural de douche & coulis scellant",
      qty: Math.ceil(showerWallSqft * 1.15), // 15% cutting loss
      unit: parseFloat((5.50 * (1 + MARKUP_RATE)).toFixed(2))
    });

    laborToAdd.push({
      desc: "Installation base de douche, membrane étanche & panneaux Durock",
      hours: 10,
      rate: 0.00
    });

    laborToAdd.push({
      desc: `Pose de céramique murale douche & coulis (${showerWallSqft} pi²)`,
      hours: 12,
      rate: 0.00
    });
  }

  // 3. Plancher (uses floorSqft, NOT wallSqft)
  if (hasFloor) {
    const floorTilesBoxCoverage = 16; // 16 pi² per box (Calacatta 24x48)
    const floorBoxes = Math.ceil((floorSqft * 1.15) / floorTilesBoxCoverage);
    const porcelainItem = getCatalogBilledPrice(['calacatta', 'porcelaine'], 118.00);

    materialsToAdd.push({
      desc: porcelainItem.name || "Porcelaine de plancher (boîtes de 16 pi²)",
      qty: floorBoxes,
      unit: porcelainItem.price
    });

    materialsToAdd.push({
      desc: "Ciment-colle polymère et coulis pour carrelage",
      qty: Math.max(1, Math.ceil(floorSqft / 50)),
      unit: parseFloat((34.00 * (1 + MARKUP_RATE)).toFixed(2))
    });

    const floorLaborHours = Math.max(6, Math.round(floorSqft * 0.09));
    laborToAdd.push({
      desc: `Préparation sous-plancher, pose carrelage sol & coulis (${floorSqft} pi²)`,
      hours: floorLaborHours,
      rate: 0.00
    });
  }

  // 4. Murs & Gypse (Remaining walls minus shower enclosure + ceiling)
  const drywallSqft = Math.max(120, (wallSqft - (hasShower ? 80 : 0)) + ceilingSqft);
  const drywallSheets = Math.ceil((drywallSqft * 1.10) / 32); // 32 pi² per 4x8 sheet
  const sheetrockItem = getCatalogBilledPrice(['sheetrock', 'gypse'], 18.98);

  materialsToAdd.push({
    desc: sheetrockItem.name || "CGC Sheetrock 1/2 po x 4 pi x 8 pi",
    qty: drywallSheets,
    unit: sheetrockItem.price
  });

  const compoundItem = getCatalogBilledPrice(['composé', 'joint'], 37.31);
  materialsToAdd.push({
    desc: compoundItem.name || "Composé à joints tout usage CGC Sheetrock (17 L)",
    qty: Math.max(1, Math.ceil(drywallSheets / 10)),
    unit: compoundItem.price
  });

  // Plastering & Joint taping
  const drywallLaborHours = Math.max(8, Math.round(drywallSheets * 1.1));
  laborToAdd.push({
    desc: `Pose de gypse, tirage de joints 3 couches & sablage (${drywallSheets} feuilles)`,
    hours: drywallLaborHours,
    rate: 0.00
  });

  // 5. Plomberie & Appareils
  if (hasVanity) {
    const vanityItem = getCatalogBilledPrice(['vanité', 'vanity'], 829.00);
    materialsToAdd.push({
      desc: vanityItem.name || "Meuble-vanité avec comptoir & lavabo",
      qty: 1,
      unit: vanityItem.price
    });

    laborToAdd.push({
      desc: "Raccordement plomberie vanité, robinet et drain",
      hours: 4,
      rate: 0.00
    });
  }

  if (hasToilet) {
    materialsToAdd.push({
      desc: "Nouvelle toilette monobloc haute efficacité & bride",
      qty: 1,
      unit: parseFloat((280.00 * (1 + MARKUP_RATE)).toFixed(2))
    });

    laborToAdd.push({
      desc: "Installation et scellage de toilette neuve",
      hours: 2,
      rate: 0.00
    });
  }

  // Plumbing rough-ins (PEX / copper / ABS)
  materialsToAdd.push({
    desc: "Fournitures de plomberie PEX, vannes d'arrêt & raccords d'évacuation",
    qty: 1,
    unit: parseFloat((160.00 * (1 + MARKUP_RATE)).toFixed(2))
  });

  // 6. Électricité
  if (electrical.includes('heated_floor')) {
    materialsToAdd.push({
      desc: `Câble chauffant Schluter-DITRA-HEAT (${Math.round(floorSqft * 0.8)} pi²) & thermostat`,
      qty: 1,
      unit: parseFloat((390.00 * (1 + MARKUP_RATE)).toFixed(2))
    });

    laborToAdd.push({
      desc: "Pose câble chauffant et connexion sonde thermostat",
      hours: 4,
      rate: 0.00
    });
  }

  if (electrical.includes('potlights') || electrical.length > 0) {
    laborToAdd.push({
      desc: "Mise aux normes électriques, boîtiers et appareillages",
      hours: 4,
      rate: 0.00
    });
  }

  // 7. Peinture
  if (hasPaint) {
    materialsToAdd.push({
      desc: "Peinture salle de bain fini perle anti-moisissure (Gal)",
      qty: Math.max(1, Math.ceil(drywallSqft / 350)),
      unit: parseFloat((65.00 * (1 + MARKUP_RATE)).toFixed(2))
    });

    laborToAdd.push({
      desc: "Application peinture apprêt et 2 couches de finition",
      hours: Math.max(6, Math.round(floorSqft * 0.06)),
      rate: 0.00
    });
  }

  // Inject into DOM
  materialsToAdd.forEach(m => addMaterialRow(m.desc, m.qty, m.unit));
  laborToAdd.forEach(l => addLaborRow(l.desc, l.hours, l.rate));
  updateCalculations();
}

function setupPhotoPreviews() {
  function handleFileInput(inputId, gridId) {
    const input = document.getElementById(inputId);
    const grid = document.getElementById(gridId);
    if (!input || !grid) return;

    input.addEventListener('change', () => {
      grid.innerHTML = '';
      Array.from(input.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const thumbWrapper = document.createElement('div');
        thumbWrapper.className = 'photo-thumb-item';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = file.name;
        img.style.cursor = 'zoom-in';
        img.title = 'Cliquer pour agrandir';
        // DO NOT revokeObjectURL on load so the lightbox can display it when clicked!

        thumbWrapper.appendChild(img);
        grid.appendChild(thumbWrapper);
      });
    });
  }

  handleFileInput('photo-current', 'photo-current-grid');
  handleFileInput('photo-desired', 'photo-desired-grid');
}

// BOOTSTRAP INITIALIZATION

document.addEventListener('DOMContentLoaded', async () => {
  await initCatalogAutocomplete();
  await loadTierPresets();
  setupPhotoPreviews();

  // DEFAULT: Load the true "Économique (Budget)" preset from tier-presets.json
  applyPresetTier('budget');

  // Row creation listeners
  document.getElementById('add-material')?.addEventListener('click', () => addMaterialRow());
  document.getElementById('add-labor')?.addEventListener('click', () => addLaborRow());

  // Dynamic recalculations on input change
  document.addEventListener('input', (e) => {
    if (
      e.target.classList.contains('mat-qty') ||
      e.target.classList.contains('mat-unit') ||
      e.target.classList.contains('lab-hours') ||
      e.target.classList.contains('lab-rate')
    ) {
      updateCalculations();
    }
  });

  // Preview generator
  document.getElementById('generate-quote')?.addEventListener('click', () => {
    updateCalculations();
    generatePreview();
  });

  // "Pré-remplir selon la demande" button (runs on demand when Simon clicks it)
  document.getElementById('btn-prefill-quote')?.addEventListener('click', () => {
    prefillFromClientRequest();
    
    // RESTORE the orange highlight on whichever tier was active!
    const activeKey = window.currentActiveTier || 'budget';
    document.querySelectorAll('.btn-tier').forEach(b => b.classList.remove('active'));
    document.getElementById(`tier-btn-${activeKey}`)?.classList.add('active');
  });

  // Send quote handler
  document.getElementById('send-quote')?.addEventListener('click', sendQuote);
});

// 1. When Simon clicks the [+] button in Suggested Materials
document.addEventListener('click', function(event) {
  const btn = event.target.closest('.btn-add-suggested-material');
  if (!btn) return;
  
  const suggestionId = btn.dataset.id;
  const name = btn.dataset.name;
  const price = parseFloat(btn.dataset.price || 0);

  // Add to 1. Matériaux table
  addMaterialRow(name, 1, price);
  updateCalculations();

  // Hide the item from the Suggested Materials table instead of removing it completely
  const suggestedRow = document.getElementById(`suggested-row-${suggestionId}`);
  if (suggestedRow) {
    suggestedRow.style.display = 'none';
  }
});

// 2. When Simon clicks the trash can icon inside 1. Matériaux to delete a row
document.addEventListener('click', function(event) {
  const deleteBtn = event.target.closest('.delete-material');
  if (!deleteBtn) return;

  const row = deleteBtn.closest('.quote-row');
  if (!row) return;

  // Optional: Check if the description matches any hidden suggested item to restore it
  const descInput = row.querySelector('.mat-desc');
  const itemName = descInput ? descInput.value.trim() : '';

  if (itemName) {
    // Find matching hidden suggested row by product name and make it visible again
    document.querySelectorAll('tr[id^="suggested-row-"]').forEach(sRow => {
      const nameCell = sRow.cells[1]; // Index 1 is the product name cell
      if (nameCell && nameCell.textContent.trim() === itemName) {
        sRow.style.display = ''; // Bring it back!
      }
    });
  }

  // Remove the row from materials and recalculate
  row.remove();
  updateCalculations();
});