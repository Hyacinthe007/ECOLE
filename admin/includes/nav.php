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
                
                <!-- Bouton déconnexion -->
                <a href="../pages/logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded transition text-sm flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
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