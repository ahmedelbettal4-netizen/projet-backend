<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';
verifierAuth();

$enseignant_id = $_SESSION['enseignant_id'];

// Récupérer tous les modules pour le filtre
$stmt = $pdo->prepare("SELECT DISTINCT m.id, m.code, m.nom 
                       FROM modules m
                       JOIN seances s ON s.module_id = m.id
                       WHERE s.enseignant_id = ?");
$stmt->execute([$enseignant_id]);
$modules = $stmt->fetchAll();

// Appliquer le filtre si nécessaire
$module_filter = $_GET['module'] ?? '';
$where_condition = "s.enseignant_id = ?";
$params = [$enseignant_id];

if ($module_filter) {
    $where_condition .= " AND s.module_id = ?";
    $params[] = $module_filter;
}

// Récupérer les séances avec leurs statistiques
$stmt = $pdo->prepare("SELECT s.*, 
                              m.nom as module_nom, 
                              m.code as module_code,
                              COUNT(DISTINCT a.id) as total_etudiants,
                              SUM(CASE WHEN a.statut = 'present' THEN 1 ELSE 0 END) as presents,
                              MAX(a.scan_time) as dernier_scan
                       FROM seances s
                       JOIN modules m ON s.module_id = m.id
                       LEFT JOIN absences a ON a.seance_id = s.id
                       WHERE $where_condition
                       GROUP BY s.id
                       ORDER BY s.date_seance DESC, s.heure_debut DESC");
$stmt->execute($params);
$seances = $stmt->fetchAll();

// Statistiques globales
$total_seances = count($seances);
$total_presents = 0;
$total_etudiants_all = 0;

foreach ($seances as $seance) {
    $total_presents += $seance['presents'];
    $total_etudiants_all += $seance['total_etudiants'];
}
$taux_global = $total_etudiants_all > 0 ? round(($total_presents / $total_etudiants_all) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des absences - Gestion des Absences</title>
    <link rel="stylesheet" href="2_assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>📜 Historique des séances</h1>
            <div>
                <a href="dashboard.php" class="btn-secondary">← Nouvelle séance</a>
                <a href="logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </header>
        
        <!-- Statistiques globales -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_seances; ?></div>
                <div class="stat-label">Total séances</div>
            </div>
            <div class="stat-card present">
                <div class="stat-number"><?php echo $total_presents; ?></div>
                <div class="stat-label">Présences totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $taux_global; ?>%</div>
                <div class="stat-label">Taux de présence global</div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="card">
            <h2>🔍 Filtrer les séances</h2>
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="module">Module :</label>
                        <select id="module" name="module">
                            <option value="">Tous les modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo $module['id']; ?>" 
                                        <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary">Filtrer</button>
                        <?php if ($module_filter): ?>
                            <a href="historique.php" class="btn-secondary">Réinitialiser</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Liste des séances -->
        <div class="card">
            <h2>📊 Liste des séances</h2>
            
            <?php if (empty($seances)): ?>
                <div class="alert alert-info" style="background: #e6f7ff; color: #0050b3;">
                    Aucune séance trouvée. Commencez par créer une nouvelle séance !
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Module</th>
                                <th>Horaire</th>
                                <th>Semestre</th>
                                <th>Total</th>
                                <th>Présents</th>
                                <th>Absents</th>
                                <th>Taux</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seances as $seance): ?>
                            <?php 
                            $absents = $seance['total_etudiants'] - $seance['presents'];
                            $taux = $seance['total_etudiants'] > 0 ? round(($seance['presents'] / $seance['total_etudiants']) * 100) : 0;
                            
                            // Couleur du taux
                            $taux_color = '';
                            if ($taux >= 75) $taux_color = '#48bb78';
                            elseif ($taux >= 50) $taux_color = '#ed8936';
                            else $taux_color = '#f56565';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($seance['module_code']); ?><br>
                                    <small><?php echo htmlspecialchars($seance['module_nom']); ?></small>
                                </td>
                                <td>
                                    <?php echo $seance['heure_debut']; ?>
                                    <?php if ($seance['heure_fin']): ?>
                                        - <?php echo $seance['heure_fin']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>S<?php echo $seance['semestre']; ?></td>
                                <td><?php echo $seance['total_etudiants']; ?></td>
                                <td style="color: #48bb78; font-weight: bold;"><?php echo $seance['presents']; ?></td>
                                <td style="color: #f56565; font-weight: bold;"><?php echo $absents; ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $taux; ?>%; background: <?php echo $taux_color; ?>"></div>
                                        <span class="progress-text"><?php echo $taux; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <a href="details_seance.php?id=<?php echo $seance['id']; ?>" class="btn-small">📋 Détails</a>
                                    <a href="export.php?seance_id=<?php echo $seance['id']; ?>" class="btn-small btn-export">📥 Exporter</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Légende et aide -->
        <div class="card" style="background: #f7fafc;">
            <h3>📖 Légende</h3>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #48bb78; border-radius: 3px;"></span> Taux ≥ 75% (Bon)</div>
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #ed8936; border-radius: 3px;"></span> Taux 50-74% (Moyen)</div>
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #f56565; border-radius: 3px;"></span> Taux < 50% (Faible)</div>
            </div>
            <p style="margin-top: 15px; color: #666; font-size: 14px;">
                💡 <strong>Astuce :</strong> Cliquez sur "Détails" pour voir la liste complète des étudiants présents et absents d'une séance.
                Utilisez "Exporter" pour télécharger les données au format CSV.
            </p>
        </div>
    </div>
</body>
</html>