<?php

declare(strict_types=1);

// --- TRAITEMENT PHP AVANT TOUT AFFICHAGE ---
// Note: session_start() et vérifications déjà faites dans parametres.php
require_once __DIR__ . '/../config.php';

$message = '';
$error = '';

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_annee') {
    $annee_id = intval($_POST['annee_id']);
    $conn->query("DELETE FROM annees_scolaires WHERE id = $annee_id");
    header("Location: ../pages/parametres.php?page=annees&success=1");
    exit();
}

// Activation d'une année scolaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activer_annee') {
    $annee_id = intval($_POST['annee_id']);
    
    // Désactiver toutes les années
    $conn->query("UPDATE annees_scolaires SET actif = 0");
    
    // Activer l'année sélectionnée
    $conn->query("UPDATE annees_scolaires SET actif = 1 WHERE id = $annee_id");
    
    header("Location: ../pages/parametres.php?page=annees&success=1");
    exit();
}

// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && ($_POST['action'] === 'add_annee' || $_POST['action'] === 'update_annee')) {

    $id = isset($_POST['annee_id']) ? intval($_POST['annee_id']) : 0;
    $libelle = trim($_POST['libelle']);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $actif = isset($_POST['actif']) ? 1 : 0;

    // Validation
    if (empty($libelle)) {
        $error = "Le libellé est obligatoire.";
    } elseif (empty($date_debut) || empty($date_fin)) {
        $error = "Les dates de début et de fin sont obligatoires.";
    } elseif (strtotime($date_fin) < strtotime($date_debut)) {
        $error = "La date de fin doit être postérieure à la date de début.";
    }

    // Si pas d'erreur de validation, on continue
    if (empty($error)) {
        if ($id > 0) {
            // UPDATE - Si on active cette année, désactiver les autres
            if ($actif == 1) {
                $conn->query("UPDATE annees_scolaires SET actif = 0");
            }
            $stmt = $conn->prepare("UPDATE annees_scolaires SET libelle=?, date_debut=?, date_fin=?, actif=? WHERE id=?");
            $stmt->bind_param("sssii", $libelle, $date_debut, $date_fin, $actif, $id);
        } else {
            // INSERT - Nouvelle année scolaire (actif = 1 par défaut)
            // Si on active cette nouvelle année, désactiver les autres
            if ($actif == 1) {
                $conn->query("UPDATE annees_scolaires SET actif = 0");
            }
            $stmt = $conn->prepare("INSERT INTO annees_scolaires (libelle, date_debut, date_fin, actif) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("sss", $libelle, $date_debut, $date_fin);
        }

        try {
            $stmt->execute();
            header("Location: ../pages/parametres.php?page=annees&success=1");
            exit();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "Une année scolaire avec ce libellé existe déjà.";
            } else {
                $error = "Erreur SQL : " . $e->getMessage();
            }
        }
    }
}

// Si on est en mode traitement POST uniquement, on s'arrête ici
if (isset($processingPostOnly) && $processingPostOnly) {
    return;
}

// --- AFFICHAGE HTML ET INCLUDES APRES TOUT TRAITEMENT ---
// Note: nav.php est déjà inclus dans parametres.php, pas besoin de le réinclure

$annees = $conn->query("SELECT * FROM annees_scolaires ORDER BY date_debut DESC");

// Année à modifier
$annee_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM annees_scolaires WHERE id = $id");
    $annee_edit = $res->fetch_assoc();
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
            <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
            Gestion des Années Scolaires
        </h2>
        <p class="text-gray-600">Liste et gestion des années scolaires</p>
    </div>
    <button onclick="addAnnee()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Ajouter une année scolaire
    </button>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Libellé</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date de début</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date de fin</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="anneesTableBody">
                <?php if ($annees->num_rows > 0): ?>
                    <?php while($annee = $annees->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($annee['libelle']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y', strtotime($annee['date_debut'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y', strtotime($annee['date_fin'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($annee['actif']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if (!$annee['actif']): ?>
                                    <form method="POST" class="inline mr-3" onsubmit="return confirm('Activer cette année scolaire ?')">
                                        <input type="hidden" name="action" value="activer_annee">
                                        <input type="hidden" name="annee_id" value="<?= $annee['id'] ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-900" title="Activer">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button onclick="editAnnee(<?= htmlspecialchars(json_encode($annee)) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette année scolaire ?')">
                                    <input type="hidden" name="action" value="delete_annee">
                                    <input type="hidden" name="annee_id" value="<?= $annee['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p>Aucune année scolaire enregistrée</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout/Modification Année Scolaire -->
<div id="modalAnnee" class="<?= $annee_edit ? '' : 'hidden' ?> fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <?= $annee_edit ? 'Modifier l\'année scolaire' : 'Ajouter une année scolaire' ?>
                </h2>
                <button onclick="toggleAnneeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?= $annee_edit ? 'update_annee' : 'add_annee' ?>">
                <?php if ($annee_edit): ?>
                    <input type="hidden" name="annee_id" value="<?= $annee_edit['id'] ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Libellé *</label>
                        <input type="text" name="libelle" required 
                               value="<?= $annee_edit ? htmlspecialchars($annee_edit['libelle']) : '' ?>"
                               placeholder="Ex: 2024-2025"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Format recommandé : AAAA-AAAA (ex: 2024-2025)
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date de début *</label>
                        <input type="date" name="date_debut" required
                               value="<?= $annee_edit ? $annee_edit['date_debut'] : '' ?>"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date de fin *</label>
                        <input type="date" name="date_fin" required
                               value="<?= $annee_edit ? $annee_edit['date_fin'] : '' ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <?php if ($annee_edit): ?>
                        <div class="md:col-span-2">
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="checkbox" name="actif" value="1" 
                                       <?= $annee_edit['actif'] ? 'checked' : '' ?>
                                       class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700">
                                    Activer cette année scolaire
                                </span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1 ml-8">
                                <i class="fas fa-info-circle mr-1"></i>
                                Si activée, cette année deviendra l'année active et les autres seront désactivées automatiquement.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="md:col-span-2 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                                <div>
                                    <p class="font-medium text-blue-800">Année active par défaut</p>
                                    <p class="text-sm text-blue-700">La nouvelle année scolaire sera automatiquement activée. Les autres années seront désactivées.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="button" onclick="toggleAnneeModal()" 
                            class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>
                        <?= $annee_edit ? 'Modifier' : 'Ajouter' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Gestion du modal Année Scolaire
function toggleAnneeModal() {
    const modal = document.getElementById('modalAnnee');
    modal.classList.toggle('hidden');
    if (!modal.classList.contains('hidden')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
}

function addAnnee() {
    const form = document.querySelector('#modalAnnee form');
    
    // Supprimer le champ annee_id si présent
    const idInput = form.querySelector('input[name="annee_id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'add_annee';
    
    // Réinitialiser le formulaire
    form.reset();
    
    // Changer le titre du modal
    document.querySelector('#modalAnnee h2').textContent = 'Ajouter une année scolaire';
    
    // Ouvrir le modal
    toggleAnneeModal();
}

function editAnnee(annee) {
    const form = document.querySelector('#modalAnnee form');
    
    // Ajouter ou mettre à jour le champ hidden annee_id
    let idInput = form.querySelector('input[name="annee_id"]');
    if (!idInput) {
        idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'annee_id';
        form.insertBefore(idInput, form.querySelector('input[name="action"]').nextSibling);
    }
    idInput.value = annee.id;
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'update_annee';
    
    // Remplir les champs du formulaire
    form.querySelector('input[name="libelle"]').value = annee.libelle || '';
    form.querySelector('input[name="date_debut"]').value = annee.date_debut || '';
    form.querySelector('input[name="date_fin"]').value = annee.date_fin || '';
    
    // Cocher la checkbox actif si l'année est active
    const actifCheckbox = form.querySelector('input[name="actif"]');
    if (actifCheckbox) {
        actifCheckbox.checked = annee.actif == 1;
    }
    
    // Changer le titre du modal
    document.querySelector('#modalAnnee h2').textContent = 'Modifier l\'année scolaire';
    
    // Ouvrir le modal
    toggleAnneeModal();
}

// Ouvrir modal si mode édition
<?php if ($annee_edit): ?>
    editAnnee(<?= htmlspecialchars(json_encode($annee_edit)) ?>);
<?php endif; ?>
</script>
