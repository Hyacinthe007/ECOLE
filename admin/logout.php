<?php
session_start();

// ✅ Log de déconnexion (si connecté)
if (isset($_SESSION['user_id'])) {
    include('config.php');
    
    $stmt = $conn->prepare("INSERT INTO logs_activite (user_id, action, details, adresse_ip) VALUES (?, 'Déconnexion', 'Déconnexion réussie', ?)");
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("is", $_SESSION['user_id'], $ip_address);
    $stmt->execute();
    $stmt->close();
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