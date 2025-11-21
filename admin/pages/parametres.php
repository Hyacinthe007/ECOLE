<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config.php';

$page = $_GET['page'] ?? 'ecole';
$allowed = ['ecole', 'annees', 'matieres', 'enseignants', 'classes', 'securites'];

// Passer la variable $page aux fichiers inclus pour qu'ils puissent l'utiliser dans les liens
$current_page = $page;

// Traiter les requêtes POST avant d'inclure nav.php pour éviter les erreurs de headers
// On définit une variable pour indiquer qu'on est en mode traitement uniquement
if (in_array($page, $allowed) && file_exists(__DIR__ . "/../parametres/$page.php") && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Utiliser un buffer de sortie pour capturer toute sortie accidentelle
    ob_start();
    
    // Définir une variable pour indiquer qu'on est en mode traitement POST uniquement
    $processingPostOnly = true;
    
    // Inclure le fichier pour traiter les POST (redirections)
    // Si une redirection se produit, le script s'arrêtera avec exit() avant d'envoyer le buffer
    include(__DIR__ . "/../parametres/$page.php");
    
    // Si on arrive ici, aucune redirection n'a eu lieu, on nettoie le buffer
    ob_end_clean();
    unset($processingPostOnly);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres | École Mandroso</title>
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-style.css">
</head>
<body class="bg-gray-50">
    
    <?php include('../includes/nav.php'); ?>
    
    <div class="flex">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="main-content flex-1 p-4 md:p-8 mt-16">
            
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-cog text-blue-600 mr-2"></i>
                    Paramètres
                </h1>
                <p class="text-gray-600">Configuration générale de l'école</p>
            </div>
            <div class="mb-6 border-b border-gray-200 overflow-x-auto">
                <nav class="flex space-x-2 md:space-x-4">
                    <a href="?page=ecole" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium <?= $page == 'ecole' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent' ?>">
                        <i class="fas fa-school mr-1 md:mr-2"></i> <span class="hidden sm:inline">Informations</span> École
                    </a>
                    <a href="?page=annees" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium <?= $page == 'annees' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent' ?>">
                        <i class="fas fa-calendar-alt mr-1 md:mr-2"></i> Années <span class="hidden sm:inline">Scolaires</span>
                    </a>
                    <a href="?page=matieres" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium <?= $page == 'matieres' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent' ?>">
                        <i class="fas fa-book mr-1 md:mr-2"></i> Matières
                    </a>
                    <a href="?page=enseignants" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium <?= $page == 'enseignants' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent' ?>">
                        <i class="fas fa-chalkboard-teacher mr-1 md:mr-2"></i> Enseignants
                    </a>
                    <a href="?page=classes" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium <?= $page == 'classes' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent' ?>">
                        <i class="fas fa-door-open mr-1 md:mr-2"></i> Classes
                    </a>
                    <a href="?page=securites" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium <?= $page == 'securites' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent' ?>">
                        <i class="fas fa-lock mr-1 md:mr-2"></i> Sécurité
                    </a>
                </nav>
            </div>
            <div>
                <?php
                if (in_array($page, $allowed)) {
                    include("../parametres/$page.php");
                } else {
                    include("../parametres/ecole.php");
                }
                ?>
            </div>
            
        </main>
    </div>
    
    <script>
        function showTab(tabName) {
            // Cacher tous les contenus
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Réinitialiser tous les boutons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-600', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Afficher le contenu sélectionné
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Activer le bouton sélectionné
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.remove('border-transparent', 'text-gray-500');
            activeButton.classList.add('border-blue-600', 'text-blue-600');
        }
    </script>
    
</body>
</html>