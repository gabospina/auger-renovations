<?php
// admin/quote-bathroom.php
session_start();

// SECURITY: Redirect to login if not authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Soumission Rapide | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #f6f8fa; margin: 0; color: #24292e; }
    .topbar { background: #24292e; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
    .topbar a { color: #fff; text-decoration: none; font-weight: bold; background: #444; padding: 8px 12px; border-radius: 4px; }
    .topbar a:hover { background: #d1b06a; }
    
    .container { max-width: 1300px; margin: 30px auto; padding: 0 20px; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
    
    .card { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #d0d7de; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; font-size: 18px; }
    
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 13px; color: #333; }
    .form-group input, .form-group select { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
    
    .btn { background: #d1b06a; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; display: inline-block; width: 100%; text-align: center; font-size: 14px; }
    .btn:hover { background: #c2a15b; }
    .btn-secondary { background: #24292e; }
    .btn-secondary:hover { background: #000; }
    
    .save-panel { background: #fdfaf3; border: 1px solid #d1b06a; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    table th, table td { padding: 12px; border-bottom: 1px solid #e1e4e8; text-align: left; }
    table th { background: #f6f8fa; color: #555; }
    .text-right { text-align: right !important; }
    .btn-remove { color: #c92a2a; cursor: pointer; background: none; border: none; font-size: 16px; }
    
    .totals-box { margin-top: 20px; border-top: 2px solid #24292e; padding-top: 15px; text-align: right; }
    .totals-box p { margin: 5px 0; font-size: 15px; color: #555; }
    .totals-box h3 { margin: 10px 0 0 0; color: #15803d; font-size: 22px; }

    /* PRINT STYLES */
    @media print {
      body { background: #fff; }
      .topbar, .control-panel, .btn-remove { display: none !important; }
      .container { display: block; max-width: 100%; margin: 0; padding: 0; }
      .quote-panel { border: none; box-shadow: none; }
      .print-header { display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #d1b06a; padding-bottom: 20px; }
    }
    .print-header { display: none; }
    @media print { .print-header { display: flex; } }

    @keyframes pulse-red {
      0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
      70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
      100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
    }
    .recording-active {
      background-color: #dc2626 !important;
      animation: pulse-red 1.5s infinite;
    }
  </style>
</head>
<body>

  <div class="topbar">
    <div style="font-size: 18px;"><strong>Auger Rénovation</strong> — Soumission Rapide</div>
    <a href="admin.php"><i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord</a>
  </div>

  <div class="container">
    
    <!-- LEFT PANEL: CONTROLS & ADDING ITEMS -->
    <div class="card control-panel">
      
      <!-- SAVED QUOTES MANAGEMENT -->
      <div class="save-panel">
        <h2 style="border-bottom: 1px solid #d1b06a; margin-top:0; font-size: 16px;"><i class="fa-solid fa-folder-open"></i> Sauvegardes</h2>
        <div class="form-group" style="margin-bottom: 0;">
          <label>Charger un estimé sauvegardé :</label>
          <!-- Reduced icon button widths to 34px so the dropdown gets maximum space -->
          <div style="display: flex; gap: 6px;">
            <select id="saved-quotes-select" style="flex: 1; min-width: 0;">
              <option value="">-- Nouvelle Soumission --</option>
            </select>
            <button type="button" class="btn btn-secondary" style="width: 34px; padding: 0; flex-shrink: 0;" onclick="loadQuote()" title="Charger"><i class="fa-solid fa-folder-open" style="font-size: 13px;"></i></button>
            <button type="button" class="btn" style="width: 34px; padding: 0; background: #c92a2a; flex-shrink: 0;" onclick="deleteQuote()" title="Supprimer"><i class="fa-solid fa-trash" style="font-size: 13px;"></i></button>
          </div>
        </div>
      </div>
      <!-- Voice Assistant Widget -->
      <div style="background: #fdf8f6; border: 1px solid #f97316; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <div>
            <strong style="color: #c2410c; display: block; font-size: 14px;">Assistant Vocal & Commande Rapide (Mode Chantier)</strong>
            <span id="voice-status" style="font-size: 12px; color: #4b5563;">Cliquez sur le micro ou tapez une commande (ex: "Client Marc Leblanc, salle de bain 8 par 10").</span>
          </div>
          <button type="button" id="btn-voice-rec" style="background: #ea580c; color: white; border: none; border-radius: 50%; width: 45px; height: 45px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);" title="Activer le micro">
            🎤
          </button>
        </div>
        <!-- Fallback text input for desktop without a microphone -->
        <div style="display: flex; gap: 8px; margin-top: 6px;">
          <input type="text" id="voice-text-fallback" placeholder="Ou tapez votre commande ici (ex: Paul Morin, 10x12, douche, toilette)..." style="flex: 1; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 12px;">
          <button type="button" id="btn-text-submit" style="background: #4b5563; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer;">Exécuter</button>
        </div>
      </div>

      <h2><i class="fa-solid fa-plus-circle" style="color: #d1b06a;"></i> Ajouter au devis</h2>
      
      <div class="form-group">
        <label>Sélectionner un matériel (Catalogue)</label>
        <select id="material-select">
          <option value="">-- Chargement du catalogue... --</option>
        </select>
      </div>

      <div style="display: flex; gap: 8px;">
        <div class="form-group" style="width: 90px;">
          <label>Quantité</label>
          <input type="number" id="material-qty" value="1" min="1">
        </div>
        <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
          <button type="button" class="btn" onclick="addMaterialToQuote()" style="height: 38px;">Ajouter</button>
        </div>
      </div>

      <hr style="border: 0; border-top: 1px solid #eee; margin: 18px 0;">

      <h2><i class="fa-solid fa-person-digging" style="color: #d1b06a;"></i> Main d'œuvre / Extra</h2>
      <div class="form-group">
        <label>Description (ex: Heures de plomberie)</label>
        <input type="text" id="custom-desc" placeholder="Entrez une description...">
      </div>

      <div style="display: flex; gap: 8px; align-items: flex-end;">
        <div class="form-group" style="width: 75px; margin-bottom: 0;">
          <label>Heures</label>
          <input type="number" id="custom-qty" value="1" min="0" step="0.5">
        </div>
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
          <label>Taux / Prix ($)</label>
          <input type="number" id="custom-price" step="0.01" placeholder="0.00">
        </div>
        <div style="width: 85px; margin-bottom: 0;">
          <button type="button" class="btn btn-secondary" onclick="addCustomToQuote()" style="height: 38px; padding: 0 5px; font-size: 13px;">Ajouter</button>
        </div>
      </div>
      
      <hr style="border: 0; border-top: 1px solid #eee; margin: 18px 0;">
      
      <button type="button" class="btn" style="margin-bottom: 10px;" onclick="saveQuote()">
        <i class="fa-solid fa-floppy-disk"></i> Sauvegarder l'estimé actuel
      </button>

      <button type="button" class="btn" style="background: #15803d;" onclick="window.print()">
        <i class="fa-solid fa-file-pdf"></i> Imprimer / Sauvegarder PDF
      </button>

    </div>

    <!-- RIGHT PANEL: THE ACTUAL QUOTE -->
    <div class="card quote-panel">
      
      <div class="print-header">
        <div>
          <h1 style="margin: 0; color: #24292e;">Auger Rénovation</h1>
          <p style="margin: 5px 0; color: #666;">RBQ: 58392747-01<br>Vaudreuil-Dorion, QC</p>
        </div>
        <div style="text-align: right;">
          <h2 style="margin: 0; color: #d1b06a;">SOUMISSION</h2>
          <p style="margin: 5px 0; color: #666;">Date: <span id="quote-date"></span></p>
        </div>
      </div>

      <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <div class="form-group" style="flex: 1;">
          <label>Nom du Client</label>
          <input type="text" id="client-name" placeholder="Ex: Simon Auger" style="border: none; border-bottom: 1px dashed #ccc; padding-left: 0; font-size: 16px;">
        </div>
        <div class="form-group" style="flex: 1;">
          <label>Projet / Titre</label>
          <input type="text" id="project-title" value="Rénovation Salle de Bain" style="border: none; border-bottom: 1px dashed #ccc; padding-left: 0; font-size: 16px;">
        </div>
      </div>

      <table id="quote-table">
        <thead>
          <tr>
            <th>Description</th>
            <th class="text-right">Qté/Hrs</th>
            <th class="text-right">Prix Unitaire</th>
            <th class="text-right">Total</th>
            <th style="width: 30px;" class="text-right"></th>
          </tr>
        </thead>
        <tbody id="quote-body">
          <!-- Rows will be injected here by JS -->
        </tbody>
      </table>

      <div class="totals-box">
        <p>Sous-total: <span id="summary-subtotal">$0.00</span></p>
        <p>TPS (5%): <span id="summary-tps">$0.00</span></p>
        <p>TVQ (9.975%): <span id="summary-tvq">$0.00</span></p>
        <h3>Total: <span id="summary-total">$0.00</span></h3>
      </div>

    </div>

  </div>

  <script>
    let materialsData = {};
    let globalMarkup = 0.15;
    let quoteItems = [];
    let currentQuoteId = null;

    document.getElementById('quote-date').textContent = new Date().toLocaleDateString('fr-CA');

    async function initialize() {
      await loadCatalog();
      await loadSavedQuotesList();
      renderQuote();
    }

    async function loadCatalog() {
      try {
        const setRes = await fetch('api-materials.php?action=get_settings');
        const settings = await setRes.json();
        if (settings && settings.markup_rate !== undefined) {
          globalMarkup = parseFloat(settings.markup_rate);
        }

        const matRes = await fetch('api-materials.php?action=list');
        const materials = await matRes.json();
        
        const select = document.getElementById('material-select');
        select.innerHTML = '<option value="">-- Sélectionnez un matériel --</option>';
        
        materials.forEach(m => {
          materialsData[m.id] = m;
          const cost = parseFloat(m.price) || 0;
          const billedPrice = cost + (cost * globalMarkup);
          
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = `${m.id} - ${m.name} ($${billedPrice.toFixed(2)})`;
          select.appendChild(opt);
        });

      } catch (e) {
        console.error("Erreur de chargement", e);
      }
    }

    async function loadSavedQuotesList() {
      try {
        const res = await fetch('api-quotes.php?action=list');
        let data = await res.json();
        
        // Ensure 'quotes' is a clean array
        const quotes = Array.isArray(data) ? data : (data.quotes || data.data || Object.values(data || {}));
        
        const select = document.getElementById('saved-quotes-select');
        if (!select) return;
        
        select.innerHTML = '<option value="">-- Nouvelle Soumission --</option>';
        
        // Track unique IDs to prevent duplicate rendering
        const seenIds = new Set();

        quotes.forEach(q => {
          if (!q || typeof q !== 'object') return;
          const id = q.id || q.quote_id;
          if (!id || seenIds.has(id)) return;
          seenIds.add(id);

          const clientName = (q.client_name || (q.client && q.client.name) || '').trim();
          const projectTitle = (q.project_title || '').trim();
          
          // Skip completely empty placeholder records if any exist
          if (!clientName && !projectTitle) return;

          const opt = document.createElement('option');
          opt.value = id;
          const dateStr = q.saved_at ? q.saved_at.split(' ')[0] : (q.created_at ? q.created_at.split(' ')[0] : '');
          const label = `${clientName || 'Sans Nom'} - ${projectTitle || 'Projet'} ${dateStr ? '(' + dateStr + ')' : ''}`;
          opt.textContent = label;
          select.appendChild(opt);
        });
      } catch (e) {
        console.error("Failed to load saved quotes", e);
      }
    }

    async function saveQuote() {
      const clientName = document.getElementById('client-name').value.trim();
      const projectTitle = document.getElementById('project-title').value.trim();

      if (quoteItems.length === 0) {
        alert("Vous ne pouvez pas sauvegarder une soumission vide.");
        return;
      }

      if (!clientName) {
        if(!confirm("Aucun nom de client n'a été entré. Sauvegarder quand même ?")) return;
      }

      const payload = {
        id: currentQuoteId,
        client_name: clientName,
        project_title: projectTitle,
        items: quoteItems
      };

      try {
        const res = await fetch('api-quotes.php?action=save', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await res.json();
        
        if (result.success) {
          currentQuoteId = result.id;
          alert("Soumission sauvegardée avec succès !");
          await loadSavedQuotesList();
          document.getElementById('saved-quotes-select').value = currentQuoteId;
        } else {
          alert("Erreur lors de la sauvegarde.");
        }
      } catch (e) {
        alert("Erreur de communication avec le serveur.");
      }
    }

    async function loadQuote() {
      const select = document.getElementById('saved-quotes-select');
      const selectedId = select ? select.value : '';

      if (!selectedId) {
        currentQuoteId = null;
        const clientInput = document.getElementById('client-name');
        if (clientInput) clientInput.value = '';
        const titleInput = document.getElementById('project-title');
        if (titleInput) titleInput.value = 'Rénovation Salle de Bain';
        quoteItems = [];
        if (typeof quoteLabor !== 'undefined') quoteLabor = [];
        renderQuote();
        return;
      }

      try {
        const res = await fetch('api-quotes.php?action=list');
        let data = await res.json();
        
        // Ensure quotes is treated as an array whether returned flat or wrapped
        const quotes = Array.isArray(data) ? data : (data.quotes || data.data || Object.values(data || {}));
        
        const quoteToLoad = quotes.find(q => q && (q.id === selectedId || q.quote_id === selectedId));

        if (quoteToLoad) {
          currentQuoteId = quoteToLoad.id || quoteToLoad.quote_id;
          
          const clientNameField = document.getElementById('client-name');
          if (clientNameField) {
            clientNameField.value = quoteToLoad.client_name || (quoteToLoad.client && quoteToLoad.client.name) || '';
          }

          const projectTitleField = document.getElementById('project-title');
          if (projectTitleField) {
            projectTitleField.value = quoteToLoad.project_title || '';
          }

          // Safely extract items supporting both 'items' and 'materials' keys
          quoteItems = Array.isArray(quoteToLoad.items) ? quoteToLoad.items : (quoteToLoad.materials || []);

          // Safely extract labor if saved
          if (Array.isArray(quoteToLoad.labor) && typeof quoteLabor !== 'undefined') {
            quoteLabor = quoteToLoad.labor;
          }

          renderQuote();
        } else {
          console.warn("Quote ID not found in saved list:", selectedId);
        }
      } catch (e) {
        console.error("Erreur lors du chargement de la soumission:", e);
        alert("Impossible de charger la soumission.");
      }
    }

    async function deleteQuote() {
      const select = document.getElementById('saved-quotes-select');
      const selectedId = select.value;

      if (!selectedId) return;

      if (!confirm("Voulez-vous vraiment supprimer cette sauvegarde ? Cette action est irréversible.")) return;

      try {
        const res = await fetch('api-quotes.php?action=delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: selectedId })
        });
        const result = await res.json();
        
        if (result.success) {
          currentQuoteId = null;
          document.getElementById('client-name').value = '';
          quoteItems = [];
          renderQuote();
          await loadSavedQuotesList();
        }
      } catch (e) {
        alert("Erreur lors de la suppression.");
      }
    }

    function addMaterialToQuote() {
      const select = document.getElementById('material-select');
      const qtyInput = document.getElementById('material-qty');
      const id = select.value;
      const qty = parseFloat(qtyInput.value) || 1;

      if (!id) {
        alert("Veuillez sélectionner un matériel.");
        return;
      }

      const mat = materialsData[id];
      const cost = parseFloat(mat.price) || 0;
      const billedPrice = cost + (cost * globalMarkup);

      quoteItems.push({
        id: Date.now(),
        desc: mat.name,
        qty: qty,
        unitPrice: billedPrice
      });

      select.value = "";
      qtyInput.value = "1";
      renderQuote();
    }

    function addCustomToQuote() {
      const descInput = document.getElementById('custom-desc');
      const qtyInput = document.getElementById('custom-qty');
      const priceInput = document.getElementById('custom-price');
      
      const desc = descInput.value.trim();
      const qty = parseFloat(qtyInput.value) || 1;
      const price = parseFloat(priceInput.value) || 0;

      if (!desc || price <= 0 || qty <= 0) {
        alert("Veuillez entrer une description, des heures et un montant valide.");
        return;
      }

      quoteItems.push({
        id: Date.now(),
        desc: desc,
        qty: qty,
        unitPrice: price
      });

      descInput.value = "";
      qtyInput.value = "1";
      priceInput.value = "";
      renderQuote();
    }

    function removeRow(rowId) {
      quoteItems = quoteItems.filter(item => item.id !== rowId);
      renderQuote();
    }

    function renderQuote() {
      const tbody = document.getElementById('quote-body');
      tbody.innerHTML = '';
      let subtotal = 0;

      if (quoteItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #999;">Aucun article ajouté.</td></tr>';
      } else {
        quoteItems.forEach(item => {
          const rowTotal = item.qty * item.unitPrice;
          subtotal += rowTotal;

          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${item.desc}</td>
            <td class="text-right">${item.qty}</td>
            <td class="text-right">$${item.unitPrice.toFixed(2)}</td>
            <td class="text-right"><strong>$${rowTotal.toFixed(2)}</strong></td>
            <td class="text-right">
              <button class="btn-remove" onclick="removeRow(${item.id})" title="Retirer"><i class="fa-solid fa-xmark"></i></button>
            </td>
          `;
          tbody.appendChild(tr);
        });
      }

      const tps = subtotal * 0.05;
      const tvq = subtotal * 0.09975;
      const total = subtotal + tps + tvq;

      document.getElementById('summary-subtotal').textContent = '$' + subtotal.toFixed(2);
      document.getElementById('summary-tps').textContent = '$' + tps.toFixed(2);
      document.getElementById('summary-tvq').textContent = '$' + tvq.toFixed(2);
      document.getElementById('summary-total').textContent = '$' + total.toFixed(2);
    }

    // ==========================================
    // VOICE-TO-ESTIMATE ASSISTANT (CHANTIER)
    // ==========================================

    /* admin/quote-bathroom.php - Voice & Command Assistant Script */
    let recognitionInstance = null;
    let isVoiceActive = false;

    function initVoiceAssistant() {
      const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      const statusEl = document.getElementById('voice-status');
      const btn = document.getElementById('btn-voice-rec');
      const textInput = document.getElementById('voice-text-fallback');
      const submitBtn = document.getElementById('btn-text-submit');

      // Bind microphone button click
      if (btn) {
        btn.onclick = toggleVoiceRecording;
      }

      // Bind text fallback submit (great for desktops without a microphone)
      if (submitBtn && textInput) {
        submitBtn.onclick = () => {
          const val = textInput.value.trim();
          if (val) {
            if (statusEl) statusEl.textContent = `✅ Commande textuelle exécutée : "${val}"`;
            processVoiceCommandText(val);
            textInput.value = '';
          }
        };
        textInput.onkeypress = (e) => {
          if (e.key === 'Enter') {
            submitBtn.click();
          }
        };
      }

      if (!SpeechRecognition) {
        if (statusEl) statusEl.textContent = "La reconnaissance vocale nécessite Google Chrome. Utilisez la boîte de texte ci-dessous.";
        if (btn) btn.style.display = 'none';
        return;
      }

      recognitionInstance = new SpeechRecognition();
      recognitionInstance.lang = 'fr-CA'; // French (Canada)
      recognitionInstance.interimResults = false;
      recognitionInstance.maxAlternatives = 1;

      recognitionInstance.onstart = function() {
        isVoiceActive = true;
        if (btn) btn.classList.add('recording-active');
        if (statusEl) statusEl.textContent = "🎙️ Écoute en cours... Décrivez le projet";
      };

      recognitionInstance.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        if (statusEl) statusEl.textContent = `✅ Capté : "${transcript}"`;
        processVoiceCommandText(transcript);
      };

      recognitionInstance.onerror = function(event) {
        console.error("Erreur de reconnaissance vocale :", event.error);
        let msg = "Microphone introuvable ou accès refusé.";
        if (statusEl) statusEl.textContent = `⚠️ ${msg} Utilisez la boîte de texte ci-dessous pour tester.`;
        stopVoiceAssistantUI();
      };

      recognitionInstance.onend = function() {
        stopVoiceAssistantUI();
      };
    }

    async function toggleVoiceRecording() {
      const statusEl = document.getElementById('voice-status');

      // Check if mediaDevices and input devices exist before prompting
      try {
        if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
          const devices = await navigator.mediaDevices.enumerateDevices();
          const hasMic = devices.some(device => device.kind === 'audioinput');
          if (!hasMic) {
            if (statusEl) statusEl.textContent = "⚠️ Aucun microphone détecté sur cet ordinateur. Utilisez la boîte de texte ci-dessous.";
            alert("Aucun microphone n'a été détecté sur cet appareil. Vous pouvez utiliser la boîte de saisie textuelle juste en dessous pour simuler une commande vocale.");
            return;
          }
        }
      } catch (e) {
        console.warn("Impossible de lister les périphériques audio:", e);
      }

      // Check explicit permission
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(track => track.stop());
      } catch (err) {
        if (statusEl) statusEl.textContent = "⚠️ Accès au microphone refusé ou absent. Utilisez la boîte de texte ci-dessous.";
        alert("Aucun microphone actif ou autorisé n'a été trouvé. Veuillez utiliser la boîte de texte de commande rapide juste en dessous.");
        return;
      }

      if (!recognitionInstance) {
        initVoiceAssistant();
      }

      if (!recognitionInstance) return;

      if (isVoiceActive) {
        try { recognitionInstance.stop(); } catch(e) {}
        stopVoiceAssistantUI();
      } else {
        try {
          recognitionInstance.start();
        } catch (err) {
          console.error(err);
        }
      }
    }

    function stopVoiceAssistantUI() {
      isVoiceActive = false;
      const btn = document.getElementById('btn-voice-rec');
      if (btn) btn.classList.remove('recording-active');
    }

    // Intelligent Parser tied directly to Simon's form inputs
    function processVoiceCommandText(text) {
      const lower = text.toLowerCase();

      // 1. Client Name Extraction
      const clientMatch = lower.match(/(?:client|pour|nom)\s+([a-zà-ÿ\s]+)(?:\.|,|\s+salle|\s+dimension|\s+avec|$)/i);
      if (clientMatch && clientMatch[1]) {
        const rawName = clientMatch[1].trim();
        const formattedName = rawName.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        const clientField = document.getElementById('client-name');
        if (clientField) {
          clientField.value = formattedName;
        }
      }

      // 2. Room Dimensions Extraction (e.g., "8 par 10" or "8 x 10")
      const dimMatch = lower.match(/(\d+)\s*(?:par|x)\s*(\d+)/i);
      if (dimMatch) {
        const length = dimMatch[1];
        const width = dimMatch[2];
        
        const lengthField = document.getElementById('bath-length') || document.getElementById('room-length');
        const widthField = document.getElementById('bath-width') || document.getElementById('room-width');
        const sqftField = document.getElementById('bath-size');

        if (lengthField) lengthField.value = length;
        if (widthField) widthField.value = width;
        if (sqftField) {
          sqftField.value = parseInt(length) * parseInt(width);
          sqftField.dispatchEvent(new Event('input'));
        }
      }

      // 3. Keyword Detection & Form Checkboxes / Work items
      const spokenWords = lower;
      
      if (window.CURRENT_PROJECT_SPEC) {
        if (!window.CURRENT_PROJECT_SPEC.workItems) window.CURRENT_PROJECT_SPEC.workItems = [];
        
        if (spokenWords.includes('douche') && !window.CURRENT_PROJECT_SPEC.workItems.includes('douche')) {
          window.CURRENT_PROJECT_SPEC.workItems.push('douche');
        }
        if (spokenWords.includes('toilette')) {
          if(!window.CURRENT_PROJECT_SPEC.plumbing) window.CURRENT_PROJECT_SPEC.plumbing = [];
          if(!window.CURRENT_PROJECT_SPEC.plumbing.includes('replace_toilet')) {
            window.CURRENT_PROJECT_SPEC.plumbing.push('replace_toilet');
          }
        }
        if (spokenWords.includes('vanité') || spokenWords.includes('lavabo')) {
          if(!window.CURRENT_PROJECT_SPEC.plumbing) window.CURRENT_PROJECT_SPEC.plumbing = [];
          if(!window.CURRENT_PROJECT_SPEC.plumbing.includes('replace_vanity')) {
            window.CURRENT_PROJECT_SPEC.plumbing.push('replace_vanity');
          }
        }
      }

      // Automatically trigger calculation/prefill engine
      if (typeof prefillFromClientRequest === 'function') {
        prefillFromClientRequest();
      } else if (typeof updateCalculations === 'function') {
        updateCalculations();
      }
    }

    // Initialize everything safely when the page loads
    document.addEventListener('DOMContentLoaded', async () => {
      initVoiceAssistant();
      await initialize();
    });
  </script>
</body>
</html>