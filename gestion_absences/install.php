<?php
/**
 * Script d'installation automatique
 * À SUPPRIMER APRÈS INSTALLATION POUR DES RAISONS DE SÉCURITÉ
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Installation - Gestion des Absences</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f7fafc;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f7fafc;
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .success {
            background: #c6f6d5;
            border-left-color: #48bb78;
            color: #22543d;
        }
        .error {
            background: #fed7d7;
            border-left-color: #f56565;
            color: #742a2a;
        }
        .warning {
            background: #feebc8;
            border-left-color: #ed8936;
            color: #7b341e;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        button:hover {
            background: #5a67d8;
        }
        code {
            background: #edf2f7;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📚 Installation de l'application Gestion des Absences</h1>";

// Vérifier si l'installation est déjà faite
if (file_exists('installed.lock')) {
    echo "<div class='step warning'>
            <strong>⚠️ Attention :</strong> L'application semble déjà installée.<br>
            Si vous souhaitez réinstaller, supprimez le fichier <code>installed.lock</code> et relancez ce script.
          </div>";
    echo "<hr>";
    echo "<p>Vous pouvez maintenant vous connecter : <a href='login.php'>Accéder à l'application</a></p>";
    echo "</div></body></html>";
    exit();
}

$step = $_GET['step'] ?? 1;
$error = false;

if ($step == 1) {
    // Test de connexion à MySQL
    echo "<div class='step'>
            <h3>📌 Étape 1 : Vérification de la configuration</h3>";
    
    // Vérifier l'existence du fichier de configuration
    if (!file_exists('1_config/database.php')) {
        echo "<div class='error'>
                ❌ Fichier de configuration non trouvé : <code>1_config/database.php</code>
              </div>";
        $error = true;
    } else {
        echo "<div class='success'>✅ Fichier de configuration trouvé</div>";
    }
    
    // Vérifier les dossiers
    $folders = ['1_config', '2_assets', '3_includes', '4_sql'];
    foreach ($folders as $folder) {
        if (is_dir($folder)) {
            echo "<div class='success'>✅ Dossier <code>$folder</code> trouvé</div>";
        } else {
            echo "<div class='error'>❌ Dossier <code>$folder</code> manquant</div>";
            $error = true;
        }
    }
    
    if (!$error) {
        echo "<div class='success'>✅ Tous les fichiers sont présents</div>";
        echo "<br><a href='?step=2'><button>Continuer →</button></a>";
    } else {
        echo "<div class='error'>Veuillez corriger les erreurs avant de continuer.</div>";
    }
    
    echo "</div>";
    
} elseif ($step == 2) {
    // Test de connexion à la base de données
    echo "<div class='step'>
            <h3>📌 Étape 2 : Connexion à la base de données</h3>";
    
    require_once '1_config/database.php';
    
    try {
        $test = $pdo->query("SELECT 1");
        echo "<div class='success'>✅ Connexion à MySQL réussie !</div>";
        echo "<div class='success'>✅ Base de données <code>$dbname</code> sélectionnée</div>";
        echo "<br><a href='?step=3'><button>Continuer →</button></a>";
    } catch (PDOException $e) {
        echo "<div class='error'>
                ❌ Erreur de connexion : " . $e->getMessage() . "<br>
                Vérifiez vos identifiants dans <code>1_config/database.php</code>
              </div>";
        echo "<br><a href='?step=1'><button>← Retour</button></a>";
    }
    
    echo "</div>";
    
} elseif ($step == 3) {
    // Création des tables
    echo "<div class='step'>
            <h3>📌 Étape 3 : Création des tables</h3>";
    
    require_once '1_config/database.php';
    
    // Lire le fichier SQL
    $sqlFile = '4_sql/database.sql';
    
    if (!file_exists($sqlFile)) {
        echo "<div class='error'>❌ Fichier SQL non trouvé : <code>$sqlFile</code></div>";
        echo "<br><a href='?step=2'><button>← Retour</button></a>";
    } else {
        $sql = file_get_contents($sqlFile);
        
        // Séparer les requêtes
        $queries = explode(';', $sql);
        
        $success = true;
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                } catch (PDOException $e) {
                    // Ignorer les erreurs "table already exists"
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "<div class='error'>❌ Erreur : " . $e->getMessage() . "<br>Requête : " . htmlspecialchars($query) . "</div>";
                        $success = false;
                    }
                }
            }
        }
        
        if ($success) {
            echo "<div class='success'>✅ Tables créées avec succès !</div>";
            
            // Vérifier si les données existent déjà
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM enseignants");
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                echo "<div class='warning'>
                        ⚠️ Aucun enseignant trouvé. Veuillez créer un compte administrateur.
                      </div>";
                echo "<br><a href='?step=4'><button>Créer un compte admin →</button></a>";
            } else {
                echo "<div class='success'>✅ Données de test déjà présentes</div>";
                echo "<br><a href='?step=5'><button>Finaliser l'installation →</button></a>";
            }
        } else {
            echo "<br><a href='?step=2'><button>← Retour</button></a>";
        }
    }
    
    echo "</div>";
    
} elseif ($step == 4) {
    // Création du compte admin
    echo "<div class='step'>
            <h3>📌 Étape 4 : Création du compte administrateur</h3>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once '1_config/database.php';
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        $nom = $_POST['nom'] ?? '';
        $prenom = $_POST['prenom'] ?? '';
        
        $errors = [];
        
        if (empty($email)) $errors[] = "L'email est requis";
        if (empty($password)) $errors[] = "Le mot de passe est requis";
        if ($password !== $confirm) $errors[] = "Les mots de passe ne correspondent pas";
        if (strlen($password) < 6) $errors[] = "Le mot de passe doit faire au moins 6 caractères";
        if (empty($nom)) $errors[] = "Le nom est requis";
        if (empty($prenom)) $errors[] = "Le prénom est requis";
        
        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO enseignants (email, password, nom, prenom) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $hashed, $nom, $prenom]);
                echo "<div class='success'>✅ Compte administrateur créé avec succès !</div>";
                echo "<div class='success'>📧 Email : $email</div>";
                echo "<div class='success'>🔑 Mot de passe : [Masqué]</div>";
                echo "<br><a href='?step=5'><button>Finaliser l'installation →</button></a>";
            } catch (PDOException $e) {
                echo "<div class='error'>❌ Erreur : " . $e->getMessage() . "</div>";
                echo "<br><a href='?step=4'><button>← Réessayer</button></a>";
            }
        } else {
            echo "<div class='error'>❌ " . implode('<br>❌ ', $errors) . "</div>";
            echo "<br><a href='?step=4'><button>← Réessayer</button></a>";
        }
    } else {
        // Afficher le formulaire
        echo "<form method='POST'>
                <div class='form-group'>
                    <label>Email :</label>
                    <input type='email' name='email' required style='width:100%; padding:8px; margin:5px 0;'>
                </div>
                <div class='form-group'>
                    <label>Nom :</label>
                    <input type='text' name='nom' required style='width:100%; padding:8px; margin:5px 0;'>
                </div>
                <div class='form-group'>
                    <label>Prénom :</label>
                    <input type='text' name='prenom' required style='width:100%; padding:8px; margin:5px 0;'>
                </div>
                <div class='form-group'>
                    <label>Mot de passe :</label>
                    <input type='password' name='password' required style='width:100%; padding:8px; margin:5px 0;'>
                </div>
                <div class='form-group'>
                    <label>Confirmer le mot de passe :</label>
                    <input type='password' name='confirm' required style='width:100%; padding:8px; margin:5px 0;'>
                </div>
                <button type='submit'>Créer le compte</button>
              </form>";
        echo "<br><a href='?step=3'><button>← Retour</button></a>";
    }
    
    echo "</div>";
    
} elseif ($step == 5) {
    // Finalisation
    echo "<div class='step'>
            <h3>📌 Étape 5 : Finalisation de l'installation</h3>";
    
    // Créer le fichier de verrouillage
    file_put_contents('installed.lock', date('Y-m-d H:i:s') . "\nInstallation complétée");
    
    echo "<div class='success'>✅ Installation terminée avec succès !</div>";
    echo "<div class='success'>🔒 Fichier de verrouillage créé</div>";
    echo "<hr>";
    echo "<h3>🎉 Félicitations ! Votre application est prête à être utilisée.</h3>";
    echo "<p>Vous pouvez maintenant :</p>
          <ul>
            <li><a href='login.php'>🔐 Vous connecter à l'application</a></li>
            <li><a href='dashboard.php'>📊 Accéder au tableau de bord</a></li>
          </ul>";
    echo "<div class='warning'>
            <strong>⚠️ IMPORTANT :</strong> Pour des raisons de sécurité, veuillez <strong>SUPPRIMER</strong> le fichier <code>install.php</code> après installation !
          </div>";
    
    echo "</div>";
}

echo "</div></body></html>";
?>