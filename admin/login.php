<?php

declare(strict_types=1);

session_start();

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/config.php';

$error = "";

// Vérifie si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validation basique
    if (empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        // Recherche de l'utilisateur
        $stmt = $conn->prepare(
            "SELECT id, username, email, password_hash, role, actif 
             FROM users 
             WHERE email = ? 
             LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Vérifie si le compte est actif
            if ($user['actif'] != 1) {
                $error = "Votre compte est désactivé. Contactez l'administrateur.";
            } elseif (password_verify($password, $user['password_hash'])) {
                // Régénère l'ID de session pour la sécurité
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                // Log de connexion
                $logStmt = $conn->prepare(
                    "INSERT INTO logs_activite (user_id, action, details, adresse_ip) 
                     VALUES (?, 'Connexion', 'Connexion réussie', ?)"
                );
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $logStmt->bind_param("is", $user['id'], $ipAddress);
                $logStmt->execute();

                // Redirection selon le rôle
                $redirectMap = [
                    'admin' => 'pages/dashboard.php',
                    'enseignant' => 'enseignant/dashboard.php',
                    'parent' => 'parent/dashboard.php',
                    'eleve' => 'eleve/dashboard.php',
                ];
                
                $redirect = $redirectMap[$user['role']] ?? 'index.php';
                header("Location: " . $redirect);
                exit();
            } else {
                $error = "Mot de passe incorrect.";
            }
        } else {
            $error = "Aucun compte trouvé avec cet email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png"  href="./assets/favicon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - École Mandroso</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .login-container {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-blue-800 to-blue-600 px-4">
    <div class="login-container bg-white p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <!-- En-tête -->
        <div class="text-center mb-6">
            <div class="bg-blue-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-graduation-cap text-blue-700 text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-1">École Mandroso</h1>
            <p class="text-gray-500 text-sm">Excellence et Développement</p>
        </div>

        <!-- Message d'erreur -->
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" action="" class="space-y-5">
            <!-- Email -->
            <div>
                <label class="block text-gray-700 font-medium mb-2">
                    <i class="fas fa-envelope text-gray-400 mr-2"></i>Adresse email
                </label>
                <input type="email" name="email" required autofocus
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                       placeholder="exemple@email.com"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <!-- Mot de passe -->
            <div>
                <label class="block text-gray-700 font-medium mb-2">
                    <i class="fas fa-lock text-gray-400 mr-2"></i>Mot de passe
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                           placeholder="••••••••"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                    <button type="button" onclick="togglePassword()" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Se souvenir de moi -->
            <div class="flex items-center justify-between">
                <label class="flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="mr-2 rounded">
                    Se souvenir de moi
                </label>
                <a href="mot-de-passe-oublie.php" class="text-sm text-blue-600 hover:text-blue-800">
                    Mot de passe oublié ?
                </a>
            </div>

            <!-- Bouton de connexion -->
            <button type="submit"
                    class="w-full bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 transition duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                <i class="fas fa-sign-in-alt mr-2"></i> Se connecter
            </button>
        </form>

        <!-- Informations supplémentaires -->
        <div class="mt-6 pt-6 border-t border-gray-200">
            <p class="text-center text-sm text-gray-600">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Besoin d'aide ? Contactez l'administration
            </p>
        </div>

        <!-- Pied de page -->
        <div class="text-center mt-6 text-xs text-gray-500">
            © <?= date('Y') ?> École Mandroso — Tous droits réservés
        </div>
    </div>
</div>

<!-- Script pour afficher/masquer le mot de passe -->
<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
});
</script>

</body>
</html>
