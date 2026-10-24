<?php
session_start();

// 1. MASTER PASSWORD: Use this to log in right now!
$MASTER_PASSWORD = 'Auger2026!'; 

// 2. LOAD SECURE MANAGER PASSWORDS
$dataDir = __DIR__ . '/data';
$passwordsFile = $dataDir . '/passwords.json';

if (!is_dir($dataDir)) { mkdir($dataDir, 0777, true); }
if (!file_exists($passwordsFile)) { file_put_contents($passwordsFile, json_encode([])); }

$managerPasswords = json_decode(file_get_contents($passwordsFile), true) ?: [];

// 3. LOGIN HANDLING
if (isset($_POST['password'])) {
    $inputPass = $_POST['password'];
    $isAuthenticated = false;

    // Check Master Password first
    if ($inputPass === $MASTER_PASSWORD) {
        $isAuthenticated = true;
    } else {
        // If not Master, check against encrypted manager passwords
        foreach ($managerPasswords as $entry) {
            if (password_verify($inputPass, $entry['hash'])) {
                $isAuthenticated = true;
                break;
            }
        }
    }

    if ($isAuthenticated) {
        $_SESSION['logged_in'] = true;
        
        if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
            header("Location: " . $_POST['redirect']);
            exit;
        }
        header("Location: admin.php");
        exit;
    } else {
        $login_error = "Mot de passe incorrect.";
    }
}

// IF NOT LOGGED IN: Show Login Form
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Connexion | Auger Rénovation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: system-ui, sans-serif; background: #f6f8fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
    .login-card { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
    input[type="password"] { width: 100%; padding: 12px; margin: 20px 0; border: 1px solid #d0d7de; border-radius: 6px; box-sizing: border-box; }
    button { width: 100%; background: #d1b06a; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
    button:hover { background: #c2a15b; }
    .error { color: #c92a2a; margin-bottom: 10px; font-size: 14px; }
  </style>
</head>
<body>
  <div class="login-card">
    <h2><i class="fa-solid fa-lock" style="color: #d1b06a;"></i> Accès Sécurisé</h2>
    <?php if (isset($login_error)) echo "<div class='error'>$login_error</div>"; ?>
    <form method="post">
      <input type="password" name="password" placeholder="Mot de passe..." required>
      <button type="submit">Se connecter</button>
    </form>
  </div>
</body>
</html>
<?php
exit;
}

// --- LOGGED IN DASHBOARD ---
$siteTextFile = __DIR__ . '/data/site-text.json';
if (!file_exists($siteTextFile)) { file_put_contents($siteTextFile, json_encode(['owner_name'=>'', 'phone'=>'', 'email'=>''])); }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['owner_name'])) {
    $data = [
        'owner_name' => $_POST['owner_name'],
        'phone'      => $_POST['phone'],
        'email'      => $_POST['email'],
    ];
    file_put_contents($siteTextFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = 'Informations de contact mises à jour avec succès.';
}

$data = json_decode(file_get_contents($siteTextFile), true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Tableau de bord | Auger Rénovation</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: system-ui, sans-serif; background: #f6f8fa; margin: 0; color: #24292e; }
    .header { background: #24292e; color: #fff; padding: 20px; text-align: center; }
    .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .card { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 25px; text-align: center; text-decoration: none; color: #24292e; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s, box-shadow 0.2s; }
    .card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.08); border-color: #d1b06a; }
    .card i { font-size: 32px; color: #d1b06a; margin-bottom: 15px; }
    .card h3 { margin: 0 0 10px 0; font-size: 18px; }
    .card p { margin: 0; color: #666; font-size: 14px; }
    .settings-panel { background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 25px; }
    .settings-panel h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; }
    .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .btn-save { background: #24292e; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-save:hover { background: #000; }
    .success-msg { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
  </style>
</head>
<body>

  <div class="header">
    <h1 style="margin:0;">Auger Rénovation - Administration</h1>
  </div>

  <div class="container">
    
    <div class="grid">
      <a href="gestion-photos.php" class="card">
        <i class="fa-solid fa-camera"></i>
        <h3>Galerie Photos</h3>
        <p>Ajouter des photos aux réalisations</p>
      </a>
      
      <a href="requests.php" class="card">
        <i class="fa-solid fa-envelope-open-text"></i>
        <h3>Demandes</h3>
        <p>Voir les demandes des clients</p>
      </a>

      <a href="materials.html" class="card">
        <i class="fa-solid fa-cubes"></i>
        <h3>Catalogue Matériaux</h3>
        <p>Gérer les prix Home Depot</p>
      </a>

      <a href="quote-bathroom.php" class="card">
        <i class="fa-solid fa-calculator"></i>
        <h3>Soumission Rapide</h3>
        <p>Créer un estimé général indépendant</p>
      </a>

      <!-- NEW PASSWORD MANAGER CARD -->
      <a href="gestion-passwords.php" class="card">
        <i class="fa-solid fa-key"></i>
        <h3>Mots de passe</h3>
        <p>Gérer les accès des gérants</p>
      </a>
    </div>

    <div class="settings-panel">
      <h2><i class="fa-solid fa-address-card"></i> Informations de contact du site</h2>
      <?php if ($message) echo "<div class='success-msg'><i class='fa-solid fa-check'></i> $message</div>"; ?>
      <form method="post">
        <div class="form-group">
          <label>Nom du responsable :</label>
          <input type="text" name="owner_name" value="<?= htmlspecialchars($data['owner_name']) ?>" required>
        </div>
        <div class="form-group">
          <label>Téléphone :</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($data['phone']) ?>" required>
        </div>
        <div class="form-group">
          <label>Courriel :</label>
          <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>" required>
        </div>
        <button type="submit" class="btn-save">Mettre à jour le site</button>
      </form>
    </div>
  </div>

</body>
</html>