<?php
session_start();

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

$passwordsFile = __DIR__ . '/data/passwords.json';
$passwords = json_decode(file_get_contents($passwordsFile), true) ?: [];
$message = '';

// HANDLE DELETE PASSWORD
if (isset($_GET['delete'])) {
    $idToDelete = $_GET['delete'];
    foreach ($passwords as $index => $entry) {
        if ($entry['id'] === $idToDelete) {
            array_splice($passwords, $index, 1);
            file_put_contents($passwordsFile, json_encode($passwords, JSON_PRETTY_PRINT));
            header("Location: gestion-passwords.php?msg=deleted");
            exit;
        }
    }
}

// HANDLE NEW PASSWORD CREATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $name = trim($_POST['manager_name']);
    $newPass = trim($_POST['new_password']);
    
    if (!empty($name) && !empty($newPass)) {
        // Encrypt the password securely (cannot be reverse-engineered)
        $hashedPassword = password_hash($newPass, PASSWORD_DEFAULT);
        
        $passwords[] = [
            'id' => uniqid('pass_'),
            'name' => $name,
            'hash' => $hashedPassword,
            'created' => date('Y-m-d')
        ];
        
        file_put_contents($passwordsFile, json_encode($passwords, JSON_PRETTY_PRINT));
        $message = "Mot de passe pour '$name' créé avec succès !";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Accès supprimé.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des Mots de Passe</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: system-ui, sans-serif; background: #f6f8fa; margin: 0; color: #24292e; }
    .topbar { background: #24292e; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
    .topbar a { color: #fff; text-decoration: none; font-weight: bold; background: #444; padding: 8px 12px; border-radius: 4px; }
    .topbar a:hover { background: #d1b06a; }
    .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #d0d7de; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 15px; }
    label { font-weight: bold; display: block; margin-bottom: 5px; }
    .btn-submit { background: #24292e; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; }
    .btn-submit:hover { background: #000; }
    .success-msg { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
    .pass-list { list-style: none; padding: 0; margin: 0; }
    .pass-list li { background: #f9fafb; padding: 15px; border: 1px solid #e1e4e8; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
    .btn-delete { color: #c92a2a; text-decoration: none; font-weight: bold; background: #ffe3e3; padding: 6px 10px; border-radius: 4px; }
    .btn-delete:hover { background: #ffc9c9; }
  </style>
</head>
<body>

  <div class="topbar">
    <div style="font-size: 18px;"><strong>Auger Rénovation</strong> — Mots de passe</div>
    <a href="admin.php"><i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord</a>
  </div>

  <div class="container">
    <?php if ($message) echo "<div class='success-msg'><i class='fa-solid fa-check'></i> $message</div>"; ?>

    <div class="card">
      <h2><i class="fa-solid fa-user-plus" style="color: #d1b06a;"></i> Créer un accès pour un gérant</h2>
      <p style="color: #666; font-size: 14px;">Les mots de passe sont encryptés. Il est impossible de les récupérer après leur création, seulement de les supprimer.</p>
      
      <form method="post">
        <label>Nom du gérant (ex: Simon) :</label>
        <input type="text" name="manager_name" required placeholder="Nom du propriétaire du mot de passe">
        
        <label>Nouveau mot de passe :</label>
        <input type="password" name="new_password" required placeholder="Saisir un mot de passe sécuritaire">
        
        <button type="submit" class="btn-submit">Sauvegarder l'accès</button>
      </form>
    </div>

    <div class="card">
      <h2><i class="fa-solid fa-users"></i> Accès Actifs</h2>
      <?php if (empty($passwords)): ?>
        <p style="color: #666;">Aucun mot de passe gérant configuré pour le moment.</p>
      <?php else: ?>
        <ul class="pass-list">
          <?php foreach ($passwords as $p): ?>
            <li>
              <div>
                <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                <span style="font-size: 12px; color: #666;">Créé le: <?= $p['created'] ?></span>
              </div>
              <a href="?delete=<?= $p['id'] ?>" class="btn-delete" onclick="return confirm('Supprimer cet accès définitivement ?');">
                <i class="fa-solid fa-trash"></i> Révoquer
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>