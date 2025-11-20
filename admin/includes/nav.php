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
                <!-- Notifications -->
                <div class="relative hidden md:block">
                    <button class="relative text-white hover:bg-blue-600 p-2 rounded transition">
                        <i class="fas fa-bell text-xl"></i>
                        <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">3</span>
                    </button>
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
                    
                    <!-- Menu déroulant -->
                    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                        <!-- En-tête du menu -->
                        <div class="px-4 py-3 border-b border-gray-200">
                            <p class="text-sm font-semibold text-gray-800">
                                <?= htmlspecialchars($_SESSION['username']) ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= htmlspecialchars($_SESSION['email'] ?? 'admin@ecole.mg') ?>
                            </p>
                            <span class="inline-block mt-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                <?= ucfirst($_SESSION['role']) ?>
                            </span>
                        </div>
                        
                        <!-- Options du menu -->
                        <div class="py-1">
                            <a href="../pages/dashboard.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-home w-5 mr-3 text-gray-500"></i>
                                Tableau de bord
                            </a>
                            
                            <a href="../pages/parametres.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-cog w-5 mr-3 text-gray-500"></i>
                                Paramètres
                            </a>
                            
                            <a href="../pages/profil.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                                <i class="fas fa-user w-5 mr-3 text-gray-500"></i>
                                Mon profil
                            </a>
                        </div>
                        
                        <!-- Séparateur -->
                        <div class="border-t border-gray-200 my-1"></div>
                        
                        <!-- Déconnexion -->
                        <a href="../logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition font-medium">
                            <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                            Déconnexion
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
    
    // Fermer le menu si on clique en dehors
    document.addEventListener('click', function(event) {
        const userMenuContainer = document.getElementById('userMenuContainer');
        const dropdown = document.getElementById('userDropdown');
        
        if (!userMenuContainer.contains(event.target)) {
            dropdown.classList.add('hidden');
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
</style>