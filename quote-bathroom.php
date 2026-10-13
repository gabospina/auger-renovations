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

        <div class="form-row-3">
          <div class="form-group">
            <label for="bath-type">Type de salle de bain</label>
            <select id="bath-type" name="bath_type">
              <option value="complete">Complète</option>
              <option value="partielle">Partielle</option>
              <option value="ensuite">Ensuite</option>
            </select>
          </div>

          <div class="form-group">
            <label for="bath-condition">État actuel</label>
            <select id="bath-condition" name="bath_condition">
              <option value="bon">Bon</option>
              <option value="moyen" selected>Moyen</option>
              <option value="mauvais">Mauvais</option>
            </select>
          </div>

          <div class="form-group">
            <label for="bath-size">Superficie (pi²)</label>
            <input type="number" id="bath-size" name="bath_size" min="0">
          </div>
        </div>

        <div class="form-group" style="margin-top: 10px;">
          <label>Travaux souhaités</label>
          <div class="checkbox-columns">
            <label><input type="checkbox" name="work[]" value="Retirer la douche"> Retirer la douche</label>
            <label><input type="checkbox" name="work[]" value="Ajouter un bain"> Ajouter un bain</label>
            <label><input type="checkbox" name="work[]" value="Remplacer le plancher"> Remplacer le plancher</label>
            <label><input type="checkbox" name="work[]" value="Remplacer la vanité"> Remplacer la vanité</label>
            <label><input type="checkbox" name="work[]" value="Remplacer le closet"> Remplacer le closet</label>
            <label><input type="checkbox" name="work[]" value="Peinture murs et plafond"> Peinture murs et plafond</label>
            <label><input type="checkbox" name="work[]" value="Remplacer la toilette"> Remplacer la toilette</label>
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

<!-- Relative path fix without leading slash -->
<!-- <script src="assets/js/quote-bathroom_v1.js"></script> -->

<!-- (Note: Since quote-bathroom.php is in your root directory, 
use assets/js/... rather than ../assets/js/... so the browser resolves the paths correctly). -->

<!-- 1. Shared core engine (Catalog, Autocomplete, Quebec Tax Math, Send Quote) -->
<script src="assets/js/quote-engine.js"></script>

<!-- 2. Shared tier preset engine (Économique / Standard / Haut de gamme) -->
<script src="assets/js/quote-tiers.js"></script>

<!-- 3. Bathroom-specific logic (Fixtures, Room Pre-fill, Photo Previews) -->
<script src="assets/js/quote-bathroom.js"></script>

</body>
</html>