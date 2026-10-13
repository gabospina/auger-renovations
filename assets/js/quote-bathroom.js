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
            <div style="color: #b45309; font-size: 17px; font-weight: 800;">SOUMISSION OFFICIELLE</div>
            <div style="color: #374151; font-size: 13px; font-weight: 600;">Date : ${todayStr}</div>
          </div>
        </div>
      </div>

      <div style="display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 13px;">
        <div>
          <div style="color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700;">Client</div>
          <strong style="font-size: 14px; color: #111;">${name}</strong><br>
          <span>${address}</span><br>
          <span>${email}</span> • <span>${phone}</span>
        </div>
        <div style="text-align: right;">
          <div style="color: #9ca3af; text-transform: uppercase; font-size: 11px; font-weight: 700;">Projet</div>
          <strong style="font-size: 14px; color: #111;">Rénovation salle de bain (${bathType})</strong><br>
          <span>Superficie approx. : ${bathSize} pi²</span>
        </div>
      </div>

      ${bathNotes ? `
      <div style="margin-bottom: 16px; background: #f9fafb; padding: 10px 14px; border-radius: 6px; border: 1px solid #f3f4f6;">
        <strong style="font-size: 12px; color: #374151; text-transform: uppercase;">Notes & Portée des travaux :</strong>
        <p style="margin: 4px 0 0 0; color: #4b5563; font-size: 13px;">${bathNotes}</p>
      </div>` : ''}

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
            <td style="padding: 10px 12px; text-align: right; font-weight: 600;">${matTotal}</td>
          </tr>
          <tr style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 10px 12px; color: #1f2937;">Main-d'œuvre qualifiée & installation</td>
            <td style="padding: 10px 12px; text-align: right; font-weight: 600;">${labTotal}</td>
          </tr>
        </tbody>
      </table>

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
        <div style="display: flex; justify-content: space-between; padding: 8px 0; font-weight: 800; font-size: 16px; border-top: 2px solid #111; color: #111;">
          <span>TOTAL :</span> <span>${grand}</span>
        </div>
      </div>
      
      <div style="margin-top: 20px; font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 8px;">
        * Cette soumission est valide pour une durée de 30 jours à compter de sa date d'émission.
      </div>
    </div>
  `;
}

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

  const durock = getCatalogBilledPrice(['durock'], 26.98);
  const kerdi = getCatalogBilledPrice(['kerdi', 'membrane'], 149.00);
  const sheetrock = getCatalogBilledPrice(['sheetrock'], 18.98);

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
      { desc: durock.name || 'Panneau de ciment Durock 1/2 po x 3 pi x 5 pi', qty: 6, unit: durock.price },
      { desc: kerdi.name || "Membrane d'étanchéité Schluter Kerdi 108 pi²", qty: 1, unit: kerdi.price },
      { desc: 'Céramique de douche & coulis scellant', qty: Math.round(sqft * 0.8), unit: parseFloat((4.50 * (1 + MARKUP_RATE)).toFixed(2)) }
    );

    laborToAdd.push(
      { desc: 'Démolition douche existante et évacuation', hours: 6, rate: 0.00 },
      { desc: 'Pose membrane Kerdi, ciment-board et étanchéité', hours: 8, rate: 0.00 },
      { desc: 'Pose carrelage mural et coulis douche', hours: 14, rate: 0.00 }
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
    laborToAdd.push({ desc: 'Installation vanité, robinetterie et raccordements', hours: 4.5, rate: 0.00 });
  }

  if (hasToilet) {
    laborToAdd.push({ desc: 'Remplacement toilette et bride de plancher', hours: 2, rate: 0.00 });
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
        img.onload = () => URL.revokeObjectURL(img.src);

        thumbWrapper.appendChild(img);
        grid.appendChild(thumbWrapper);
      });
    });
  }

  handleFileInput('photo-current', 'photo-current-grid');
  handleFileInput('photo-desired', 'photo-desired-grid');
}

// BOOTSTRAP
document.addEventListener('DOMContentLoaded', async () => {
  await initCatalogAutocomplete();
  await loadTierPresets();

  document.getElementById('add-material')?.addEventListener('click', () => addMaterialRow());
  document.getElementById('add-labor')?.addEventListener('click', () => addLaborRow());

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

  setupPhotoPreviews();
  applyPresetTier('budget');

  document.getElementById('generate-quote')?.addEventListener('click', () => {
    updateCalculations();
    generatePreview();
  });

  document.getElementById('btn-prefill-quote')?.addEventListener('click', () => {
    prefillFromClientRequest();
  });

  document.getElementById('send-quote')?.addEventListener('click', sendQuote);
});