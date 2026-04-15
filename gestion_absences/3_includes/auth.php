<?php
function estConnecte() {
    return isset($_SESSION['enseignant_id']);
}

function verifierAuth() {
    if (!estConnecte()) {
        header('Location: login.php');
        exit();
    }
}

function getEnseignantInfo() {
    global $pdo;
    if (estConnecte()) {
        $stmt = $pdo->prepare("SELECT * FROM enseignants WHERE id = ?");
        $stmt->execute([$_SESSION['enseignant_id']]);
        return $stmt->fetch();
    }
    return null;
}
?>