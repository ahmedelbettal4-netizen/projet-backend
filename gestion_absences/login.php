<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';

if (estConnecte()) {
    header('Location: dashboard.php');
    exit();
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM enseignants WHERE email = ?");
    $stmt->execute([$email]);
    $enseignant = $stmt->fetch();
    
    if ($enseignant && password_verify($password, $enseignant['password'])) {
        $_SESSION['enseignant_id'] = $enseignant['id'];
        $_SESSION['enseignant_nom'] = $enseignant['nom'] . ' ' . $enseignant['prenom'];
        header('Location: dashboard.php');
        exit();
    } else {
        $erreur = 'Email ou mot de passe incorrect';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestion des Absences</title>
    <link rel="stylesheet" href="2_assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>📚 Gestion des Absences</h1>
            <h2>Connexion Enseignant</h2>
            
            <?php if ($erreur): ?>
                <div class="alert alert-error"><?php echo $erreur; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" value="prof@example.com" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe :</label>
                    <input type="password" id="password" name="password" value="password123" required>
                </div>
                
                <button type="submit" class="btn-primary">Se connecter</button>
            </form>
            
            <div class="demo-info">
                <p>📝 Compte démo : prof@example.com / password123</p>
            </div>
        </div>
    </div>
</body>
</html>