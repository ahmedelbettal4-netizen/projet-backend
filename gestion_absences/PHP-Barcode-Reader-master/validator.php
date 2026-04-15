<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté à votre application
if (!isset($_SESSION['enseignant_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter']);
    exit();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
    $seance_id = isset($_POST['seance_id']) ? intval($_POST['seance_id']) : (isset($_SESSION['current_seance_id']) ? $_SESSION['current_seance_id'] : 0);
    
    if (empty($barcode)) {
        $response['message'] = 'Code-barres vide';
        echo json_encode($response);
        exit();
    }
    
    if ($seance_id <= 0) {
        $response['message'] = 'Aucune séance active. Créez une séance d\'abord.';
        echo json_encode($response);
        exit();
    }
    
    // Chercher l'étudiant dans votre table 'etudiants'
    $stmt = $pdo->prepare("SELECT id, nom, prenom, matricule FROM etudiants WHERE code_barre = ?");
    $stmt->execute([$barcode]);
    $etudiant = $stmt->fetch();
    
    if ($etudiant) {
        // Vérifier si déjà présent
        $stmt = $pdo->prepare("SELECT statut FROM absences WHERE seance_id = ? AND etudiant_id = ?");
        $stmt->execute([$seance_id, $etudiant['id']]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['statut'] == 'present') {
            $response['success'] = true;
            $response['message'] = $etudiant['prenom'] . ' ' . $etudiant['nom'] . ' est déjà présent';
            $response['nom'] = $etudiant['nom'];
            $response['prenom'] = $etudiant['prenom'];
            $response['matricule'] = $etudiant['matricule'];
            $response['status'] = 'already_present';
        } else {
            // Marquer comme présent
            $stmt = $pdo->prepare("UPDATE absences SET statut = 'present', scan_time = NOW() 
                                   WHERE seance_id = ? AND etudiant_id = ?");
            $stmt->execute([$seance_id, $etudiant['id']]);
            
            $response['success'] = true;
            $response['message'] = '✓ Présence enregistrée pour ' . $etudiant['prenom'] . ' ' . $etudiant['nom'];
            $response['nom'] = $etudiant['nom'];
            $response['prenom'] = $etudiant['prenom'];
            $response['matricule'] = $etudiant['matricule'];
            $response['status'] = 'new_present';
        }
    } else {
        $response['message'] = '✗ Code-barres non reconnu. Veuillez ajouter cet étudiant.';
    }
    
    echo json_encode($response);
    exit();
}
?>