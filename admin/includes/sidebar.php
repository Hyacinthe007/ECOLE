<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- ====== HEADER avec bouton burger ====== -->
<header class="fixed top-0 left-0 right-0 h-16 bg-white border-b z-50 flex items-center px-4 justify-between">
  <div class="flex items-center gap-2">
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

    <span class="font-semibold">École Mandroso</span>
  </div>
  <div class="flex items-center gap-3">
    <!-- Notifications, profil, etc. -->
    <button class="relative p-2 hover:bg-gray-100 rounded-full transition-colors">
      <i class="fas fa-bell text-gray-600"></i>
      <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
    </button>
    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium">
      A
    </div>
  </div>
</header>

<!-- ====== SIDEBAR ====== -->
<aside id="sidebar" class="sidebar bg-white border-r">
  <div class="p-4">
    <!-- Logo (caché en mode collapsed) -->
    <div class="logo-text mb-6 px-3">
      <h2 class="text-xl font-bold text-gray-800">École Mandroso</h2>
    </div>

    <nav class="space-y-1">
      <!-- Tableau de bord -->
      <a href="dashboard.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'dashboard' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-home w-5 text-center <?= $current_page == 'dashboard' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Tableau de bord</span>
        <span class="tooltip">Tableau de bord</span>
      </a>

      <!-- Élèves -->
      <a href="eleves.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'eleves' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-user-graduate w-5 text-center <?= $current_page == 'eleves' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Élèves</span>
        <span class="tooltip">Élèves</span>
      </a>

      <!-- Notes -->
      <a href="notes.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'notes' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-file-alt w-5 text-center <?= $current_page == 'notes' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Notes</span>
        <span class="tooltip">Notes</span>
      </a>

      <!-- Bulletins -->
      <a href="bulletins.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'bulletins' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-chalkboard w-5 text-center <?= $current_page == 'bulletins' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Bulletins</span>
        <span class="tooltip">Bulletins</span>
      </a>

      <!-- Présences -->
      <a href="presences.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'presences' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-calendar-check w-5 text-center <?= $current_page == 'presences' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Présences</span>
        <span class="tooltip">Présences</span>
      </a>

      <!-- Paiements -->
      <a href="paiements.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'paiements' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-money-bill-wave w-5 text-center <?= $current_page == 'paiements' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Paiements</span>
        <span class="tooltip">Paiements</span>
      </a>

      <!-- Emploi du Temps -->
      <a href="emploi_du_temps.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'emploi_du_temps' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-calendar-alt w-5 text-center <?= $current_page == 'emploi_du_temps' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Emploi du Temps</span>
        <span class="tooltip">Emploi du Temps</span>
      </a>

      <!-- Messages -->
      <a href="messages.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'messages' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-envelope w-5 text-center <?= $current_page == 'messages' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Messages</span>
        <span class="tooltip">Messages</span>
      </a>

      <!-- Paramètres -->
      <a href="parametres.php" class="menu-item flex items-center gap-3 p-3 <?= $current_page == 'parametres' ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-700 hover:bg-gray-50' ?> rounded-lg transition-all duration-200">
        <i class="fas fa-cog w-5 text-center <?= $current_page == 'parametres' ? 'text-blue-700' : 'text-gray-600' ?>"></i>
        <span class="menu-text">Paramètres</span>
        <span class="tooltip">Paramètres</span>
      </a>
    </nav>
  </div>
</aside>

<!-- ====== OVERLAY (voile noir mobile) ====== -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

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
  if (isMobile) return; // Ne fonctionne pas sur mobile
  
  const isCollapsed = sidebar.classList.toggle('collapsed');
  
  // Sauvegarder l'état
  localStorage.setItem('sidebarCollapsed', isCollapsed);
  
  // Animation de l'icône
  const icon = toggleBtn.querySelector('svg');
  if (isCollapsed) {
    icon.style.transform = 'rotate(180deg)';
  } else {
    icon.style.transform = 'rotate(0deg)';
  }
}
</script>

<style>
/* Transition douce pour l'icône du bouton toggle */
#toggleSidebarBtn svg {
  transition: transform 0.3s ease;
}
</style>