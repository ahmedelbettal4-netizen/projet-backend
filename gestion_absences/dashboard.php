<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';
verifierAuth();

$enseignant = getEnseignantInfo();

$stmt = $pdo->query("SELECT m.*, f.nom as filiere_nom 
                     FROM modules m 
                     JOIN filieres f ON m.filiere_id = f.id 
                     ORDER BY f.nom, m.nom");
$modules = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM filieres");
$filieres = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion des Absences</title>
    <link rel="stylesheet" href="2_assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>📱 Gestion des Absences par Code-barres</h1>
            <div class="user-info">
                <span>👨‍🏫 <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?></span>
                <a href="logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </header>
        
        <nav class="nav-tabs">
            <a href="dashboard.php" class="active">📊 Nouvelle séance</a>
            <a href="historique.php">📜 Historique des absences</a>
        </nav>
        
        <main>
            <div class="card">
                <h2>Nouvelle séance de présence</h2>
                
                <form id="seanceForm" action="seance.php" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="module_id">Module :</label>
                            <select id="module_id" name="module_id" required>
                                <option value="">Sélectionner un module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>">
                                        <?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="filiere_id">Filière :</label>
                            <select id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionner une filière</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>">
                                        <?php echo htmlspecialchars($filiere['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="semestre">Semestre :</label>
                            <select id="semestre" name="semestre" required>
                                <option value="">Sélectionner le semestre</option>
                                <option value="1">Semestre 1</option>
                                <option value="2">Semestre 2</option>
                                <option value="3">Semestre 3</option>
                                <option value="4">Semestre 4</option>
                                <option value="5">Semestre 5</option>
                                <option value="6">Semestre 6</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_seance">Date :</label>
                            <input type="date" id="date_seance" name="date_seance" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="heure_debut">Heure de début :</label>
                            <input type="time" id="heure_debut" name="heure_debut" 
                                   value="<?php echo date('H:i'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="heure_fin">Heure de fin :</label>
                            <input type="time" id="heure_fin" name="heure_fin">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary">Commencer la séance</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>