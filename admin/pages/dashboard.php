<?php
session_start();

// ✅ Vérification de la connexion
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Vérification du rôle admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

include('../config.php');

// Récupération des statistiques
$stats = [];

// Nombre total d'élèves actifs
$result = $conn->query("SELECT COUNT(*) as total FROM eleves WHERE statut = 'actif'");
$stats['eleves'] = $result->fetch_assoc()['total'];

// Nombre total d'enseignants
$result = $conn->query("SELECT COUNT(*) as total FROM enseignants WHERE statut = 'actif'");
$stats['enseignants'] = $result->fetch_assoc()['total'];

// Nombre de classes
$result = $conn->query("SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = (SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1)");
$stats['classes'] = $result->fetch_assoc()['total'];

// Paiements en attente
$result = $conn->query("SELECT COUNT(*) as total FROM frais_scolarite WHERE statut = 'en_attente'");
$stats['paiements_attente'] = $result->fetch_assoc()['total'];

// Absences du jour
$result = $conn->query("SELECT COUNT(*) as total FROM absences WHERE date = CURDATE()");
$stats['absences_jour'] = $result->fetch_assoc()['total'];

// Récupération des activités récentes
$activites = $conn->query("SELECT la.*, u.username FROM logs_activite la 
                          LEFT JOIN users u ON la.user_id = u.id 
                          ORDER BY la.date_heure DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin | École Mandroso</title>
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
</head>
<body class="bg-gray-50">

    <!-- ====== HEADER ====== -->
    <header class="fixed top-0 left-0 right-0 h-16 bg-white border-b z-50 flex items-center px-4 justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <!-- Bouton burger : visible uniquement < lg -->
            <button id="burgerBtn" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-md border hover:bg-gray-100 transition-colors"
                aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <!-- Bouton toggle sidebar : visible uniquement >= lg (desktop) -->
            <button id="toggleSidebarBtn" class="hidden lg:inline-flex items-center justify-center w-10 h-10 rounded-md border hover:bg-gray-100 transition-colors"
                aria-label="Réduire/Agrandir le menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>

            <i class="fas fa-graduation-cap text-2xl text-blue-600"></i>
            <span class="text-xl font-bold text-gray-800 hidden sm:block">École Mandroso</span>
        </div>
        
        <div class="flex items-center gap-3">
            <!-- Notifications -->
            <button class="relative p-2 hover:bg-gray-100 rounded-full transition-colors">
                <i class="fas fa-bell text-gray-600"></i>
                <?php if ($stats['paiements_attente'] > 0 || $stats['absences_jour'] > 0): ?>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                <?php endif; ?>
            </button>

            <!-- Profil utilisateur -->
            <div class="flex items-center gap-2">
                <div class="hidden md:block text-right">
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($_SESSION['username']) ?></p>
                    <p class="text-xs text-gray-500">Administrateur</p>
                </div>
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                    <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                </div>
            </div>

            <!-- Déconnexion -->
            <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <i class="fas fa-sign-out-alt"></i>
                <span class="hidden sm:inline">Déconnexion</span>
            </a>
        </div>
    </header>

    <!-- ====== SIDEBAR ====== -->
    <aside id="sidebar" class="sidebar bg-white border-r">
        <div class="p-4">
            <!-- Logo (caché en mode collapsed) -->
            <div class="logo-text mb-6 px-3">
                <h2 class="text-lg font-bold text-gray-800">Menu Principal</h2>
            </div>

            <nav class="space-y-1">
                <!-- Dashboard -->
                <a href="dashboard.php" class="menu-item flex items-center gap-3 p-3 bg-blue-50 text-blue-700 font-medium rounded-lg transition-all duration-200">
                    <i class="fas fa-home w-5 text-center"></i>
                    <span class="menu-text">Tableau de bord</span>
                    <span class="tooltip">Tableau de bord</span>
                </a>

                <!-- Élèves -->
                <a href="eleves.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-user-graduate w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Élèves</span>
                    <span class="tooltip">Élèves</span>
                </a>

                <!-- Enseignants -->
                <a href="enseignants.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-chalkboard-teacher w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Enseignants</span>
                    <span class="tooltip">Enseignants</span>
                </a>

                <!-- Classes -->
                <a href="classes.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-school w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Classes</span>
                    <span class="tooltip">Classes</span>
                </a>

                <!-- Notes -->
                <a href="notes.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-file-alt w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Notes</span>
                    <span class="tooltip">Notes</span>
                </a>

                <!-- Présences -->
                <a href="presences.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-calendar-check w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Présences</span>
                    <span class="tooltip">Présences</span>
                </a>

                <!-- Paiements -->
                <a href="paiements.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-money-bill-wave w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Paiements</span>
                    <span class="tooltip">Paiements</span>
                </a>

                <!-- Emploi du Temps -->
                <a href="emploi-temps.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-calendar-alt w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Emploi du Temps</span>
                    <span class="tooltip">Emploi du Temps</span>
                </a>

                <!-- Messages -->
                <a href="messages.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-envelope w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Messages</span>
                    <span class="tooltip">Messages</span>
                </a>

                <!-- Paramètres -->
                <a href="parametres.php" class="menu-item flex items-center gap-3 p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-all duration-200">
                    <i class="fas fa-cog w-5 text-center text-gray-600"></i>
                    <span class="menu-text">Paramètres</span>
                    <span class="tooltip">Paramètres</span>
                </a>
            </nav>
        </div>
    </aside>

    <!-- ====== OVERLAY (voile noir mobile) ====== -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- ====== CONTENU PRINCIPAL ====== -->
    <main class="main-content pt-20" id="mainContent">
        <div class="p-4 md:p-8">
            <!-- En-tête -->
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                    Tableau de bord
                </h1>
                <p class="text-gray-600 text-sm md:text-base">Vue d'ensemble de l'école Mandroso</p>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                <!-- Élèves -->
                <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm mb-1">Élèves actifs</p>
                            <p class="text-3xl font-bold"><?= $stats['eleves'] ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                            <i class="fas fa-user-graduate text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Enseignants -->
                <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm mb-1">Enseignants</p>
                            <p class="text-3xl font-bold"><?= $stats['enseignants'] ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                            <i class="fas fa-chalkboard-teacher text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Classes -->
                <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm mb-1">Classes</p>
                            <p class="text-3xl font-bold"><?= $stats['classes'] ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                            <i class="fas fa-door-open text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Paiements en attente -->
                <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm mb-1">Paiements</p>
                            <p class="text-3xl font-bold"><?= $stats['paiements_attente'] ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-4 rounded-lg">
                            <i class="fas fa-clock text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="mb-8 bg-white rounded-xl shadow-lg p-4 md:p-6">
                <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                    Actions rapides
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                    <a href="eleves.php?action=add" class="flex flex-col items-center justify-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition group">
                        <i class="fas fa-user-plus text-2xl md:text-3xl text-blue-600 mb-2 group-hover:scale-110 transition"></i>
                        <span class="text-xs md:text-sm font-medium text-gray-700 text-center">Ajouter élève</span>
                    </a>
                    <a href="presences.php" class="flex flex-col items-center justify-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition group">
                        <i class="fas fa-clipboard-check text-2xl md:text-3xl text-green-600 mb-2 group-hover:scale-110 transition"></i>
                        <span class="text-xs md:text-sm font-medium text-gray-700 text-center">Faire l'appel</span>
                    </a>
                    <a href="notes.php" class="flex flex-col items-center justify-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition group">
                        <i class="fas fa-pen text-2xl md:text-3xl text-purple-600 mb-2 group-hover:scale-110 transition"></i>
                        <span class="text-xs md:text-sm font-medium text-gray-700 text-center">Saisir notes</span>
                    </a>
                    <a href="paiements.php" class="flex flex-col items-center justify-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition group">
                        <i class="fas fa-cash-register text-2xl md:text-3xl text-orange-600 mb-2 group-hover:scale-110 transition"></i>
                        <span class="text-xs md:text-sm font-medium text-gray-700 text-center">Paiement</span>
                    </a>
                </div>
            </div>

            <!-- Graphiques et activités -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
                <!-- Activités récentes -->
                <div class="bg-white rounded-xl shadow-lg p-4 md:p-6">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-history text-blue-600 mr-2"></i>
                        Activités récentes
                    </h2>
                    <div class="space-y-3">
                        <?php if ($activites->num_rows > 0): ?>
                            <?php while($activite = $activites->fetch_assoc()): ?>
                                <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                    <i class="fas fa-circle text-blue-500 text-xs mt-2"></i>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-800 truncate">
                                            <span class="font-medium"><?= htmlspecialchars($activite['username']) ?></span>
                                            - <?= htmlspecialchars($activite['action']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('d/m/Y H:i', strtotime($activite['date_heure'])) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm text-center py-4">Aucune activité récente</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alertes -->
                <div class="bg-white rounded-xl shadow-lg p-4 md:p-6">
                    <h2 class="text-lg md:text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-bell text-yellow-600 mr-2"></i>
                        Alertes
                    </h2>
                    <div class="space-y-3">
                        <?php if ($stats['absences_jour'] > 0): ?>
                            <div class="flex items-start space-x-3 p-3 bg-yellow-50 border-l-4 border-yellow-500 rounded">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mt-1"></i>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800">Absences aujourd'hui</p>
                                    <p class="text-xs text-gray-600"><?= $stats['absences_jour'] ?> élève(s) absent(s)</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($stats['paiements_attente'] > 0): ?>
                            <div class="flex items-start space-x-3 p-3 bg-red-50 border-l-4 border-red-500 rounded">
                                <i class="fas fa-money-bill-wave text-red-600 mt-1"></i>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800">Paiements en retard</p>
                                    <p class="text-xs text-gray-600"><?= $stats['paiements_attente'] ?> paiement(s) en attente</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($stats['absences_jour'] == 0 && $stats['paiements_attente'] == 0): ?>
                            <div class="flex items-start space-x-3 p-3 bg-green-50 border-l-4 border-green-500 rounded">
                                <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800">Tout est à jour !</p>
                                    <p class="text-xs text-gray-600">Aucune alerte pour le moment</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ====== SCRIPT ====== -->
    <script>
        let sidebar, overlay, burgerBtn, toggleBtn;
        let isMobile = window.innerWidth < 1024;

        document.addEventListener('DOMContentLoaded', () => {
            sidebar   = document.getElementById('sidebar');
            overlay   = document.getElementById('sidebarOverlay');
            burgerBtn = document.getElementById('burgerBtn');
            toggleBtn = document.getElementById('toggleSidebarBtn');

            // Charger l'état sauvegardé (desktop uniquement)
            if (!isMobile) {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    updateToggleIcon(true);
                }
            }

            // Clic burger (mobile)
            burgerBtn?.addEventListener('click', toggleMobileSidebar);

            // Clic toggle (desktop)
            toggleBtn?.addEventListener('click', toggleDesktopCollapse);

            // Clic overlay => fermer
            overlay?.addEventListener('click', closeMobileSidebar);

            // Fermer au clic d'un lien sur mobile
            document.querySelectorAll('#sidebar a').forEach(a => {
                a.addEventListener('click', () => { 
                    if (isMobile) closeMobileSidebar(); 
                });
            });

            // ESC pour fermer
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && isMobile && sidebar.classList.contains('mobile-open')) {
                    closeMobileSidebar();
                }
            });

            // Resize
            window.addEventListener('resize', handleResize);
        });

        function handleResize() {
            const wasMobile = isMobile;
            isMobile = window.innerWidth < 1024;

            // Si on passe en desktop
            if (wasMobile && !isMobile) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
            }

            // Si on passe en mobile
            if (!wasMobile && isMobile) {
                sidebar.classList.remove('collapsed');
                closeMobileSidebar();
            }
        }

        // ===== MOBILE =====
        function toggleMobileSidebar() {
            const isOpen = sidebar.classList.contains('mobile-open');
            if (isOpen) closeMobileSidebar();
            else openMobileSidebar();
        }

        function openMobileSidebar() {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
            document.body.classList.add('overflow-hidden');
            burgerBtn?.setAttribute('aria-expanded', 'true');
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.classList.remove('overflow-hidden');
            burgerBtn?.setAttribute('aria-expanded', 'false');
        }

        // ===== DESKTOP =====
        function toggleDesktopCollapse() {
            if (isMobile) return;
            
            const isCollapsed = sidebar.classList.toggle('collapsed');
            
            // Sauvegarder l'état
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            
            // Animation de l'icône
            updateToggleIcon(isCollapsed);
        }

        function updateToggleIcon(isCollapsed) {
            const icon = toggleBtn?.querySelector('svg');
            if (icon) {
                icon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
                icon.style.transition = 'transform 0.3s ease';
            }
        }
    </script>

</body>
</html>