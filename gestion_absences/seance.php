<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';
verifierAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$module_id = $_POST['module_id'];
$filiere_id = $_POST['filiere_id'];
$semestre = $_POST['semestre'];
$date_seance = $_POST['date_seance'];
$heure_debut = $_POST['heure_debut'];
$heure_fin = $_POST['heure_fin'] ?: null;
$enseignant_id = $_SESSION['enseignant_id'];

$stmt = $pdo->prepare("INSERT INTO seances (module_id, enseignant_id, date_seance, heure_debut, heure_fin, semestre) 
                       VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$module_id, $enseignant_id, $date_seance, $heure_debut, $heure_fin, $semestre]);
$seance_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("SELECT * FROM etudiants WHERE filiere_id = ? AND semestre = ? ORDER BY nom, prenom");
$stmt->execute([$filiere_id, $semestre]);
$etudiants = $stmt->fetchAll();

$stmt = $pdo->prepare("INSERT INTO absences (seance_id, etudiant_id, statut) VALUES (?, ?, 'absent')");
foreach ($etudiants as $etudiant) {
    $stmt->execute([$seance_id, $etudiant['id']]);
}

$_SESSION['seance_actuelle'] = [
    'id' => $seance_id,
    'module_id' => $module_id,
    'filiere_id' => $filiere_id,
    'semestre' => $semestre
];

header("Location: scan.php?seance_id=$seance_id");
exit();
?>