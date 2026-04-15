<?php
/**
 * Page d'accueil - Redirection vers login ou dashboard
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['enseignant_id'])) {
    // Rediriger vers le tableau de bord
    header('Location: dashboard.php');
    exit();
} else {
    // Rediriger vers la page de connexion
    header('Location: login.php');
    exit();
}
?>