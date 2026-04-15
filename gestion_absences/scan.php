<?php
require_once '1_config/database.php';
require_once '3_includes/auth.php';
verifierAuth();

$seance_id = $_GET['seance_id'] ?? 0;

if (!$seance_id) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT s.*, m.nom as module_nom, m.code as module_code, m.filiere_id
                       FROM seances s 
                       JOIN modules m ON s.module_id = m.id 
                       WHERE s.id = ?");
$stmt->execute([$seance_id]);
$seance = $stmt->fetch();

if (!$seance) {
    header('Location: dashboard.php');
    exit();
}

// Traitement du scan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ajout d'un nouvel étudiant
    if (isset($_POST['action']) && $_POST['action'] === 'add_student') {
        $code_barre = trim($_POST['code_barre']);
        $matricule = trim($_POST['matricule']);
        $nom = trim($_POST['nom']);
        $prenom = trim($_POST['prenom']);
        $email = trim($_POST['email']);
        $filiere_id = $seance['filiere_id'];
        $semestre = $seance['semestre'];
        
        // Vérifier si le code-barre existe déjà
        $stmt = $pdo->prepare("SELECT id FROM etudiants WHERE code_barre = ?");
        $stmt->execute([$code_barre]);
        if ($stmt->fetch()) {
            $_SESSION['flash_message'] = "❌ Ce code-barre existe déjà !";
            $_SESSION['flash_type'] = 'error';
        } else {
            // Ajouter l'étudiant
            $sql = "INSERT INTO etudiants (code_barre, matricule, nom, prenom, email, filiere_id, semestre) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code_barre, $matricule, $nom, $prenom, $email, $filiere_id, $semestre]);
            
            $newId = $pdo->lastInsertId();
            
            // Ajouter une absence pour la séance actuelle
            $stmt = $pdo->prepare("INSERT INTO absences (seance_id, etudiant_id, statut) VALUES (?, ?, 'absent')");
            $stmt->execute([$seance_id, $newId]);
            
            $_SESSION['flash_message'] = "✅ Étudiant $prenom $nom ajouté avec succès !";
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: scan.php?seance_id=$seance_id");
        exit();
    }
    
    // Scan de code-barres
    elseif (isset($_POST['code_barre']) && !empty($_POST['code_barre'])) {
        $code_barre = trim($_POST['code_barre']);
        
        // Chercher l'étudiant PAR CODE-BARRES
        $stmt = $pdo->prepare("SELECT id, nom, prenom, matricule, filiere_id, semestre FROM etudiants WHERE code_barre = ?");
        $stmt->execute([$code_barre]);
        $etudiant = $stmt->fetch();
        
        if ($etudiant) {
            // Vérifier si l'étudiant est dans la bonne filière et semestre
            if ($etudiant['filiere_id'] != $seance['filiere_id'] || $etudiant['semestre'] != $seance['semestre']) {
                $_SESSION['flash_message'] = "⚠️ " . $etudiant['prenom'] . " " . $etudiant['nom'] . " n'est pas inscrit dans cette filière/semestre !";
                $_SESSION['flash_type'] = 'warning';
            } else {
                // Vérifier si une entrée existe dans la table absences
                $stmt = $pdo->prepare("SELECT id, statut FROM absences WHERE seance_id = ? AND etudiant_id = ?");
                $stmt->execute([$seance_id, $etudiant['id']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    if ($existing['statut'] == 'present') {
                        $_SESSION['flash_message'] = "⚠️ " . $etudiant['prenom'] . " " . $etudiant['nom'] . " est déjà marqué présent !";
                        $_SESSION['flash_type'] = 'warning';
                    } else {
                        // Mettre à jour le statut existant
                        $stmt = $pdo->prepare("UPDATE absences SET statut = 'present', scan_time = NOW() 
                                               WHERE seance_id = ? AND etudiant_id = ?");
                        $stmt->execute([$seance_id, $etudiant['id']]);
                        
                        $_SESSION['flash_message'] = "✓ Présence enregistrée pour " . $etudiant['prenom'] . " " . $etudiant['nom'];
                        $_SESSION['flash_type'] = 'success';
                    }
                } else {
                    // Créer une nouvelle entrée dans absences
                    $stmt = $pdo->prepare("INSERT INTO absences (seance_id, etudiant_id, statut, scan_time) VALUES (?, ?, 'present', NOW())");
                    $stmt->execute([$seance_id, $etudiant['id']]);
                    
                    $_SESSION['flash_message'] = "✓ Présence enregistrée pour " . $etudiant['prenom'] . " " . $etudiant['nom'];
                    $_SESSION['flash_type'] = 'success';
                }
            }
        } else {
            $_SESSION['flash_message'] = "✗ Code-barres '$code_barre' non reconnu ! Cliquez sur 'Ajouter' pour l'ajouter.";
            $_SESSION['flash_type'] = 'error';
        }
        header("Location: scan.php?seance_id=$seance_id");
        exit();
    }
}

// Récupérer les messages flash
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

// Récupérer TOUS les étudiants de la filière et semestre
$filiere_id = $seance['filiere_id'];
$semestre = $seance['semestre'];

$stmt = $pdo->prepare("SELECT e.*, COALESCE(a.statut, 'absent') as statut, a.scan_time 
                       FROM etudiants e 
                       LEFT JOIN absences a ON a.etudiant_id = e.id AND a.seance_id = ?
                       WHERE e.filiere_id = ? AND e.semestre = ?
                       ORDER BY e.nom, e.prenom");
$stmt->execute([$seance_id, $filiere_id, $semestre]);
$etudiants = $stmt->fetchAll();

$total_etudiants = count($etudiants);
$presents = 0;
foreach ($etudiants as $e) {
    if ($e['statut'] == 'present') $presents++;
}
$absents = $total_etudiants - $presents;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Scan des présences - Gestion des Absences</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 { color: #667eea; font-size: 1.5em; }
        .btn-secondary {
            background: #48bb78;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-block;
            margin: 5px;
        }
        .btn-logout {
            background: #f56565;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-block;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .seance-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #667eea; }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .alert-error { background: #fed7d7; color: #742a2a; border: 1px solid #fc8181; }
        .alert-warning { background: #feebc8; color: #7b341e; border: 1px solid #fbd38d; }
        .flash-message { animation: fadeout 3s ease-in-out forwards; }
        @keyframes fadeout { 0% { opacity: 1; } 70% { opacity: 1; } 100% { opacity: 0; display: none; } }
        
        .scanner-section {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .scanner-section { grid-template-columns: 1fr; }
            .header { flex-direction: column; text-align: center; }
        }
        
        #reader { width: 100%; max-width: 500px; margin: 0 auto; }
        #reader video { width: 100%; border-radius: 10px; background: #000; }
        
        .btn-camera {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        .btn-camera:hover { background: #5a67d8; }
        .btn-danger { background: #f56565; }
        .btn-danger:hover { background: #e53e3e; }
        .btn-primary {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-add {
            background: #48bb78;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
        }
        .manual-input {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .search-box { margin-bottom: 15px; }
        .search-box input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
        }
        .student-table th, .student-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .student-table th {
            background: #f7fafc;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        .badge-present {
            background: #c6f6d5;
            color: #22543d;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        .badge-absent {
            background: #fed7d7;
            color: #742a2a;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close {
            cursor: pointer;
            font-size: 24px;
            color: #666;
        }
        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        .scan-status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .small-text {
            font-size: 12px;
            color: #718096;
            margin-top: 10px;
            text-align: center;
        }
        .filter-info {
            background: #e6f7ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>📷 Scan des présences</h1>
            <div>
                <a href="dashboard.php" class="btn-secondary">← Nouvelle séance</a>
                <a href="historique.php" class="btn-secondary">📜 Historique</a>
                <a href="logout.php" class="btn-logout">Déconnexion</a>
            </div>
        </header>
        
        <div class="seance-info card">
            <h3><?php echo htmlspecialchars($seance['module_code'] . ' - ' . $seance['module_nom']); ?></h3>
            <p>📅 Date : <?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?> | ⏰ Heure : <?php echo $seance['heure_debut']; ?></p>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_etudiants; ?></div>
                <div class="stat-label">Total étudiants</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="presentCount"><?php echo $presents; ?></div>
                <div class="stat-label">✅ Présents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="absentCount"><?php echo $absents; ?></div>
                <div class="stat-label">❌ Absents</div>
            </div>
        </div>
        
        <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo $flash_type; ?> flash-message">
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="scanner-section">
            <!-- Scanner Section -->
            <div class="card">
                <h2>🎯 Scanner un code-barres</h2>
                
                <div id="reader-container">
                    <div id="reader" style="width:100%;"></div>
                </div>
                
                <div class="camera-controls">
                    <button id="startCameraBtn" class="btn-camera">📷 Démarrer la caméra</button>
                    <button id="stopCameraBtn" class="btn-camera btn-danger" style="display:none;">⏹️ Arrêter la caméra</button>
                </div>
                
                <div id="scanStatus" class="scan-status"></div>
                <p class="small-text">💡 Placez le code-barres devant la caméra. Le scan est automatique !</p>
                
                <div class="manual-input">
                    <h4>⌨️ Saisie manuelle</h4>
                    <form method="POST" action="" id="manualForm">
                        <div class="form-group">
                            <input type="text" id="manualCode" name="code_barre" 
                                   placeholder="Saisissez le code-barres manuellement" 
                                   style="font-size: 18px; font-family: monospace;">
                        </div>
                        <button type="submit" class="btn-primary" style="width:100%;">✅ Valider le scan</button>
                    </form>
                </div>
                
                <button onclick="openModal()" class="btn-add">➕ Ajouter un nouvel étudiant</button>
            </div>
            
            <!-- Student List Section -->
            <div class="card">
                <h2>📋 Liste des étudiants</h2>
                <div class="filter-info">
                    📌 Filière: <?php echo $filiere_id; ?> | Semestre: <?php echo $semestre; ?>
                </div>
                <div class="search-box">
                    <input type="text" id="search" placeholder="🔍 Rechercher un étudiant...">
                </div>
                <div class="table-container">
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>Code-barres</th>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Statut</th>
                                <th>Heure</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            <?php if (empty($etudiants)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">
                                        ⚠️ Aucun étudiant trouvé pour cette filière et semestre.<br>
                                        <small>Vérifiez que les étudiants ont filiere_id=<?php echo $filiere_id; ?> et semestre=<?php echo $semestre; ?></small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($etudiants as $etudiant): ?>
                                <tr data-code="<?php echo htmlspecialchars($etudiant['code_barre']); ?>">
                                    <td><?php echo htmlspecialchars($etudiant['code_barre']); ?></td>
                                    <td><?php echo htmlspecialchars($etudiant['matricule']); ?></td>
                                    <td><?php echo htmlspecialchars($etudiant['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($etudiant['prenom']); ?></td>
                                    <td class="status-cell">
                                        <span class="badge-<?php echo $etudiant['statut']; ?>">
                                            <?php echo $etudiant['statut'] === 'present' ? '✓ Présent' : '✗ Absent'; ?>
                                        </span>
                                    </td>
                                    <td class="time-cell">
                                        <?php echo $etudiant['scan_time'] && $etudiant['statut'] === 'present' 
                                                ? date('H:i:s', strtotime($etudiant['scan_time'])) : '-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajout Étudiant -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Ajouter un nouvel étudiant</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="" id="addStudentForm">
                <input type="hidden" name="action" value="add_student">
                <div class="form-group">
                    <label>Code-barres * :</label>
                    <input type="text" name="code_barre" id="new_code_barre" required placeholder="Ex: 23009155">
                </div>
                <div class="form-group">
                    <label>Matricule * :</label>
                    <input type="text" name="matricule" id="new_matricule" required placeholder="Ex: ETU008">
                </div>
                <div class="form-group">
                    <label>Nom * :</label>
                    <input type="text" name="nom" id="new_nom" required placeholder="Ex: Dupont">
                </div>
                <div class="form-group">
                    <label>Prénom * :</label>
                    <input type="text" name="prenom" id="new_prenom" required placeholder="Ex: Jean">
                </div>
                <div class="form-group">
                    <label>Email :</label>
                    <input type="email" name="email" id="new_email" placeholder="Ex: jean.dupont@email.com">
                </div>
                <div class="form-group">
                    <label>Filière :</label>
                    <input type="text" value="<?php echo $filiere_id; ?>" disabled style="background:#f0f0f0;">
                    <small class="small-text">L'étudiant sera ajouté à la filière actuelle</small>
                </div>
                <div class="form-group">
                    <label>Semestre :</label>
                    <input type="text" value="<?php echo $semestre; ?>" disabled style="background:#f0f0f0;">
                    <small class="small-text">L'étudiant sera ajouté au semestre actuel</small>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top:15px;">Ajouter l'étudiant</button>
            </form>
        </div>
    </div>
    
    <script>
        let html5QrCode = null;
        let isScanning = false;
        
        const startCameraBtn = document.getElementById('startCameraBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');
        const scanStatus = document.getElementById('scanStatus');
        
        // Démarrer la caméra
        startCameraBtn.addEventListener('click', function() {
            startCameraBtn.style.display = 'none';
            stopCameraBtn.style.display = 'inline-block';
            scanStatus.innerHTML = '🟢 Demande d\'accès à la caméra...';
            scanStatus.style.background = '#e6f7ff';
            scanStatus.style.color = '#0050b3';
            
            html5QrCode = new Html5Qrcode("reader");
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    if (isScanning) return;
                    isScanning = true;
                    
                    scanStatus.innerHTML = '📷 Code détecté: ' + decodedText + ' - Enregistrement...';
                    scanStatus.style.background = '#c6f6d5';
                    scanStatus.style.color = '#22543d';
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'code_barre=' + encodeURIComponent(decodedText)
                    })
                    .then(response => response.text())
                    .then(() => {
                        window.location.reload();
                    });
                },
                (error) => {
                    console.log("Scan error:", error);
                }
            ).catch((err) => {
                console.error("Camera error:", err);
                scanStatus.innerHTML = '❌ Erreur: Impossible d\'accéder à la caméra. Vérifiez les permissions.';
                scanStatus.style.background = '#fed7d7';
                scanStatus.style.color = '#742a2a';
                startCameraBtn.style.display = 'inline-block';
                stopCameraBtn.style.display = 'none';
            });
        });
        
        // Arrêter la caméra
        stopCameraBtn.addEventListener('click', function() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    startCameraBtn.style.display = 'inline-block';
                    stopCameraBtn.style.display = 'none';
                    scanStatus.innerHTML = '⏹️ Caméra arrêtée';
                    scanStatus.style.background = '#e2e8f0';
                    scanStatus.style.color = '#4a5568';
                    setTimeout(() => {
                        if (scanStatus.innerHTML === '⏹️ Caméra arrêtée') {
                            scanStatus.innerHTML = '';
                        }
                    }, 2000);
                    isScanning = false;
                }).catch((err) => {
                    console.error("Stop error:", err);
                });
            }
        });
        
        // Formulaire manuel
        document.getElementById('manualForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const code = document.getElementById('manualCode').value.trim();
            if (code !== '') {
                scanStatus.innerHTML = '📷 Validation du code: ' + code + '...';
                scanStatus.style.background = '#e6f7ff';
                scanStatus.style.color = '#0050b3';
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'code_barre=' + encodeURIComponent(code)
                })
                .then(response => response.text())
                .then(() => {
                    window.location.reload();
                });
            }
        });
        
        // Recherche
        document.getElementById('search').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#studentTableBody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Modal functions
        function openModal() {
            document.getElementById('addStudentModal').style.display = 'flex';
            document.getElementById('new_code_barre').focus();
        }
        
        function closeModal() {
            document.getElementById('addStudentModal').style.display = 'none';
        }
        
        document.getElementById('addStudentForm').addEventListener('submit', function(e) {
            const code = document.getElementById('new_code_barre').value;
            const matricule = document.getElementById('new_matricule').value;
            const nom = document.getElementById('new_nom').value;
            const prenom = document.getElementById('new_prenom').value;
            
            if (!code || !matricule || !nom || !prenom) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires (*)');
            }
        });
        
        window.onclick = function(event) {
            const modal = document.getElementById('addStudentModal');
            if (event.target === modal) closeModal();
        }
        
        setTimeout(() => {
            const flash = document.querySelector('.flash-message');
            if (flash) flash.style.display = 'none';
        }, 3000);
    </script>
</body>
</html>