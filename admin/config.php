<?php
/**
 * ---------------------------------------------------------
 * FICHIER : config.php
 * Projet  : École Mandroso - Gestion Scolaire
 * Auteur  : Innovation (2025)
 * ---------------------------------------------------------
 * Ce fichier établit la connexion MySQL et définit
 * les constantes globales de configuration.
 * ---------------------------------------------------------
 */

# 🔧 Paramètres de connexion à MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // nom d'utilisateur MySQL
define('DB_PASS', '');            // mot de passe MySQL (vide par défaut sous XAMPP)
define('DB_NAME', 'ecole'); // nom de ta base créée

# 🧩 Connexion à la base
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

# ⚠️ Vérification des erreurs de connexion
if ($conn->connect_error) {
    die("
        <div style='font-family:sans-serif;
                    background:#fee2e2;
                    color:#b91c1c;
                    border:2px solid #b91c1c;
                    padding:15px;
                    margin:20px;
                    border-radius:10px;'>
            <h2>Erreur de connexion à la base de données</h2>
            <p><b>Détail :</b> " . htmlspecialchars($conn->connect_error) . "</p>
            <p>Vérifie ton fichier <code>config.php</code> ou ton serveur MySQL (XAMPP)</p>
        </div>
    ");
}

# ✅ Si tout est bon, la connexion est active
$conn->set_charset("utf8mb4");

# Optionnel : masquer les erreurs notices PHP
error_reporting(E_ALL & ~E_NOTICE);

# 🔒 Sécurité basique : forcer HTTPS (si hébergé en ligne)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

# Exemple : pour vérifier que la connexion marche
# echo "Connexion MySQL réussie !";
?>
