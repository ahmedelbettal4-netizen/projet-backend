<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';
verifierAuth();

$seance_id = $_GET['id'] ?? 0;

// Vérifier que la séance appartient à l'enseignant connecté
$stmt = $pdo->prepare("SELECT s.*, m.nom as module_nom, m.code as module_code, m.filiere_id
                       FROM seances s 
                       JOIN modules m ON s.module_id = m.id 
                       WHERE s.id = ? AND s.enseignant_id = ?");
$stmt->execute([$seance_id, $_SESSION['enseignant_id']]);
$seance = $stmt->fetch();

if (!$seance) {
    header('Location: historique.php');
    exit();
}

// Récupérer la filiere_id depuis le module
$filiere_id = $seance['filiere_id'];

// Récupérer tous les étudiants avec leur statut
$stmt = $pdo->prepare("SELECT e.*, a.statut, a.scan_time 
                       FROM etudiants e 
                       LEFT JOIN absences a ON a.etudiant_id = e.id AND a.seance_id = ?
                       WHERE e.filiere_id = ? AND e.semestre = ?
                       ORDER BY a.statut DESC, e.nom, e.prenom");
$stmt->execute([$seance_id, $filiere_id, $seance['semestre']]);
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

// Récupérer les étudiants absents
$stmt_absents = $pdo->prepare("SELECT e.* 
                               FROM etudiants e 
                               LEFT JOIN absences a ON a.etudiant_id = e.id AND a.seance_id = ?
                               WHERE e.filiere_id = ? AND e.semestre = ? AND (a.statut = 'absent' OR a.statut IS NULL)
                               ORDER BY e.nom, e.prenom");
$stmt_absents->execute([$seance_id, $filiere_id, $seance['semestre']]);
$liste_absents = $stmt_absents->fetchAll();

// Récupérer les étudiants présents
$stmt_presents = $pdo->prepare("SELECT e.*, a.scan_time 
                               FROM etudiants e 
                               JOIN absences a ON a.etudiant_id = e.id AND a.seance_id = ?
                               WHERE e.filiere_id = ? AND e.semestre = ? AND a.statut = 'present'
                               ORDER BY a.scan_time DESC");
$stmt_presents->execute([$seance_id, $filiere_id, $seance['semestre']]);
$liste_presents = $stmt_presents->fetchAll();

// Récupérer le nom de la filière
$stmt = $pdo->prepare("SELECT nom FROM filieres WHERE id = ?");
$stmt->execute([$filiere_id]);
$filiere_nom = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la séance - Gestion des Absences</title>
    <link rel="stylesheet" href="2_assets/css/style.css">
    <style>
        @media print {
            .header, .btn-primary, .btn-secondary, .btn-logout, .stats-container .btn, .actions, .no-print {
                display: none;
            }
            .card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            body {
                background: white;
            }
            .container {
                padding: 0;
                margin: 0;
            }
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #f7fafc;
            padding: 10px;
            border-radius: 5px;
        }
        .info-item strong {
            color: #667eea;
        }
        .present-count {
            color: #48bb78;
            font-weight: bold;
        }
        .absent-count {
            color: #f56565;
            font-weight: bold;
        }
        .progress-bar-container {
            height: 30px;
            background: #e2e8f0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transition: width 0.3s;
        }
        .btn-small {
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            margin: 0 2px;
            display: inline-block;
        }
        .btn-export {
            background: #48bb78;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
        }
        .badge-present {
            background: #c6f6d5;
            color: #22543d;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .badge-absent {
            background: #fed7d7;
            color: #742a2a;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>📋 Détails de la séance</h1>
            <div>
                <a href="historique.php" class="btn-secondary">← Retour à l'historique</a>
                <a href="dashboard.php" class="btn-secondary">+ Nouvelle séance</a>
                <a href="logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </header>
        
        <!-- Informations de la séance -->
        <div class="card">
            <h2>📌 Informations générales</h2>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Module :</strong><br>
                    <?php echo htmlspecialchars($seance['module_code'] . ' - ' . $seance['module_nom']); ?>
                </div>
                <div class="info-item">
                    <strong>Filière :</strong><br>
                    <?php echo htmlspecialchars($filiere_nom ?: 'Non définie'); ?>
                </div>
                <div class="info-item">
                    <strong>Semestre :</strong><br>
                    S<?php echo $seance['semestre']; ?>
                </div>
                <div class="info-item">
                    <strong>Date :</strong><br>
                    <?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?>
                </div>
                <div class="info-item">
                    <strong>Horaire :</strong><br>
                    <?php echo $seance['heure_debut']; ?>
                    <?php if ($seance['heure_fin']): ?>
                        - <?php echo $seance['heure_fin']; ?>
                    <?php endif; ?>
                </div>
                <div class="info-item">
                    <strong>Enseignant :</strong><br>
                    <?php echo htmlspecialchars($_SESSION['enseignant_nom']); ?>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total étudiants</div>
            </div>
            <div class="stat-card present">
                <div class="stat-number present-count"><?php echo $presents; ?></div>
                <div class="stat-label">✅ Présents</div>
            </div>
            <div class="stat-card absent">
                <div class="stat-number absent-count"><?php echo $absents; ?></div>
                <div class="stat-label">❌ Absents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $taux; ?>%</div>
                <div class="stat-label">📊 Taux de présence</div>
            </div>
        </div>
        
        <!-- Barre de progression -->
        <div class="card">
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo $taux; ?>%;">
                    <?php if ($taux > 15): ?>
                        <?php echo $taux; ?>% Présents
                    <?php endif; ?>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>✅ Présents: <?php echo $presents; ?> (<?php echo $taux; ?>%)</span>
                <span>❌ Absents: <?php echo $absents; ?> (<?php echo 100 - $taux; ?>%)</span>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="action-buttons no-print">
            <a href="export.php?seance_id=<?php echo $seance_id; ?>" class="btn-primary">📥 Exporter en CSV</a>
            <button onclick="window.print()" class="btn-secondary">🖨️ Imprimer</button>
            <a href="scan.php?seance_id=<?php echo $seance_id; ?>" class="btn-primary">📷 Scanner des présences</a>
        </div>
        
        <!-- Liste des présents -->
        <div class="card">
            <h2>✅ Étudiants présents (<?php echo $presents; ?>)</h2>
            <?php if ($presents > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code-barres</th>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Heure de scan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste_presents as $index => $etudiant): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($etudiant['code_barre']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['matricule']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['nom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['email']); ?></td>
                                <td><?php echo date('H:i:s', strtotime($etudiant['scan_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #666;">Aucun étudiant présent pour cette séance.</p>
            <?php endif; ?>
        </div>
        
        <!-- Liste des absents -->
        <div class="card">
            <h2>❌ Étudiants absents (<?php echo $absents; ?>)</h2>
            <?php if ($absents > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code-barres</th>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste_absents as $index => $etudiant): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($etudiant['code_barre']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['matricule']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['nom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['email']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Actions pour les absents -->
                <div style="margin-top: 20px; padding: 15px; background: #fff5f5; border-radius: 5px;">
                    <strong>📧 Actions suggérées :</strong><br>
                    <p style="margin-top: 10px; color: #666;">
                        Vous pouvez contacter les étudiants absents pour les informer du cours manqué.
                    </p>
                </div>
            <?php else: ?>
                <p style="color: #48bb78;">🎉 Félicitations ! Tous les étudiants sont présents !</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>