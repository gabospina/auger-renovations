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

// Helper to compute room surfaces dynamically
function getProjectSurfaces(fallbackSqft = 0) {
  const spec = window.CURRENT_PROJECT_SPEC || {};
  const length = parseFloat(spec.length || spec.bath_length || 0);
  const width = parseFloat(spec.width || spec.bath_width || 0);
  const ceiling = parseFloat(spec.ceilingHeight || spec.ceiling_height || 8);

  let floorArea = (length > 0 && width > 0) ? (length * width) : (parseFloat(fallbackSqft) || parseFloat(spec.sqft) || 0);
  let ceilingArea = floorArea;

  let wallArea = 0;
  if (length > 0 && width > 0) {
    wallArea = (length + width) * 2 * ceiling;
  } else if (floorArea > 0) {
    wallArea = (Math.sqrt(floorArea) * 4) * ceiling;
  }

  return {
    floor: Math.round(floorArea),
    ceiling: Math.round(ceilingArea),
    wall: Math.round(wallArea)
  };
}

function calculateTierMaterials(tierKey, roomSqft = 0) {
  const preset = typeof TIER_PRESETS !== 'undefined' ? TIER_PRESETS?.[tierKey] : null;
  if (!preset || !Array.isArray(preset.materials)) {
    return { items: [], totalCost: 0, totalBilled: 0 };
  }

  const surfaces = getProjectSurfaces(roomSqft);
  let totalCost = 0;
  let totalBilled = 0;
  const items = [];

  const catalog = (typeof availableCatalog !== 'undefined' && Array.isArray(availableCatalog))
    ? availableCatalog
    : (window.QuoteEngine?.catalog || []);
  const markup = (typeof MARKUP_RATE !== 'undefined')
    ? MARKUP_RATE
    : (window.QuoteEngine?.markupRate || 0.15);

  preset.materials.forEach(mat => {
    let unitBaseCost = 0;
    let desc = mat.desc || 'Matériau';

    // 1. Resolve unit cost from Catalog
    if (mat.material_id && catalog.length > 0) {
      const match = catalog.find(c => 
        c.id === mat.material_id || 
        ('SKU-' + c.id) === mat.material_id ||
        c.id === ('SKU-' + mat.material_id)
      );
      if (match) {
        unitBaseCost = match.base_price;
        if (match.name) desc = match.name;
      }
    }

    if (!unitBaseCost) {
      unitBaseCost = parseFloat(mat.unit_fallback || mat.unit || 0);
    }

    // 2. Resolve Quantities by Surface Type
    let qty = 0;
    const baseQty = parseFloat(mat.qty) || 0;
    const coverage = parseFloat(mat.coverage) || 1;
    const wasteFactor = parseFloat(mat.waste_factor) || 1.10; // 10% cutting waste

    switch (mat.qty_type) {
      case 'fixed':
        qty = baseQty;
        break;

      case 'per_sqft':
      case 'floor_area':
        qty = surfaces.floor > 0 ? Math.ceil((surfaces.floor * wasteFactor) / coverage) : baseQty;
        break;

      case 'wall_area':
        qty = surfaces.wall > 0 ? Math.ceil((surfaces.wall * wasteFactor) / coverage) : baseQty;
        break;

      case 'ceiling_area':
        qty = surfaces.ceiling > 0 ? Math.ceil((surfaces.ceiling * wasteFactor) / coverage) : baseQty;
        break;

      default:
        qty = baseQty;
        break;
    }

    qty = Math.max(0, isNaN(qty) ? 0 : qty);

    const billedUnit = unitBaseCost * (1 + markup);
    const lineCost = qty * unitBaseCost;
    const lineBilled = qty * billedUnit;

    totalCost += lineCost;
    totalBilled += lineBilled;

    items.push({
      material_id: mat.material_id || '',
      desc: desc,
      qty: qty,
      unit_cost: unitBaseCost,
      unit_billed: parseFloat(billedUnit.toFixed(2)),
      line_cost: parseFloat(lineCost.toFixed(2)),
      line_billed: parseFloat(lineBilled.toFixed(2))
    });
  });

  return {
    surfaces,
    items,
    totalCost: parseFloat(totalCost.toFixed(2)),
    totalBilled: parseFloat(totalBilled.toFixed(2))
  };
}

// Calculate realistic labor hours dynamically based on room measurements
function calculateTierLabor(tierKey, roomSqft = 0) {
  const preset = typeof TIER_PRESETS !== 'undefined' ? TIER_PRESETS?.[tierKey] : null;
  if (!preset || !Array.isArray(preset.labor)) {
    return [];
  }

  const surfaces = getProjectSurfaces(roomSqft);

  return preset.labor.map(l => {
    let hours = parseFloat(l.hours) || 0;

    // Optional dynamic calculation rules if specified
    if (l.hours_type === 'per_sqft_floor' && surfaces.floor > 0) {
      hours = Math.max(4, Math.round(surfaces.floor * (parseFloat(l.hours_per_sqft) || 0.1)));
    } else if (l.hours_type === 'per_sqft_wall' && surfaces.wall > 0) {
      hours = Math.max(4, Math.round(surfaces.wall * (parseFloat(l.hours_per_sqft) || 0.05)));
    }

    return {
      desc: l.desc || 'Main-d\'œuvre',
      hours: hours,
      rate: parseFloat(l.rate) || 0 // Simon enters rate, or 0.00
    };
  });
}

// Store globally so other functions know what's selected
window.currentActiveTier = 'budget'; // default

function applyPresetTier(tierKey) {
  window.currentActiveTier = tierKey; // <--- SAVE IT HERE
  
  const bathSize = parseFloat(document.getElementById('bath-size')?.value) || 0;
  const computed = calculateTierMaterials(tierKey, bathSize);
  const computedLabor = calculateTierLabor(tierKey, bathSize);

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
  const labContainer = document.getElementById('labor-list');
  if (labContainer) {
    labContainer.innerHTML = '';
    if (computedLabor.length > 0) {
      computedLabor.forEach(l => addLaborRow(l.desc, l.hours, l.rate));
    } else {
      addLaborRow();
    }
  }

  updateCalculations();
}