<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';
verifierAuth();

$seance_id = $_GET['seance_id'] ?? 0;

// Vérifier que la séance appartient à l'enseignant connecté
$stmt = $pdo->prepare("SELECT s.*, m.nom as module_nom, m.code as module_code, f.nom as filiere_nom
                       FROM seances s 
                       JOIN modules m ON s.module_id = m.id
                       JOIN filieres f ON m.filiere_id = f.id
                       WHERE s.id = ? AND s.enseignant_id = ?");
$stmt->execute([$seance_id, $_SESSION['enseignant_id']]);
$seance = $stmt->fetch();

if (!$seance) {
    die("Séance non trouvée ou accès non autorisé");
}

// Récupérer les présences/absences
$stmt = $pdo->prepare("SELECT e.matricule, e.nom, e.prenom, e.email, a.statut, a.scan_time 
                       FROM etudiants e 
                       LEFT JOIN absences a ON a.etudiant_id = e.id AND a.seance_id = ?
                       WHERE e.filiere_id = ? AND e.semestre = ?
                       ORDER BY e.nom, e.prenom");
$stmt->execute([$seance_id, $seance['filiere_id'], $seance['semestre']]);
$etudiants = $stmt->fetchAll();

// Statistiques
$total = count($etudiants);
$presents = 0;
$absents = 0;
foreach ($etudiants as $etudiant) {
    if ($etudiant['statut'] == 'present') {
        $presents++;
    } else {
        $absents++;
    }
}
$taux = $total > 0 ? round(($presents / $total) * 100, 2) : 0;

// Générer le fichier CSV
$filename = 'absences_' . $seance['module_code'] . '_' . date('Y-m-d', strtotime($seance['date_seance'])) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Ajouter l'en-tête avec les informations de la séance
fputcsv($output, ['RAPPORT D\'ABSENCES']);
fputcsv($output, ['']);
fputcsv($output, ['Module', $seance['module_code'] . ' - ' . $seance['module_nom']]);
fputcsv($output, ['Filière', $seance['filiere_nom']]);
fputcsv($output, ['Semestre', 'S' . $seance['semestre']]);
fputcsv($output, ['Date', date('d/m/Y', strtotime($seance['date_seance']))]);
fputcsv($output, ['Heure', $seance['heure_debut'] . ($seance['heure_fin'] ? ' - ' . $seance['heure_fin'] : '')]);
fputcsv($output, ['']);
fputcsv($output, ['STATISTIQUES']);
fputcsv($output, ['Total étudiants', $total]);
fputcsv($output, ['Présents', $presents]);
fputcsv($output, ['Absents', $absents]);
fputcsv($output, ['Taux de présence', $taux . '%']);
fputcsv($output, ['']);
fputcsv($output, ['DÉTAIL DES PRÉSENCES']);
fputcsv($output, ['']);

// Ajouter les en-têtes du tableau
fputcsv($output, ['Matricule', 'Nom', 'Prénom', 'Email', 'Statut', 'Heure de scan']);

// Ajouter les données
foreach ($etudiants as $etudiant) {
    fputcsv($output, [
        $etudiant['matricule'],
        $etudiant['nom'],
        $etudiant['prenom'],
        $etudiant['email'],
        $etudiant['statut'] === 'present' ? 'Présent' : 'Absent',
        $etudiant['scan_time'] && $etudiant['statut'] === 'present' ? date('d/m/Y H:i:s', strtotime($etudiant['scan_time'])) : ''
    ]);
}

// Ajouter une ligne de total
fputcsv($output, ['']);
fputcsv($output, ['TOTAL PRÉSENTS', $presents]);
fputcsv($output, ['TOTAL ABSENTS', $absents]);

fclose($output);
exit();
?>