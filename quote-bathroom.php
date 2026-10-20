<?php
// PUBLIC PAGE — NO LOGIN REQUIRED
$isSubmitted = isset($_GET['submitted']) && $_GET['submitted'] == '1';
$submittedReqId = $_GET['req'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Soumission salle de bain | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/quote-bathroom-form.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="quote-body">

<header class="site-header">
  <div class="container header-inner">
    <nav class="main-nav" id="mainNav">
      <ul>
        <li><a href="index.html">MAIN PAGE</a></li>
        <li><a href="accueil.html">PAGE D'ACCUEIL</a></li>
        <li><a href="realisations.html">RÉALISATIONS</a></li>
        <li><a href="services.html">SERVICES</a></li>
        <li><a href="contact.html">CONTACTEZ-NOUS</a></li>
        <li class="rbq-nav">RBQ : 58392747-01</li>
      </ul>
    </nav>
    <button class="nav-toggle" id="navToggle" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<section class="hero-small">
  <div class="container hero-content">
    <h1>Créer une soumission – Salle de bain</h1>
    <p>Remplissez les détails pour générer une soumission professionnelle.</p>
  </div>
</section>

<section class="section">
  <div class="container quote-grid">

    <?php if ($isSubmitted): ?>
      <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 16px 20px; border-radius: 6px; text-align: center; margin-bottom: 20px; font-size: 15px; line-height: 1.5;">
        <strong style="font-size: 17px;"><i class="fa-solid fa-circle-check"></i> Demande transmise avec succès!</strong><br>
        Numéro de dossier : <strong><?= htmlspecialchars($submittedReqId) ?></strong><br>
        <span style="display: inline-block; margin-top: 6px; color: #1e6b37;">
          Simon Auger communiquera avec vous sous peu pour discuter de votre projet et réviser les détails de votre soumission.
        </span>
      </div>
    <?php endif; ?>

    <form action="save-request.php" method="POST" enctype="multipart/form-data">

      <!-- Customer Info -->
      <article class="card quote-card">
        <h2>Informations du client</h2>

        <div class="form-group">
          <label for="client-name">Nom du client</label>
          <input type="text" id="client-name" name="client_name" required>
        </div>

        <div class="form-group">
          <label for="client-address">Adresse</label>
          <input type="text" id="client-address" name="client_address" required>
        </div>

        <div class="form-group">
          <label for="client-email">Courriel</label>
          <input type="email" id="client-email" name="client_email" required>
        </div>

        <div class="form-group">
          <label for="client-phone">Téléphone</label>
          <input type="tel" id="client-phone" name="client_phone" required>
        </div>
      </article>

      <!-- Bathroom Details -->
      <article class="card quote-card">

          <h2>Détails de la salle de bain</h2>

          <!-- General Information -->
          <div class="form-row-3">
              <div class="form-group">
                  <label for="bath-type">Type de salle de bain</label>
                  <select id="bath-type" name="bath_type">
                      <option value="powder">Salle d'eau</option>
                      <option value="complete" selected>Salle de bain complète</option>
                      <option value="ensuite">Salle de bain attenante</option>
                      <option value="basement">Sous-sol</option>
                  </select>
              </div>

              <div class="form-group">
                  <label for="bath-condition">État actuel</label>
                  <select id="bath-condition" name="bath_condition">
                      <option value="excellent">Excellent</option>
                      <option value="good">Bon</option>
                      <option value="average" selected>Moyen</option>
                      <option value="poor">Mauvais</option>
                  </select>
              </div>

              <div class="form-group">
                  <label for="renovation-level">Type de rénovation</label>
                  <select id="renovation-level" name="renovation_level">
                      <option value="refresh">Rafraîchissement</option>
                      <option value="partial">Rénovation partielle</option>
                      <option value="complete" selected>Rénovation complète</option>
                      <option value="luxury">Haut de gamme</option>
                  </select>
              </div>
          </div>

          <!-- Dimensions -->
          <h3>Dimensions</h3>
          <div class="form-row-3">
              <div class="form-group">
                  <label>Longueur (pi)</label>
                  <input type="number" step="0.1" min="0" name="bath_length">
              </div>

              <div class="form-group">
                  <label>Largeur (pi)</label>
                  <input type="number" step="0.1" min="0" name="bath_width">
              </div>

              <div class="form-group">
                  <label>Hauteur du plafond (pi)</label>
                  <input type="number" step="0.1" min="0" name="ceiling_height">
              </div>
          </div>

          <!-- Existing Problems -->
          <h3>Conditions actuelles</h3>
          <div class="checkbox-columns">
              <label><input type="checkbox" name="issues[]" value="water_damage"> Dégâts d'eau</label>
              <label><input type="checkbox" name="issues[]" value="mold"> Moisissure</label>
              <label><input type="checkbox" name="issues[]" value="leaks"> Fuite d'eau</label>
              <label><input type="checkbox" name="issues[]" value="soft_floor"> Plancher mou</label>
              <label><input type="checkbox" name="issues[]" value="cracked_tiles"> Céramique fissurée</label>
              <label><input type="checkbox" name="issues[]" value="none"> Aucun problème connu</label>
          </div>

          <!-- Demolition -->
          <h3>Démolition requise</h3>
          <div class="checkbox-columns">
              <label><input type="checkbox" name="demo[]" value="vanity"> Retirer la vanité</label>
              <label><input type="checkbox" name="demo[]" value="toilet"> Retirer la toilette</label>
              <label><input type="checkbox" name="demo[]" value="bathtub"> Retirer le bain</label>
              <label><input type="checkbox" name="demo[]" value="shower"> Retirer la douche</label>
              <label><input type="checkbox" name="demo[]" value="floor_tile"> Retirer le plancher</label>
              <label><input type="checkbox" name="demo[]" value="wall_tile"> Retirer les céramiques murales</label>
              <label><input type="checkbox" name="demo[]" value="drywall"> Retirer le gypse</label>
              <label><input type="checkbox" name="demo[]" value="ceiling"> Retirer le plafond</label>
          </div>

          <!-- Plumbing -->
          <h3>Travaux de plomberie</h3>
          <div class="checkbox-columns">
              <label><input type="checkbox" name="plumbing[]" value="replace_toilet"> Nouvelle toilette</label>
              <label><input type="checkbox" name="plumbing[]" value="replace_vanity"> Nouvelle vanité</label>
              <label><input type="checkbox" name="plumbing[]" value="replace_sink"> Nouveau lavabo</label>
              <label><input type="checkbox" name="plumbing[]" value="replace_faucet"> Nouveau robinet</label>
              <label><input type="checkbox" name="plumbing[]" value="replace_shower_head"> Nouvelle pomme de douche</label>
              <label><input type="checkbox" name="plumbing[]" value="replace_tub"> Nouveau bain</label>
              <label><input type="checkbox" name="plumbing[]" value="move_plumbing"> Déplacer les conduites de plomberie</label>
          </div>

          <!-- Electrical -->
          <h3>Travaux électriques</h3>
          <div class="checkbox-columns">
              <label><input type="checkbox" name="electrical[]" value="potlights"> Ajouter des encastrés (pot lights)</label>
              <label><input type="checkbox" name="electrical[]" value="fan_replace"> Remplacer le ventilateur</label>
              <label><input type="checkbox" name="electrical[]" value="fan_new"> Ajouter un ventilateur</label>
              <label><input type="checkbox" name="electrical[]" value="gfci"> Ajouter une prise GFCI</label>
              <label><input type="checkbox" name="electrical[]" value="heated_floor"> Plancher chauffant</label>
              <label><input type="checkbox" name="electrical[]" value="led_mirror"> Miroir DEL</label>
          </div>

          <!-- DOUCHE -->
          <h3>Douche</h3>
          <div class="checkbox-columns">
            <label>
              <input type="checkbox" name="shower[]" value="replace_standard_shower">
              Remplacer la douche standard
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="walk_in_shower">
              Douche italienne (Walk-In)
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="glass_shower">
              Porte de douche en verre
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="frameless_glass">
              Verre sans cadre
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="rain_shower">
              Pomme de douche pluie
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="body_jets">
              Jets corporels
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="linear_drain">
              Drain linéaire
            </label>
            <label>
              <input type="checkbox" name="shower[]" value="waterproof_membrane">
              Membrane d'étanchéité
            </label>
          </div>

          <!-- PLANCHER (Choix unique - Radio) -->
          <h3>Plancher</h3>
          <div class="checkbox-columns">
            <label>
              <input type="radio" name="floor" value="ceramic">
              Céramique
            </label>
            <label>
              <input type="radio" name="floor" value="porcelain">
              Porcelaine
            </label>
            <label>
              <input type="radio" name="floor" value="luxury_vinyl">
              Vinyle de luxe
            </label>
            <label>
              <input type="radio" name="floor" value="natural_stone">
              Pierre naturelle
            </label>
          </div>

          <!-- VANITÉ (Choix unique - Radio) -->
          <h3>Vanité</h3>
          <div class="checkbox-columns">
            <label>
              <input type="radio" name="vanity" value="24">
              Vanité 24"
            </label>
            <label>
              <input type="radio" name="vanity" value="30">
              Vanité 30"
            </label>
            <label>
              <input type="radio" name="vanity" value="36">
              Vanité 36"
            </label>
            <label>
              <input type="radio" name="vanity" value="48">
              Vanité 48"
            </label>
            <label>
              <input type="radio" name="vanity" value="60">
              Vanité 60"
            </label>
          </div>

          <!-- COMPTOIR (Choix unique - Radio) -->
          <h3>Comptoir</h3>
          <div class="checkbox-columns">
            <label>
              <input type="radio" name="countertop" value="laminate">
              Stratifié
            </label>
            <label>
              <input type="radio" name="countertop" value="quartz">
              Quartz
            </label>
            <label>
              <input type="radio" name="countertop" value="granite">
              Granit
            </label>
            <label>
              <input type="radio" name="countertop" value="marble">
              Marbre
            </label>
            <label>
              <input type="radio" name="countertop" value="none">
              Aucun / Inclus avec vanité
            </label>
          </div>

          <!-- PEINTURE -->
          <h3>Peinture</h3>
          <div class="checkbox-columns">
            <label>
              <input type="checkbox" name="painting[]" value="walls">Murs
            </label>
            <label>
              <input type="checkbox" name="painting[]" value="ceiling">Plafond
            </label>
            <label>
              <input type="checkbox" name="painting[]" value="door">Porte
            </label>
            <label>
              <input type="checkbox" name="painting[]" value="trim">Moulures
            </label>
          </div>

          <h3>Budget approximatif</h3>
          <div class="checkbox-columns">
            <label>
              <input type="radio" name="budget_range" value="under_5000">
              Moins de 5 000 $
            </label>
            <label>
              <input type="radio" name="budget_range" value="5000_10000">
              5 000 $ à 10 000 $
            </label>
            <label>
              <input type="radio" name="budget_range" value="10000_20000">
              10 000 $ à 20 000 $
            </label>
            <label>
              <input type="radio" name="budget_range" value="20000_40000">
              20 000 $ à 40 000 $
            </label>
            <label>
              <input type="radio" name="budget_range" value="40000_plus">
              40 000 $ et plus
            </label>
          </div>

          <h3>Style désiré</h3>
          <div class="checkbox-columns">
            <label>
              <input type="radio" name="design_style" value="modern">
              Moderne
            </label>
            <label>
              <input type="radio" name="design_style" value="contemporary">
              Contemporain
            </label>
            <label>
              <input type="radio" name="design_style" value="scandinavian">
              Scandinave
            </label>
            <label>
              <input type="radio" name="design_style" value="traditional">
              Traditionnel
            </label>
            <label>
              <input type="radio" name="design_style" value="farmhouse">
              Farmhouse
            </label>
            <label>
              <input type="radio" name="design_style" value="luxury">
              Spa / Luxe
            </label>
          </div>

          <h3>Échéancier</h3>
          <div class="form-group">
              <label>Date souhaitée pour débuter</label>
              <input
                  type="date"
                  name="desired_start_date"
                  style="max-width:170px;"
              >
          </div>
          <div class="priority-row">
            <span class="field-title">Priorité du projet</span>
            <div class="priority-inline">
              <label><input type="radio" name="project_priority" value="urgent"> ASAP</label>
              <label><input type="radio" name="project_priority" value="not_urgent"> Pas urgent</label>
              <label><input type="radio" name="project_priority" value="next_month"> 1 mois</label>
              <label><input type="radio" name="project_priority" value="next_2_months"> 2 mois</label>
            </div>
          </div>
      </article>

      <!-- Notes supplémentaires -->
      <article class="card quote-card">
        <h2>Notes supplémentaires</h2>
        <div class="form-group">
          <label for="bath-notes" class="sr-only">Notes supplémentaires</label>
          <textarea id="bath-notes" name="bath_notes" rows="3" placeholder="Précisions sur votre projet, contraintes particulières..."></textarea>
        </div>
      </article>

      <!-- Photos Upload -->
      <article class="card quote-card">
        <h2>Photos du projet</h2>
        <div class="form-row-2">
          <div class="form-group">
            <label for="photo-current">Photos de la salle de bain actuelle</label>
            <input type="file" id="photo-current" name="photo_current[]" multiple accept="image/*">
            <div id="photo-current-grid" class="photo-thumb-grid"></div>
          </div>

          <div class="form-group">
            <label for="photo-desired">Photos du style désiré</label>
            <input type="file" id="photo-desired" name="photo_desired[]" multiple accept="image/*">
            <div id="photo-desired-grid" class="photo-thumb-grid"></div>
          </div>
        </div>
      </article>

      <!-- Actions -->
      <article class="card quote-card">
        <h2>Actions</h2>
        <div class="quote-actions">
          <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 15px; cursor: pointer;">
            <i class="fa-solid fa-paper-plane"></i> Envoyer ma demande de soumission
          </button>
        </div>
        <p class="form-note">La soumission sera analysée par l'équipe d'Auger Rénovation.</p>
      </article>

    </form>

  </div>
</section>

<footer class="site-footer">
  <div class="container footer-inner">
    <div>
      <p>&copy; <span id="year"></span> Habitations A Inc. / Auger Rénovation. Tous droits réservés.</p>
      <p>RBQ : 58392747-01 | Vaudreuil-Dorion, QC, Canada</p>
    </div>
    <div class="footer-links">
      <a href="accueil.html">Accueil</a>
      <a href="realisations.html">Réalisations</a>
      <a href="services.html">Services</a>
      <a href="contact.html">Contact</a>
    </div>
    <div class="footer-socials">
      <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
      <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
      <a href="mailto:auger.renovation@gmail.com" aria-label="Email"><i class="fa-solid fa-envelope"></i></a>
    </div>
  </div>
</footer>

<!-- Lightbox Modal for Photo Thumbnails -->
<div id="photo-lightbox" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center; padding:20px; cursor:zoom-out;">
  <button type="button" style="position:absolute; top:20px; right:25px; background:none; border:none; color:#fff; font-size:32px; cursor:pointer;">&times;</button>
  <img id="lightbox-img" src="" alt="Aperçu agrandi" style="max-width:92%; max-height:90vh; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
</div>

<!-- 1. Shared core engine -->
<script src="assets/js/quote-engine.js"></script>

<!-- 2. Shared tier preset engine -->
<script src="assets/js/quote-tiers.js"></script>

<!-- 3. Bathroom-specific logic -->
<script src="assets/js/quote-bathroom.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const lightbox = document.getElementById('photo-lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    if (!lightbox || !lightboxImg) return;

    document.addEventListener('click', (e) => {
      const thumbImg = e.target.closest('.photo-thumb-item img');
      if (thumbImg) {
        lightboxImg.src = thumbImg.src;
        lightbox.style.display = 'flex';
      }
    });

    lightbox.addEventListener('click', () => {
      lightbox.style.display = 'none';
      lightboxImg.src = '';
    });
  });
</script>

</body>
</html>