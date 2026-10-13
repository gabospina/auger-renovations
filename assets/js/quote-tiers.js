// assets/js/quote-tiers.js
// Dynamic Tier Presets Handler (Économique / Standard / Haut de gamme)

let TIER_PRESETS = {
  budget: { label: 'Économique', materials: [], labor: [] },
  standard: { label: 'Standard', materials: [], labor: [] },
  premium: { label: 'Haut de gamme', materials: [], labor: [] }
};

function validateTierPresets(data) {
  const tiers = ['budget', 'standard', 'premium'];
  const safeData = {};

  tiers.forEach(tier => {
    const source = data?.[tier] || {};
    safeData[tier] = {
      label: source.label || tier,
      materials: Array.isArray(source.materials) ? source.materials : [],
      labor: Array.isArray(source.labor) ? source.labor : []
    };
  });

  return safeData;
}

async function loadTierPresets() {
  try {
    const apiPath = window.location.pathname.includes('/admin/')
      ? 'api-materials.php?action=get_tiers&t=' + Date.now()
      : 'admin/api-materials.php?action=get_tiers&t=' + Date.now();

    const response = await fetch(apiPath, { cache: 'no-store' });
    if (!response.ok) throw new Error('Erreur HTTP: ' + response.status);

    const data = await response.json();
    TIER_PRESETS = validateTierPresets(data);
  } catch (err) {
    console.error('Erreur chargement des niveaux de finition:', err);
    TIER_PRESETS = validateTierPresets({});
  }
}

// assets/js/quote-tiers.js

function calculateTierMaterials(tierKey, roomSqft = 0) {
  const preset = TIER_PRESETS?.[tierKey];
  if (!preset || !Array.isArray(preset.materials)) {
    return { items: [], totalCost: 0, totalBilled: 0 };
  }

  let totalCost = 0;
  let totalBilled = 0;
  const items = [];

  preset.materials.forEach(mat => {
    let unitBaseCost = 0;
    let desc = mat.desc;

    // 1. Resolve unit price from availableCatalog (loaded by quote-engine.js)
    if (mat.material_id && availableCatalog.length > 0) {
      const match = availableCatalog.find(c => 
        c.id === mat.material_id || 
        ('SKU-' + c.id) === mat.material_id ||
        c.id === ('SKU-' + mat.material_id)
      );
      if (match) {
        unitBaseCost = match.base_price;
        if (match.name) desc = match.name;
      }
    }

    // 2. Fallback if not found in catalog
    if (!unitBaseCost) {
      unitBaseCost = parseFloat(mat.unit_fallback || mat.unit || 0);
    }

    // 3. Resolve quantity (fixed vs per sqft)
    const baseQty = parseFloat(mat.qty) || 0;
    const qty = (mat.qty_type === 'per_sqft' && roomSqft > 0)
      ? Math.ceil(baseQty * roomSqft)
      : baseQty;

    const billedUnit = unitBaseCost * (1 + MARKUP_RATE);
    const lineCost = qty * unitBaseCost;
    const lineBilled = qty * billedUnit;

    totalCost += lineCost;
    totalBilled += lineBilled;

    items.push({
      desc: desc,
      qty: qty,
      unit_cost: unitBaseCost,
      unit_billed: parseFloat(billedUnit.toFixed(2)),
      line_cost: parseFloat(lineCost.toFixed(2)),
      line_billed: parseFloat(lineBilled.toFixed(2))
    });
  });

  return {
    items,
    totalCost: parseFloat(totalCost.toFixed(2)),
    totalBilled: parseFloat(totalBilled.toFixed(2))
  };
}

function applyPresetTier(tierKey) {
  const bathSize = parseFloat(document.getElementById('bath-size')?.value) || 0;
  const computed = calculateTierMaterials(tierKey, bathSize);

  // Toggle button styling
  document.querySelectorAll('.btn-tier').forEach(b => b.classList.remove('active'));
  document.getElementById(`tier-btn-${tierKey}`)?.classList.add('active');

  // Populate Materials Table
  const matContainer = document.getElementById('materials-list');
  if (matContainer) {
    matContainer.innerHTML = '';
    if (computed.items.length > 0) {
      computed.items.forEach(item => {
        addMaterialRow(item.desc, item.qty, item.unit_billed);
      });
    } else {
      addMaterialRow();
    }
  }

  // Populate Labor Table
  const preset = TIER_PRESETS?.[tierKey];
  const labContainer = document.getElementById('labor-list');
  if (labContainer) {
    labContainer.innerHTML = '';
    if (Array.isArray(preset?.labor) && preset.labor.length > 0) {
      preset.labor.forEach(l => addLaborRow(l.desc, l.hours, l.rate));
    } else {
      addLaborRow();
    }
  }

  updateCalculations();
}
