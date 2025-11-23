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
        
        /* Amélioration pour très petits écrans */
        @media (max-width: 375px) {
            .login-container {
                padding: 1rem !important;
            }
        }
        
        /* Amélioration de la zone tactile sur mobile */
        @media (max-width: 768px) {
            button, a, input[type="checkbox"] {
                min-height: 44px;
                min-width: 44px;
            }
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-blue-800 to-blue-600 px-3 sm:px-4 py-4 sm:py-8">
    <div class="login-container bg-white p-4 sm:p-6 md:p-8 rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-md">
        <!-- En-tête -->
        <div class="text-center mb-4 sm:mb-6">
            <div class="bg-blue-100 w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                <i class="fas fa-graduation-cap text-blue-700 text-3xl sm:text-4xl"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-1">École Mandroso</h1>
            <p class="text-gray-500 text-xs sm:text-sm">Excellence et Développement</p>
        </div>

        <!-- Message d'erreur -->
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-3 sm:p-4 rounded mb-4 sm:mb-6 flex items-start sm:items-center text-sm sm:text-base">
                <i class="fas fa-exclamation-circle mr-2 sm:mr-3 mt-0.5 sm:mt-0 flex-shrink-0"></i>
                <span class="break-words"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" action="" class="space-y-4 sm:space-y-5">
            <!-- Email -->
            <div>
                <label class="block text-gray-700 font-medium mb-1.5 sm:mb-2 text-sm sm:text-base">
                    <i class="fas fa-envelope text-gray-400 mr-1.5 sm:mr-2"></i>Adresse email
                </label>
                <input type="email" name="email" required autofocus
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                       placeholder="exemple@email.com"
                       class="w-full px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <!-- Mot de passe -->
            <div>
                <label class="block text-gray-700 font-medium mb-1.5 sm:mb-2 text-sm sm:text-base">
                    <i class="fas fa-lock text-gray-400 mr-1.5 sm:mr-2"></i>Mot de passe
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                           placeholder="••••••••"
                           class="w-full px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base pr-10 sm:pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                    <button type="button" onclick="togglePassword()" 
                            class="absolute right-2 sm:right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 p-1 sm:p-0">
                        <i class="fas fa-eye text-sm sm:text-base" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Se souvenir de moi -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-0">
                <label class="flex items-center text-xs sm:text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="mr-2 rounded w-4 h-4">
                    Se souvenir de moi
                </label>
                <a href="mot-de-passe-oublie.php" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 whitespace-nowrap">
                    Mot de passe oublié ?
                </a>
            </div>

            <!-- Bouton de connexion -->
            <button type="submit"
                    class="w-full bg-blue-600 text-white font-semibold py-2.5 sm:py-3 text-sm sm:text-base rounded-lg hover:bg-blue-700 active:bg-blue-800 transition duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 active:translate-y-0">
                <i class="fas fa-sign-in-alt mr-2"></i> Se connecter
            </button>
        </form>

        <!-- Informations supplémentaires -->
        <div class="mt-4 sm:mt-6 pt-4 sm:pt-6 border-t border-gray-200">
            <p class="text-center text-xs sm:text-sm text-gray-600 px-2">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Besoin d'aide ? Contactez l'administration
            </p>
        </div>

        <!-- Pied de page -->
        <div class="text-center mt-4 sm:mt-6 text-xs text-gray-500 px-2">
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
