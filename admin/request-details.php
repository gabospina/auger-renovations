<!-- ========================================== -->
<!-- AI PROJECT ANALYSIS HUB (ADMIN PANEL)      -->
<!-- ========================================== -->
<article class="card quote-card" style="border: 2px solid #ea580c; margin-top: 20px;">
  <h2><i class="fa-solid fa-wand-magic-sparkles" style="color: #ea580c;"></i> Analyseur IA du Projet (Mode Chantiers)</h2>
  
  <div id="ai-analysis-container" style="padding: 10px 0;">
    <p id="ai-status-text" style="color: #4b5563; font-size: 14px; margin-bottom: 15px;">
      Analysez les photos et dimensions de ce client pour détecter automatiquement les luminaires, calcul de matériaux et pré-remplir le devis.
    </p>

    <!-- Trigger Button -->
    <button type="button" id="btn-run-ai-analysis" onclick="runAIAnalysis('<?= htmlspecialchars($reqId ?? $_GET['id'] ?? '') ?>')" class="btn btn-primary" style="background: #ea580c; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">
      <i class="fa-solid fa-robot"></i> Analyser les photos du client avec l'IA
    </button>

    <!-- Results Panel (Hidden until analyzed) -->
    <div id="ai-results-panel" style="display: none; margin-top: 20px; background: #fff7ed; border: 1px solid #fed7aa; padding: 15px; border-radius: 8px;">
      <h3 style="color: #c2410c; margin-top: 0; font-size: 16px;"><i class="fa-solid fa-square-check"></i> Résultats de l'analyse</h3>
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 14px; margin-bottom: 15px;">
        <div>
          <strong>Éléments détectés :</strong>
          <ul id="ai-detected-list" style="margin: 5px 0 0 20px; color: #374151;">
            <!-- Populated via JavaScript -->
          </ul>
        </div>
        <div>
          <strong>Recommandations :</strong>
          <ul id="ai-recommendations-list" style="margin: 5px 0 0 20px; color: #374151;">
            <!-- Populated via JavaScript -->
          </ul>
        </div>
      </div>

      <div style="display: flex; gap: 10px; align-items: center; border-top: 1px solid #fed7aa; padding-top: 12px;">
        <button type="button" id="btn-generate-prefill" onclick="runQuotePrefill('<?= htmlspecialchars($reqId ?? $_GET['id'] ?? '') ?>')" class="btn" style="background: #16a34a; color: white; padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px;">
          <i class="fa-solid fa-file-invoice-dollar"></i> Générer le devis pré-rempli (IA)
        </button>
        <span id="ai-confidence-badge" style="font-size: 12px; background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-weight: bold;"></span>
      </div>
    </div>
  </div>
</article>

<script>
// 1. Frontend trigger for the AI analysis pipeline
async function runAIAnalysis(reqId) {
  if (!reqId) {
    alert("Identifiant de demande introuvable.");
    return;
  }

  const statusText = document.getElementById('ai-status-text');
  const runBtn = document.getElementById('btn-run-ai-analysis');
  const resultsPanel = document.getElementById('ai-results-panel');

  runBtn.disabled = true;
  runBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyse des photos en cours...';
  statusText.textContent = "L'intelligence artificielle examine les photos du client et calcule les surfaces...";

  try {
    // Correct relative path since request-details.php is inside admin/
    const response = await fetch(`ai/analyze-request.php?id=${encodeURIComponent(reqId)}`);
    const data = await response.json();

    if (data.success) {
      statusText.textContent = "✅ Analyse terminée avec succès !";
      runBtn.style.display = 'none';
      resultsPanel.style.display = 'block';

      // Populate detected fixtures
      const detectedList = document.getElementById('ai-detected-list');
      detectedList.innerHTML = '';
      (data.analysis.detected_items || []).forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        detectedList.appendChild(li);
      });

      // Populate recommendations
      const recList = document.getElementById('ai-recommendations-list');
      recList.innerHTML = '';
      for (const [key, val] of Object.entries(data.analysis.recommendations || {})) {
        if (val) {
          const li = document.createElement('li');
          li.textContent = `${key.replace(/_/g, ' ')}`;
          recList.appendChild(li);
        }
      }

      // Confidence badge
      document.getElementById('ai-confidence-badge').textContent = `Confiance IA : ${Math.round((data.analysis.confidence || 0.9) * 100)}%`;

    } else {
      throw new Error(data.error || "Erreur lors de l'analyse.");
    }
  } catch (err) {
    console.error(err);
    statusText.textContent = "⚠️ Erreur lors de l'analyse de l'image. Mode simulation actif.";
    runBtn.disabled = false;
    runBtn.innerHTML = '<i class="fa-solid fa-robot"></i> Réessayer l\'analyse';
    
    // Fallback simulation view
    resultsPanel.style.display = 'block';
    document.getElementById('ai-detected-list').innerHTML = '<li>Vanité simple (36")</li><li>Toilette standard</li><li>Bain-douche encastré</li><li>Céramique de plancher</li>';
    document.getElementById('ai-recommendations-list').innerHTML = '<li>Remplacer la vanité</li><li>Remplacer le plancher</li><li>Membrane d\'étanchéité</li>';
    document.getElementById('ai-confidence-badge').textContent = 'Confiance IA : 92% (Simulation)';
  }
}

// 2. Trigger the Material Mapper & Quote Prefill Engine
async function runQuotePrefill(reqId) {
  try {
    const btn = document.getElementById('btn-generate-prefill');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Calcul des matériaux...';

    // Step A: Map materials based on surfaces
    await fetch(`ai/material-mapper.php?id=${encodeURIComponent(reqId)}`);

    // Step B: Generate prefill package and get redirect URL
    const res = await fetch(`ai/quote-prefill.php?id=${encodeURIComponent(reqId)}`);
    const json = await res.json();

    if (json.success && json.redirect_url) {
      // Redirect straight into Simon's quote editor with prefilled data
      window.location.href = json.redirect_url;
    } else {
      throw new Error(json.error || "Erreur lors de la génération du devis pré-rempli.");
    }
  } catch (e) {
    console.error(e);
    alert("Erreur: " + e.message);
    const btn = document.getElementById('btn-generate-prefill');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-file-invoice-dollar"></i> Générer le devis pré-rempli (IA)';
  }
}
</script>