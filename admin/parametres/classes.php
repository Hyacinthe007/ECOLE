<?php

declare(strict_types=1);

// --- TRAITEMENT PHP AVANT TOUT AFFICHAGE ---
// Note: session_start() et vérifications déjà faites dans parametres.php
require_once __DIR__ . '/../config.php';

$message = '';
$error = '';

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_classe') {
    $classe_id = intval($_POST['classe_id']);
    $conn->query("DELETE FROM classes WHERE id = $classe_id");
    header("Location: ../pages/parametres.php?page=classes&success=1");
    exit();
}

// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && ($_POST['action'] === 'add_classe' || $_POST['action'] === 'update_classe')) {

    $id = isset($_POST['classe_id']) ? intval($_POST['classe_id']) : 0;
    $nom = trim($_POST['nom']);
    $niveau = $_POST['niveau'];
    $section = trim($_POST['section'] ?? '');
    $capacite_max = intval($_POST['capacite_max']);
    $annee_scolaire_id = intval($_POST['annee_scolaire_id']);
    $enseignant_titulaire_id = isset($_POST['enseignant_titulaire_id']) && $_POST['enseignant_titulaire_id'] > 0 ? intval($_POST['enseignant_titulaire_id']) : null;

    // Validation
    if (empty($nom)) {
        $error = "Le nom de la classe est obligatoire.";
    } elseif ($capacite_max <= 0) {
        $error = "La capacité maximale doit être supérieure à 0.";
    } elseif ($annee_scolaire_id <= 0) {
        $error = "Veuillez sélectionner une année scolaire.";
    }

    // Si pas d'erreur de validation, on continue
    if (empty($error)) {
        if ($id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE classes SET nom=?, niveau=?, section=?, capacite_max=?, annee_scolaire_id=?, enseignant_titulaire_id=? WHERE id=?");
            $stmt->bind_param("sssiiii", $nom, $niveau, $section, $capacite_max, $annee_scolaire_id, $enseignant_titulaire_id, $id);
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO classes (nom, niveau, section, capacite_max, annee_scolaire_id, enseignant_titulaire_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiii", $nom, $niveau, $section, $capacite_max, $annee_scolaire_id, $enseignant_titulaire_id);
        }

        try {
            $stmt->execute();
            header("Location: ../pages/parametres.php?page=classes&success=1");
            exit();
        } catch (mysqli_sql_exception $e) {
            $error = "Erreur SQL : " . $e->getMessage();
        }
    }
}

// Si on est en mode traitement POST uniquement, on s'arrête ici
if (isset($processingPostOnly) && $processingPostOnly) {
    return;
}

// --- AFFICHAGE HTML ET INCLUDES APRES TOUT TRAITEMENT ---
// Note: nav.php est déjà inclus dans parametres.php, pas besoin de le réinclure

// Récupérer l'année scolaire active
$annee_active = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
$annee_active_id = 0;
if ($annee_active->num_rows > 0) {
    $annee_active_id = $annee_active->fetch_assoc()['id'];
}

// Récupération des données avec comptage des élèves inscrits (pour l'année active uniquement)
$annee_condition = $annee_active_id > 0 ? "AND i.annee_scolaire_id = $annee_active_id" : "";
$classes = $conn->query(
    "SELECT c.*, 
            COUNT(DISTINCT i.eleve_id) as nb_eleves, 
            a.libelle as annee_libelle,
            u.username as enseignant_titulaire_nom
     FROM classes c 
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'valide' $annee_condition
     LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id 
     LEFT JOIN users u ON c.enseignant_titulaire_id = u.id
     GROUP BY c.id 
     ORDER BY FIELD(c.niveau, 'maternelle', 'primaire', 'college', 'lycee'), c.nom"
);

// Classe à modifier
$classe_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM classes WHERE id = $id");
    $classe_edit = $res->fetch_assoc();
}

?>
<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
        <i class="fas fa-check-circle mr-2"></i> Opération réussie !
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
        <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">
            <i class="fas fa-door-open text-blue-600 mr-2"></i>
            Gestion des Classes
        </h2>
        <p class="text-gray-600">Liste et gestion des classes de l'école</p>
    </div>
    <button onclick="addClasse()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Ajouter une classe
    </button>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <!-- Filtres et recherche -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
            <input type="text" id="searchClasse" 
                placeholder="Nom ou section..."
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Niveau</label>
            <select id="niveauFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Tous les niveaux</option>
                <option value="maternelle">Maternelle</option>
                <option value="primaire">Primaire</option>
                <option value="college">Collège</option>
                <option value="lycee">Lycée</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Année scolaire</label>
            <select id="anneeFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Toutes les années</option>
                <?php 
                $annees_filter = $conn->query("SELECT id, libelle FROM annees_scolaires ORDER BY date_debut DESC");
                while($annee = $annees_filter->fetch_assoc()): 
                ?>
                    <option value="<?= $annee['id'] ?>"><?= htmlspecialchars($annee['libelle']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="button" onclick="filterClasses()" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-search mr-2"></i> Filtrer
            </button>
        </div>
    </div>
    
    <!-- Liste des classes -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Niveau</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacité</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effectif</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Année scolaire</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="classesTableBody">
                <?php 
                if ($classes->num_rows > 0):
                    while($classe = $classes->fetch_assoc()): 
                        $percent = $classe['capacite_max'] > 0 ? ($classe['nb_eleves'] / $classe['capacite_max']) * 100 : 0;
                        $color = $percent >= 90 ? 'text-red-600' : ($percent >= 70 ? 'text-yellow-600' : 'text-green-600');
                ?>
                    <tr class="hover:bg-gray-50" 
                        data-niveau="<?= strtolower($classe['niveau']) ?>"
                        data-annee="<?= $classe['annee_scolaire_id'] ?>"
                        data-search="<?= strtolower($classe['nom'] . ' ' . $classe['section']) ?>">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-chalkboard text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($classe['nom']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                <?= ucfirst($classe['niveau']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-left">
                            <?= $classe['section'] ? htmlspecialchars($classe['section']) : '-' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                            <?= $classe['capacite_max'] ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="<?= $color ?> font-semibold mr-2">
                                    <?= $classe['nb_eleves'] ?>
                                </span>
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="<?= $percent >= 90 ? 'bg-red-500' : ($percent >= 70 ? 'bg-yellow-500' : 'bg-green-500') ?> h-2 rounded-full transition-all" 
                                        style="width: <?= min($percent, 100) ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($classe['annee_libelle']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editClasse(<?= htmlspecialchars(json_encode($classe)) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette classe ?')">
                                <input type="hidden" name="action" value="delete_classe">
                                <input type="hidden" name="classe_id" value="<?= $classe['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                else: 
                ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p>Aucune classe enregistrée</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout/Modification Classe -->
<div id="modalClasse" class="<?= $classe_edit ? '' : 'hidden' ?> fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <?= $classe_edit ? 'Modifier la classe' : 'Ajouter une classe' ?>
                </h2>
                <button onclick="toggleClasseModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?= $classe_edit ? 'update_classe' : 'add_classe' ?>">
                <?php if ($classe_edit): ?>
                    <input type="hidden" name="classe_id" value="<?= $classe_edit['id'] ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la classe *</label>
                        <input type="text" name="nom" required 
                               value="<?= $classe_edit ? htmlspecialchars($classe_edit['nom']) : '' ?>"
                               placeholder="Ex: 6ème A, CP1, Terminale S"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Format recommandé : Niveau + Section (ex: 6ème A, CP1)
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Niveau *</label>
                        <select name="niveau" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="maternelle" <?= ($classe_edit && $classe_edit['niveau'] == 'maternelle') ? 'selected' : '' ?>>Maternelle</option>
                            <option value="primaire" <?= ($classe_edit && $classe_edit['niveau'] == 'primaire') ? 'selected' : '' ?>>Primaire</option>
                            <option value="college" <?= ($classe_edit && $classe_edit['niveau'] == 'college') ? 'selected' : '' ?>>Collège</option>
                            <option value="lycee" <?= ($classe_edit && $classe_edit['niveau'] == 'lycee') ? 'selected' : '' ?>>Lycée</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Section</label>
                        <input type="text" name="section"
                               value="<?= $classe_edit ? htmlspecialchars($classe_edit['section']) : '' ?>"
                               placeholder="Ex: A, B, C, 1, 2..."
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Optionnel - Ex: A, D, 1, 2...
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Capacité maximale *</label>
                        <input type="number" name="capacite_max" required
                               value="<?= $classe_edit ? $classe_edit['capacite_max'] : 40 ?>"
                               min="1" max="100"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Année scolaire *</label>
                        <select name="annee_scolaire_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Sélectionner une année --</option>
                            <?php 
                            $annees_form = $conn->query("SELECT id, libelle FROM annees_scolaires ORDER BY date_debut DESC");
                            while($annee = $annees_form->fetch_assoc()): 
                            ?>
                                <option value="<?= $annee['id'] ?>" <?= ($classe_edit && $classe_edit['annee_scolaire_id'] == $annee['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($annee['libelle']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Enseignant titulaire</label>
                        <select name="enseignant_titulaire_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Aucun enseignant titulaire --</option>
                            <?php 
                            // Récupérer les utilisateurs avec le rôle enseignant
                            $enseignants_users = $conn->query("
                                SELECT u.id, u.username, e.nom, e.prenom 
                                FROM users u 
                                LEFT JOIN enseignants e ON u.id = e.user_id 
                                WHERE u.role = 'enseignant' AND u.actif = 1
                                ORDER BY COALESCE(e.nom, u.username), COALESCE(e.prenom, '')
                            ");
                            while($ens = $enseignants_users->fetch_assoc()): 
                                $display_name = ($ens['nom'] && $ens['prenom']) ? $ens['nom'] . ' ' . $ens['prenom'] : $ens['username'];
                            ?>
                                <option value="<?= $ens['id'] ?>" <?= ($classe_edit && $classe_edit['enseignant_titulaire_id'] == $ens['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($display_name) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Optionnel - Enseignant responsable de la classe
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="button" onclick="toggleClasseModal()" 
                            class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>
                        <?= $classe_edit ? 'Modifier' : 'Ajouter' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestion du modal Classe
function toggleClasseModal() {
    const modal = document.getElementById('modalClasse');
    modal.classList.toggle('hidden');
    if (!modal.classList.contains('hidden')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
}

function addClasse() {
    const form = document.querySelector('#modalClasse form');
    
    // Supprimer le champ classe_id si présent
    const idInput = form.querySelector('input[name="classe_id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'add_classe';
    
    // Réinitialiser le formulaire
    form.reset();
    
    // Réinitialiser la capacité à 40
    form.querySelector('input[name="capacite_max"]').value = 40;
    
    // Changer le titre du modal
    document.querySelector('#modalClasse h2').textContent = 'Ajouter une classe';
    
    // Ouvrir le modal
    toggleClasseModal();
}

function editClasse(classe) {
    const form = document.querySelector('#modalClasse form');
    
    // Ajouter ou mettre à jour le champ hidden classe_id
    let idInput = form.querySelector('input[name="classe_id"]');
    if (!idInput) {
        idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'classe_id';
        form.insertBefore(idInput, form.querySelector('input[name="action"]').nextSibling);
    }
    idInput.value = classe.id;
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'update_classe';
    
    // Remplir les champs du formulaire
    form.querySelector('input[name="nom"]').value = classe.nom || '';
    form.querySelector('select[name="niveau"]').value = classe.niveau || '';
    form.querySelector('input[name="section"]').value = classe.section || '';
    form.querySelector('input[name="capacite_max"]').value = classe.capacite_max || 40;
    form.querySelector('select[name="annee_scolaire_id"]').value = classe.annee_scolaire_id || '';
    form.querySelector('select[name="enseignant_titulaire_id"]').value = classe.enseignant_titulaire_id || '';
    
    // Changer le titre du modal
    document.querySelector('#modalClasse h2').textContent = 'Modifier la classe';
    
    // Ouvrir le modal
    toggleClasseModal();
}

// Filtrage des classes
function filterClasses() {
    const search = document.getElementById('searchClasse').value.toLowerCase();
    const niveau = document.getElementById('niveauFilter').value.toLowerCase();
    const annee = document.getElementById('anneeFilter').value;
    
    const rows = document.querySelectorAll('#classesTableBody tr[data-niveau]');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const rowNiveau = row.getAttribute('data-niveau');
        const rowAnnee = row.getAttribute('data-annee');
        const rowSearch = row.getAttribute('data-search');
        
        let show = true;
        
        if (search && !rowSearch.includes(search)) {
            show = false;
        }
        
        if (niveau && rowNiveau !== niveau) {
            show = false;
        }
        
        if (annee && rowAnnee !== annee) {
            show = false;
        }
        
        if (show) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Afficher message si aucun résultat
    const tbody = document.getElementById('classesTableBody');
    const emptyRow = tbody.querySelector('tr:not([data-niveau])');
    
    if (visibleCount === 0 && rows.length > 0) {
        if (!emptyRow) {
            tbody.innerHTML += `
                <tr class="no-results">
                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-search text-4xl mb-2"></i>
                        <p>Aucune classe trouvée avec ces critères</p>
                    </td>
                </tr>
            `;
        }
    } else {
        const noResultRow = tbody.querySelector('.no-results');
        if (noResultRow) noResultRow.remove();
    }
}

// Recherche en temps réel
document.getElementById('searchClasse').addEventListener('input', filterClasses);

// Ouvrir modal si mode édition
<?php if ($classe_edit): ?>
    editClasse(<?= htmlspecialchars(json_encode($classe_edit)) ?>);
<?php endif; ?>
</script>
