<!-- Lien vers le fichier CSS global -->
<link rel="stylesheet" href="../assets/css/admin-style.css">

<nav class="bg-blue-700 text-white shadow-lg fixed w-full top-0 z-30">
    <div class="px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-4">
                <!-- Bouton burger mobile -->
                <button onclick="toggleSidebar()" class="lg:hidden text-white hover:bg-blue-600 p-2 rounded transition">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <!-- Bouton toggle sidebar desktop -->
                <button onclick="toggleSidebarDesktop()" class="hidden lg:block text-white hover:bg-blue-600 p-2 rounded transition">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <!-- Logo et nom -->
                <div class="flex items-center space-x-3">
                    <i class="fas fa-graduation-cap text-2xl hidden sm:block"></i>
                    <span class="text-xl font-bold hidden sm:block">École Mandroso</span>
                </div>
            </div>
            
            <!-- Partie droite -->
            <div class="flex items-center space-x-4">
                <!-- Notifications avec activités récentes -->
                <?php
                // Récupérer les activités récentes pour les notifications
                if (isset($conn)) {
                    $activites_notif = $conn->query(
                        "SELECT la.*, u.username 
                         FROM logs_activite la 
                         LEFT JOIN users u ON la.user_id = u.id 
                         ORDER BY la.date_heure DESC 
                         LIMIT 10"
                    );
                    $activites_count = $activites_notif->num_rows;
                } else {
                    $activites_count = 0;
                    $activites_notif = null;
                }
                ?>
                <div class="relative hidden md:block" id="notificationsContainer">
                    <button onclick="toggleNotifications()" class="relative text-white hover:bg-blue-600 p-2 rounded transition">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($activites_count > 0): ?>
                            <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" id="notificationBadge"><?= $activites_count > 9 ? '9+' : $activites_count ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Dropdown des notifications -->
                    <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-y-auto">
                        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-800">Activités récentes</h3>
                            <span class="text-xs text-gray-500"><?= $activites_count ?> activité<?= $activites_count > 1 ? 's' : '' ?></span>
                        </div>
                        <div class="py-2">
                            <?php if ($activites_count > 0 && $activites_notif): ?>
                                <?php 
                                $activites_notif->data_seek(0);
                                while($activite = $activites_notif->fetch_assoc()): 
                                ?>
                                    <div class="px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                                        <div class="flex items-start space-x-3">
                                            <i class="fas fa-circle text-blue-500 text-xs mt-2"></i>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-800">
                                                    <span class="font-medium"><?= htmlspecialchars($activite['username'] ?? 'Utilisateur') ?></span>
                                                    - <?= htmlspecialchars($activite['action'] ?? 'Action') ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?= $activite['date_heure'] ? date('d/m/Y H:i', strtotime($activite['date_heure'])) : '' ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-3xl mb-2"></i>
                                    <p class="text-sm">Aucune activité récente</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Info utilisateur -->
                <div class="flex items-center space-x-3">
                    <span class="text-sm hidden md:block">
                        <i class="fas fa-user-circle mr-2"></i>
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </span>
                    <span class="hidden md:block text-xs bg-blue-600 px-2 py-1 rounded">
                        <?= ucfirst($_SESSION['role']) ?>
                    </span>
                </div>
                
                <!-- Menu Avatar avec dropdown -->
                <div class="relative" id="userMenuContainer">
                    <button onclick="toggleUserMenu()" class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium hover:bg-blue-400 transition focus:outline-none focus:ring-2 focus:ring-white">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </button>
                    
                    <!-- Menu déroulant utilisateur -->
                    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                        <!-- En-tête du menu avec informations utilisateur -->
                        <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold text-lg shadow-md">
                                    <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">
                                        <?= htmlspecialchars($_SESSION['username']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate">
                                        <?= htmlspecialchars($_SESSION['email'] ?? 'admin@ecole.mg') ?>
                                    </p>
                                    <span class="inline-block mt-1.5 px-2 py-0.5 text-xs bg-blue-600 text-white rounded-full font-medium">
                                        <?= ucfirst($_SESSION['role']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Options du menu -->
                        <div class="py-1">
                            <a href="../pages/dashboard.php" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 transition group">
                                <i class="fas fa-home w-5 mr-3 text-gray-400 group-hover:text-blue-600 transition"></i>
                                <span class="group-hover:text-blue-600">Tableau de bord</span>
                            </a>
                            
                            <a href="../pages/parametres.php?page=securites" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 transition group">
                                <i class="fas fa-user-circle w-5 mr-3 text-gray-400 group-hover:text-blue-600 transition"></i>
                                <span class="group-hover:text-blue-600">Mon profil</span>
                            </a>
                            
                            <a href="../pages/parametres.php?page=securites" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 transition group">
                                <i class="fas fa-key w-5 mr-3 text-gray-400 group-hover:text-blue-600 transition"></i>
                                <span class="group-hover:text-blue-600">Changer le mot de passe</span>
                            </a>
                            
                            <a href="../pages/parametres.php" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 transition group">
                                <i class="fas fa-cog w-5 mr-3 text-gray-400 group-hover:text-blue-600 transition"></i>
                                <span class="group-hover:text-blue-600">Paramètres</span>
                            </a>
                        </div>
                        
                        <!-- Séparateur -->
                        <div class="border-t border-gray-200 my-1"></div>
                        
                        <!-- Déconnexion -->
                        <a href="../logout.php" class="flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition font-medium group">
                            <i class="fas fa-sign-out-alt w-5 mr-3 group-hover:transform group-hover:translate-x-1 transition"></i>
                            <span>Déconnexion</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script>
    // Toggle sidebar mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
        
        // Empêcher le scroll du body quand le sidebar est ouvert
        if (sidebar.classList.contains('mobile-open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'auto';
        }
    }
    
    // Toggle sidebar desktop (mode réduit)
    function toggleSidebarDesktop() {
        const sidebar = document.getElementById('sidebar');
        
        sidebar.classList.toggle('collapsed');
        
        // Sauvegarder l'état dans le localStorage
        if (sidebar.classList.contains('collapsed')) {
            localStorage.setItem('sidebarCollapsed', 'true');
        } else {
            localStorage.setItem('sidebarCollapsed', 'false');
        }
    }
    
    // Toggle menu utilisateur
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('hidden');
    }
    
    // Toggle notifications
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationsDropdown');
        dropdown.classList.toggle('hidden');
    }
    
    // Fermer le menu si on clique en dehors
    document.addEventListener('click', function(event) {
        const userMenuContainer = document.getElementById('userMenuContainer');
        const userDropdown = document.getElementById('userDropdown');
        const notificationsContainer = document.getElementById('notificationsContainer');
        const notificationsDropdown = document.getElementById('notificationsDropdown');
        
        if (!userMenuContainer.contains(event.target)) {
            userDropdown.classList.add('hidden');
        }
        
        if (!notificationsContainer.contains(event.target)) {
            notificationsDropdown.classList.add('hidden');
        }
    });
    
    // Restaurer l'état du sidebar au chargement
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (isCollapsed && window.innerWidth >= 1024) {
            sidebar.classList.add('collapsed');
        }
    });
    
    // Fermer le sidebar mobile lors du clic sur un lien
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarLinks = document.querySelectorAll('.sidebar a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });
    });
    
    // Fermer le sidebar mobile si on redimensionne vers desktop
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
</script>

<style>
/* Animation pour le dropdown */
#userDropdown {
    animation: slideDown 0.2s ease-out;
    transform-origin: top right;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Effet hover sur l'avatar */
#userMenuContainer button:hover {
    transform: scale(1.05);
}

#userMenuContainer button {
    transition: all 0.2s ease;
}

/* Animation pour le dropdown des notifications */
#notificationsDropdown {
    animation: slideDown 0.2s ease-out;
    transform-origin: top right;
}

/* Effet hover sur le bouton notifications */
#notificationsContainer button:hover {
    transform: scale(1.05);
}

#notificationsContainer button {
    transition: all 0.2s ease;
}
</style>