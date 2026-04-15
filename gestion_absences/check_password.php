<?php
require_once '1_config/database.php';

$stmt = $pdo->query("SELECT email, password FROM enseignants");
$enseignants = $stmt->fetchAll();

echo "<h1>Vérification des mots de passe</h1>";

foreach ($enseignants as $e) {
    echo "<h3>Email: " . $e['email'] . "</h3>";
    echo "Hash stocké: " . $e['password'] . "<br>";
    
    // Tester avec 'password123'
    if (password_verify('password123', $e['password'])) {
        echo "<span style='color: green;'>✅ 'password123' fonctionne !</span><br>";
    } else {
        echo "<span style='color: red;'>❌ 'password123' ne fonctionne pas</span><br>";
        
        // Créer un nouveau hash
        $nouveau_hash = password_hash('password123', PASSWORD_DEFAULT);
        echo "Nouveau hash à utiliser: <code>$nouveau_hash</code><br>";
        
        // Mettre à jour
        $update = $pdo->prepare("UPDATE enseignants SET password = ? WHERE email = ?");
        $update->execute([$nouveau_hash, $e['email']]);
        echo "<span style='color: green;'>✅ Mot de passe mis à jour !</span><br>";
    }
    echo "<hr>";
}

echo "<p><a href='login.php'>Aller à la page de connexion</a></p>";
?>