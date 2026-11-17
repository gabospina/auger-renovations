<?php
session_start();

// Auth check
$loggedIn = false;
foreach (['admin_logged_in', 'admin', 'logged_in', 'password_ok'] as $k) {
    if (!empty($_SESSION[$k]) && $_SESSION[$k] === true) { $loggedIn = true; break; }
}
if (!$loggedIn) { header('Location: admin.php'); exit; }

$progressFile = __DIR__ . '/data/gbp-progress.json';
$siteTextFile = __DIR__ . '/data/site-text.json';

$progress = file_exists($progressFile) ? (json_decode(file_get_contents($progressFile), true) ?: []) : [];
$siteData = file_exists($siteTextFile) ? (json_decode(file_get_contents($siteTextFile), true) ?: []) : [];

// Toggle handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $itemId = intval($_POST['item_id']);
    $progress[$itemId] = !($progress[$itemId] ?? false);
    file_put_contents($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    header('Location: gbp-checklist.php');
    exit;
}

// Checklist items
$items = [
    1 => [
        'title' => 'Revendiquer votre fiche Google',
        'desc' => 'Si ce n\'est pas déjà fait, revendiquez votre fiche gratuite à business.google.com. Utilisez le nom exact qui figure sur votre enseigne.',
    ],
    2 => [
        'title' => 'Vérifier adresse, téléphone et heures',
        'desc' => 'Ces informations doivent être identiques sur votre site et sur Google. Google vérifie la cohérence entre les deux.',
    ],
    3 => [
        'title' => 'Choisir les bonnes catégories',
        'desc' => 'Catégorie principale : « Entrepreneur en rénovation » ou « Entreprise générale ». Ajoutez des sous-catégories : cuisines, salles de bains, sous-sols.',
    ],
    4 => [
        'title' => 'Ajouter 10 photos minimum',
        'desc' => 'Photos avant/après de vos réalisations. Google priorise les fiches avec des photos récentes. Ajoutez 2-3 photos par semaine idéalement.',
    ],
    5 => [
        'title' => 'Demander des avis clients',
        'desc' => 'Après chaque projet terminé, demandez un avis Google. Utilisez la page « Demandes d\'avis » du site pour envoyer un email automatique.',
    ],
    6 => [
        'title' => 'Ajouter vos services à la fiche',
        'desc' => 'Google permet maintenant de lister vos services directement sur la fiche : cuisines, salles de bains, sous-sols, etc. avec photos et prix.',
    ],
    7 => [
        'title' => 'Activer la messagerie Google',
        'desc' => 'Permet aux clients de vous envoyer un message directement depuis votre fiche Google. Répondez rapidement — les délais comptent.',
    ],
    8 => [
        'title' => 'Publier une mise à jour hebdomadaire',
        'desc' => 'Google Posts = mini-annonces. Publiez une fois par semaine : nouvelle réalisation, promo, avant/après. Chaque post est indexé par Google.',
    ],
];

$doneCount = count(array_filter($progress, fn($v) => $v === true));
$total = count($items);
$percent = round(($doneCount / $total) * 100);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checklist Google Business — Auger Renovations</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body { font-family: system-ui, sans-serif; background: #f6f8fa; margin: 0; padding: 30px; color: #24292e; }
.container { max-width: 900px; margin: 0 auto; }
.header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
h1 { color: #24292e; margin: 0; }
.back-link { color: #24292e; text-decoration: none; font-weight: 600; }
.card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
.card h2 { margin-top: 0; color: #24292e; font-size: 1.15rem; border-bottom: 1px solid #eee; padding-bottom: 12px; }
.progress-bar-wrap { background: #e5eaf0; border-radius: 999px; height: 28px; overflow: hidden; margin: 20px 0; position: relative; }
.progress-bar { background: linear-gradient(90deg, #d1b06a, #e5c98d); height: 100%; transition: width .4s ease; border-radius: 999px; }
.progress-bar-text { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #24292e; font-size: .9rem; }
.btn-google { display: inline-block; background: #4285f4; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 700; margin-top: 12px; }
.btn-google:hover { background: #3367d6; }
.checklist-item { display: flex; gap: 16px; padding: 20px; border: 1px solid #e5eaf0; border-radius: 10px; margin-bottom: 12px; background: #f9fafb; transition: all .2s; }
.checklist-item.done { background: #d1fae5; border-color: #10b981; }
.checklist-num { flex: 0 0 auto; width: 36px; height: 36px; border-radius: 50%; background: #24292e; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; }
.checklist-item.done .checklist-num { background: #10b981; }
.checklist-content { flex: 1; }
.checklist-title { font-weight: 700; color: #24292e; margin-bottom: 6px; font-size: 1rem; display: flex; align-items: center; gap: 10px; }
.checklist-item.done .checklist-title { text-decoration: line-through; color: #065f46; }
.checklist-desc { color: #555; font-size: .9rem; line-height: 1.55; margin: 0; }
.checklist-action { flex: 0 0 auto; align-self: center; }
.toggle-btn { background: #d1b06a; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: .85rem; font-family: inherit; }
.toggle-btn:hover { background: #c2a15b; }
.toggle-btn.undo { background: #6b7280; }
.toggle-btn.undo:hover { background: #4b5563; }
.info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 16px 20px; border-radius: 6px; color: #0d47a1; font-size: .92rem; line-height: 1.6; margin-bottom: 16px; }
@media (max-width: 640px) { .checklist-item { flex-direction: column; } .checklist-action { align-self: flex-start; } }
</style>
</head>
<body>
<div class="container">

    <div class="header-bar">
        <h1>🎯 Google Business Profile</h1>
        <a href="admin.php" class="back-link">← Retour admin</a>
    </div>

    <div class="card">
        <h2>Pourquoi c'est important</h2>
        <div class="info-box">
            Quand un client cherche « rénovation salle de bain Montréal » sur Google, votre <strong>fiche Google Business</strong> apparaît en premier — avant même votre site web. Les fiches complètes reçoivent <strong>7x plus de clics</strong> que les fiches incomplètes. C'est le levier marketing gratuit le plus puissant pour une entreprise locale.
        </div>
        <a href="https://business.google.com" target="_blank" rel="noopener" class="btn-google">
            <i class="fa-brands fa-google"></i> Ouvrir Google Business Profile
        </a>
    </div>

    <div class="card">
        <h2>Votre progression : <?= $doneCount ?> / <?= $total ?> (<?= $percent ?>%)</h2>
        <div class="progress-bar-wrap">
            <div class="progress-bar" style="width: <?= $percent ?>%;"></div>
            <div class="progress-bar-text"><?= $percent ?>% complété</div>
        </div>

        <?php foreach ($items as $id => $item):
            $isDone = $progress[$id] ?? false;
        ?>
            <div class="checklist-item <?= $isDone ? 'done' : '' ?>">
                <div class="checklist-num">
                    <?php if ($isDone): ?>
                        <i class="fa-solid fa-check"></i>
                    <?php else: ?>
                        <?= $id ?>
                    <?php endif; ?>
                </div>
                <div class="checklist-content">
                    <div class="checklist-title"><?= htmlspecialchars($item['title']) ?></div>
                    <p class="checklist-desc"><?= htmlspecialchars($item['desc']) ?></p>
                </div>
                <div class="checklist-action">
                    <form method="POST">
                        <input type="hidden" name="item_id" value="<?= $id ?>">
                        <button type="submit" class="toggle-btn <?= $isDone ? 'undo' : '' ?>">
                            <?= $isDone ? '↩️ Annuler' : '✓ Marquer fait' ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>📈 Suivre vos résultats</h2>
        <p>Une fois votre checklist complétée, surveillez ces métriques dans <a href="analytics.php" style="color:#d1b06a; font-weight:600;">Analytics</a> :</p>
        <ul style="line-height:1.9; color:#555;">
            <li><strong>Pages vues</strong> — trafic total sur votre site</li>
            <li><strong>Clics WhatsApp</strong> — clients qui vous contactent directement</li>
            <li><strong>Demandes de soumission</strong> — clients qualifiés prêts à acheter</li>
            <li><strong>Scans QR code</strong> — engagement in-store via vos codes imprimés</li>
        </ul>
        <p style="color:#666; font-size:.9rem; margin-bottom:0;">
            💡 <strong>Astuce :</strong> après avoir complété la checklist, comparez vos analytics au mois précédent. La plupart des entreprises voient <strong>20-50% d'augmentation</strong> des clics « directions » en 30 jours.
        </p>
    </div>

</div>
</body>
</html>