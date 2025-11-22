<?php

declare(strict_types=1);

/**
 * Script de création d'un utilisateur administrateur
 * 
 * Ce script crée un utilisateur administrateur dans la base de données.
 * À supprimer après utilisation pour des raisons de sécurité.
 */

require_once __DIR__ . '/config.php';

// Données de l'utilisateur administrateur
$username = 'Hyacinthe@dma.mg';
$email = 'Hyacinthe@dma.mg';
$password = 'Hyacinthe12345';
$role = 'admin';

// Vérifier si l'utilisateur existe déjà
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
$check_stmt->bind_param("ss", $email, $username);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $existing_user = $result->fetch_assoc();
    echo "<div style='padding: 20px; background: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; margin: 20px;'>";
    echo "<h2 style='color: #92400e;'>⚠️ Utilisateur déjà existant</h2>";
    echo "<p>Un utilisateur avec l'email ou le nom d'utilisateur <strong>$email</strong> existe déjà (ID: {$existing_user['id']}).</p>";
    echo "<p>Si vous souhaitez réinitialiser le mot de passe, veuillez le faire depuis l'interface d'administration.</p>";
    echo "</div>";
    $check_stmt->close();
    $conn->close();
    exit;
}

// Hasher le mot de passe
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insérer l'utilisateur
$stmt = $conn->prepare("
    INSERT INTO users (username, email, password_hash, role, actif) 
    VALUES (?, ?, ?, ?, 1)
");

$stmt->bind_param("ssss", $username, $email, $password_hash, $role);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;
    
    // Log de création
    $log_stmt = $conn->prepare("INSERT INTO logs_activite (user_id, action, details, adresse_ip) VALUES (?, 'Création compte', 'Création du compte administrateur', ?)");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $log_stmt->bind_param("is", $user_id, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    echo "<div style='padding: 20px; background: #d1fae5; border: 2px solid #10b981; border-radius: 8px; margin: 20px;'>";
    echo "<h2 style='color: #065f46;'>✅ Utilisateur administrateur créé avec succès !</h2>";
    echo "<p><strong>ID :</strong> $user_id</p>";
    echo "<p><strong>Nom d'utilisateur :</strong> $username</p>";
    echo "<p><strong>Email :</strong> $email</p>";
    echo "<p><strong>Rôle :</strong> $role</p>";
    echo "<p><strong>Statut :</strong> Actif</p>";
    echo "<hr style='margin: 15px 0; border: none; border-top: 1px solid #10b981;'>";
    echo "<p style='color: #065f46;'><strong>⚠️ Important :</strong> Supprimez ce fichier (create_admin.php) après utilisation pour des raisons de sécurité.</p>";
    echo "<p><a href='login.php' style='display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Se connecter</a></p>";
    echo "</div>";
} else {
    echo "<div style='padding: 20px; background: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; margin: 20px;'>";
    echo "<h2 style='color: #991b1b;'>❌ Erreur lors de la création</h2>";
    echo "<p>Erreur : " . htmlspecialchars($stmt->error) . "</p>";
    echo "</div>";
}

$stmt->close();
$check_stmt->close();
$conn->close();

?>

