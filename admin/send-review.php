<?php
session_start();

// --- Auth check ---
$loggedIn = false;
foreach (['admin_logged_in', 'admin', 'logged_in', 'password_ok'] as $k) {
    if (!empty($_SESSION[$k]) && $_SESSION[$k] === true) { $loggedIn = true; break; }
}
if (!$loggedIn) { header('Location: admin.php'); exit; }

require_once __DIR__ . '/lib/mailer.php';

$requestsFile = __DIR__ . '/data/requests.json';
$reviewLogFile = __DIR__ . '/data/review-requests.json';
$settingsFile = __DIR__ . '/data/tenant-settings.json';

$requests = file_exists($requestsFile) ? (json_decode(file_get_contents($requestsFile), true) ?: []) : [];
$reviewLog = file_exists($reviewLogFile) ? (json_decode(file_get_contents($reviewLogFile), true) ?: []) : [];
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];

$reviewUrl = $settings['google_review_url'] ?? '';

$message = '';

// Send review handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_review') {
    $reqId = $_POST['request_id'] ?? '';
    $req = $requests[$reqId] ?? null;

    if (!$req) {
        $message = '❌ Demande introuvable.';
    } elseif (empty($req['client']['email'])) {
        $message = '❌ Ce client n\'a pas fourni d\'email.';
    } elseif (empty($reviewUrl)) {
        $message = '❌ Aucune URL Google Review configurée. Voir Paramètres.';
    } elseif (!mail_configured()) {
        $message = '❌ SMTP non configuré. Voir Paramètres.';
    } else {
        $clientName = $req['client']['name'] ?: 'Cher client';
        $clientEmail = $req['client']['email'];

        $subject = "Votre avis compte pour Auger Renovations";
        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#003366;'>Bonjour {$clientName},</h2>
            <p>Merci d'avoir choisi <strong>Auger Renovations</strong> pour votre projet.</p>
            <p>Nous espérons que vous êtes satisfait du résultat. Auriez-vous 30 secondes pour partager votre expérience sur Google? Votre avis aide d'autres clients à nous trouver.</p>
            <p style='text-align:center;margin:30px 0;'>
                <a href='{$reviewUrl}' style='display:inline-block;background:#4285f4;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:700;font-size:1rem;'>
                    ⭐ Laisser un avis Google
                </a>
            </p>
            <p style='color:#666;font-size:.9rem;'>Moins de 30 secondes — merci d'avance.</p>
            <p style='margin-top:24px;'>Cordialement,<br><strong>Auger Renovations</strong></p>
        </div>
        ";

        $result = send_mail($clientEmail, $subject, $html);
        if ($result['success']) {
            // Log the request
            $reviewLog[] = [
                'request_id' => $reqId,
                'customer_email' => $clientEmail,
                'customer_name' => $clientName,
                'sent_at' => date('Y-m-d H:i:s'),
            ];
            file_put_contents($reviewLogFile, json_encode($reviewLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Track event
            require_once __DIR__ . '/lib/tracker.php';
            track_event('review_request', 'admin/send-review.php', ['request_id' => $reqId]);

            $message = "✅ Demande d'avis envoyée à {$clientName} ({$clientEmail}).";
        } else {
            $message = '❌ Échec: ' . htmlspecialchars($result['error']);
        }
    }
}

// Filter: only completed requests (status = 'completed' or 'done' or similar)
// Adjust to Auger's status values
$completedStatuses = ['completed', 'done', 'finished', 'closed'];
$eligibleRequests = [];
foreach ($requests as $rid => $r) {
    $status = strtolower($r['status'] ?? 'pending');
    if (in_array($status, $completedStatuses, true) || !empty($_GET['all'])) {
        $eligibleRequests[$rid] = $r;
    }
}

// Reverse chronological
$eligibleRequests = array_reverse($eligibleRequests, true);

// Track which requests already had a review sent
$alreadySent = [];
foreach ($reviewLog as $entry) {
    $alreadySent[$entry['request_id']] = $entry['sent_at'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Demandes d'avis — Auger Renovations</title>
<style>
body { font-family: -apple-system, Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 30px; color: #333; }
.container { max-width: 1000px; margin: 0 auto; }
h1 { color: #003366; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 24px; }
.message { background: #e3f2fd; color: #003366; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #003366; }
.warning { background: #fff3cd; color: #856404; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107; }
table { width: 100%; border-collapse: collapse; }
th { background: #003366; color: #fff; padding: 12px; text-align: left; font-size: .82rem; text-transform: uppercase; letter-spacing: .4px; }
td { padding: 12px; border-bottom: 1px solid #eef1f5; font-size: .92rem; }
tr:hover { background: #f9fafb; }
.btn { display: inline-block; background: #4285f4; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; border: none; cursor: pointer; font-size: .85rem; }
.btn:hover { background: #3367d6; }
.btn-disabled { background: #ddd; color: #888; cursor: not-allowed; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .75rem; font-weight: 700; }
.badge-sent { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }
.back { color: #003366; text-decoration: none; font-weight: 600; }
code { background: #f0f4fa; padding: 2px 8px; border-radius: 4px; font-size: .85rem; }
</style>
</head>
<body>
<div class="container">
    <h1>⭐ Demandes d'avis Google</h1>
    <p><a href="admin.php" class="back">← Retour admin</a> · <a href="settings.php" class="back">⚙️ Paramètres</a></p>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <?php if (empty($reviewUrl)): ?>
        <div class="warning">
            ⚠️ <strong>Aucune URL Google Review configurée.</strong> Allez dans <a href="settings.php">Paramètres</a> et ajoutez votre lien Google Review avant d'envoyer des demandes.
        </div>
    <?php endif; ?>

    <?php if (!mail_configured()): ?>
        <div class="warning">
            ⚠️ <strong>SMTP non configuré.</strong> Allez dans <a href="settings.php">Paramètres</a> pour configurer l'envoi d'emails.
        </div>
    <?php endif; ?>

    <div class="card">
        <p style="margin-top:0;">
            <strong><?= count($eligibleRequests) ?></strong> demande(s) éligible(s) pour une demande d'avis.
            <a href="?all=1" style="margin-left:12px;color:#003366;">Voir toutes les demandes</a>
        </p>

        <?php if (empty($eligibleRequests)): ?>
            <p style="text-align:center; padding: 40px; color: #888;">
                Aucune demande complétée pour l'instant. Marquez d'abord des demandes comme "complétées" avant d'envoyer des demandes d'avis.
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Type projet</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eligibleRequests as $rid => $r):
                        $clientName = $r['client']['name'] ?? 'Client';
                        $clientEmail = $r['client']['email'] ?? '';
                        $projectType = $r['project']['type'] ?? '-';
                        $wasSent = $alreadySent[$rid] ?? null;
                    ?>
                    <tr>
                        <td><code><?= htmlspecialchars($rid) ?></code></td>
                        <td><?= htmlspecialchars($clientName) ?></td>
                        <td><?= htmlspecialchars($clientEmail) ?: '<em style="color:#aaa;">(aucun)</em>' ?></td>
                        <td><?= htmlspecialchars($projectType) ?></td>
                        <td>
                            <?php if ($wasSent): ?>
                                <span class="badge badge-sent">✓ Envoyé <?= substr($wasSent, 0, 10) ?></span>
                            <?php else: ?>
                                <span class="badge badge-pending">En attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($wasSent): ?>
                                <button class="btn btn-disabled" disabled>Déjà envoyé</button>
                            <?php elseif (empty($clientEmail)): ?>
                                <button class="btn btn-disabled" disabled title="Email manquant">Pas d'email</button>
                            <?php else: ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Envoyer une demande d\'avis à <?= htmlspecialchars(addslashes($clientName)) ?>?');">
                                    <input type="hidden" name="action" value="send_review">
                                    <input type="hidden" name="request_id" value="<?= htmlspecialchars($rid) ?>">
                                    <button type="submit" class="btn">⭐ Envoyer demande</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>