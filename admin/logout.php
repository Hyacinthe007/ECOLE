<?php
session_start();

// ✅ Log de déconnexion (si connecté)
if (isset($_SESSION['user_id'])) {
    include('config.php');
    
    // Vérifier que l'utilisateur existe dans la table users avant d'insérer le log
    $user_id = $_SESSION['user_id'];
    $check_user = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $check_user->bind_param("i", $user_id);
    $check_user->execute();
    $result = $check_user->get_result();
    
    if ($result->num_rows > 0) {
        // L'utilisateur existe, on peut insérer le log
        $stmt = $conn->prepare("INSERT INTO logs_activite (user_id, action, details, adresse_ip) VALUES (?, 'Déconnexion', 'Déconnexion réussie', ?)");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt->bind_param("is", $user_id, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
    $check_user->close();
    $conn->close();
}

// ✅ Sauvegarder le nom de l'utilisateur pour le message
$username = $_SESSION['username'] ?? 'Utilisateur';

// ✅ Détruire toutes les variables de session
$_SESSION = array();

// ✅ Détruire le cookie de session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// ✅ Détruire la session
session_destroy();

// ✅ Redirection vers la page de connexion avec message
header("Location: login.php?disconnected=1&user=" . urlencode($username));
exit();
?>
