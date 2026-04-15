<?php
echo "<h1>🔍 Diagnostic - Vérification des dossiers</h1>";
echo "<h2>Dossier actuel : " . __DIR__ . "</h2>";

echo "<h3>📁 Dossiers trouvés :</h3>";
$dossiers = glob(__DIR__ . '/*', GLOB_ONLYDIR);
if (empty($dossiers)) {
    echo "<p style='color: red;'>❌ Aucun dossier trouvé !</p>";
} else {
    echo "<ul>";
    foreach ($dossiers as $dossier) {
        $nom = basename($dossier);
        echo "<li>📁 $nom/";
        
        // Vérifier si database.php existe dans config
        if ($nom == 'config' || $nom == '1_config') {
            if (file_exists($dossier . '/database.php')) {
                echo " ✅ database.php trouvé";
            } else {
                echo " ❌ database.php MANQUANT";
            }
        }
        
        // Vérifier si auth.php existe dans includes
        if ($nom == 'includes' || $nom == '3_includes') {
            if (file_exists($dossier . '/auth.php')) {
                echo " ✅ auth.php trouvé";
            } else {
                echo " ❌ auth.php MANQUANT";
            }
        }
        
        echo "</li>";
    }
    echo "</ul>";
}

echo "<h3>📄 Fichiers PHP trouvés :</h3>";
$fichiers = glob(__DIR__ . '/*.php');
echo "<ul>";
foreach ($fichiers as $fichier) {
    echo "<li>📄 " . basename($fichier) . "</li>";
}
echo "</ul>";

echo "<h3>💡 Solutions :</h3>";
echo "<p>Si le dossier 'config' n'existe pas, créez-le et ajoutez database.php</p>";
echo "<p>Si le dossier s'appelle '1_config', modifiez login.php pour utiliser '1_config'</p>";
?>