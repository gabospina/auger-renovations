<?php
session_start();

$PASSWORD = 'MonMotDePasseFort123'; // change this

// LOGIN HANDLING
if (isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $_SESSION['logged_in'] = true;
    }
}

// If not logged in, show login form
if (!isset($_SESSION['logged_in'])) {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Admin – Connexion</title>
</head>
<body>
  <h1>Connexion administrateur</h1>
  <form method="post">
    <label>Mot de passe :</label>
    <input type="password" name="password">
    <button type="submit">Entrer</button>
  </form>
</body>
</html>
<?php
exit;
}

// PATH TO JSON FILE
$dataFile = __DIR__ . '/data/site-text.json';

// SAVE CONTACT INFO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['owner_name'])) {
    $data = [
        'owner_name' => $_POST['owner_name'],
        'phone'      => $_POST['phone'],
        'email'      => $_POST['email'],
    ];
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $message = 'Informations mises à jour.';
}

// LOAD CONTACT INFO
$data = json_decode(file_get_contents($dataFile), true);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Admin – Infos contact</title>
</head>
<body>
  <h1>Admin – Infos contact</h1>

  <?php if (!empty($message)) echo "<p>$message</p>"; ?>

  <form method="post">
    <label>Nom :</label>
    <input type="text" name="owner_name" value="<?= htmlspecialchars($data['owner_name']) ?>"><br>

    <label>Téléphone :</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($data['phone']) ?>"><br>

    <label>Courriel :</label>
    <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>"><br>

    <button type="submit">Sauvegarder</button>
  </form>

  <hr>

  <a href="quote-bathroom.php">Créer une soumission salle de bain</a>

</body>
</html>
