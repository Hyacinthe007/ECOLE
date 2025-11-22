<?php

declare(strict_types=1);

// --- TRAITEMENT PHP AVANT TOUT AFFICHAGE ---
// Note: session_start() et vérifications déjà faites dans parametres.php
require_once __DIR__ . '/../config.php';

$message = '';
$error = '';

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_enseignant') {
    $enseignant_id = intval($_POST['enseignant_id']);
    $conn->query("DELETE FROM enseignants WHERE id = $enseignant_id");
    header("Location: ../pages/parametres.php?page=enseignants&success=1");
    exit();
}

// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && ($_POST['action'] === 'add_enseignant' || $_POST['action'] === 'update_enseignant')) {

    $id = isset($_POST['enseignant_id']) ? intval($_POST['enseignant_id']) : 0;

    $matricule = trim($_POST['matricule']);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $specialite = trim($_POST['specialite']);
    $date_embauche = $_POST['date_embauche'];
    $telephone = trim($_POST['telephone']);
    $email_pro = trim($_POST['email_pro'] ?? '');
    $diplomes = trim($_POST['diplomes']);
    $statut = $_POST['statut'];
    
    // Validation du téléphone
    if (strlen($telephone) !== 10 || !preg_match('/^03/', $telephone)) {
        $error = "Le numéro de téléphone doit contenir exactement 10 chiffres et commencer par '03'.";
    }

    // ----------------------------------------------------------------
    // 🔵 AUTO-GÉNÉRATION MATRICULE POUR NOUVEL ENSEIGNANT
    // ----------------------------------------------------------------
    if (empty($matricule)) {

        $annee = date('Y');

        // Chercher le dernier matricule de l'année
        $result = $conn->query("
            SELECT matricule 
            FROM enseignants 
            WHERE matricule LIKE 'EN{$annee}%' 
            ORDER BY matricule DESC 
            LIMIT 1
        ");

        if ($result->num_rows > 0) {
            $last = $result->fetch_assoc()['matricule']; // ex: EN2025-0004
            $last_num = intval(substr($last, -4));      // extrait "0004" → 4
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }

        // Format EN2025-0001
        $matricule = "EN{$annee}-" . str_pad($new_num, 4, '0', STR_PAD_LEFT);
    }

    // ----------------------------------------------------------------
    // 🔵 UPDATE ENSEIGNANT
    // ----------------------------------------------------------------
    if ($id > 0) {

        $stmt = $conn->prepare("
            UPDATE enseignants 
            SET matricule=?, nom=?, prenom=?, specialite=?, date_embauche=?, telephone=?, email_pro=?, diplomes=?, statut=?
            WHERE id=?
        ");

        $stmt->bind_param("sssssssssi",
            $matricule, $nom, $prenom, $specialite, $date_embauche,
            $telephone, $email_pro, $diplomes, $statut, $id
        );

    } else {
    // ----------------------------------------------------------------
    // 🔵 INSERT NOUVEL ENSEIGNANT
    // ----------------------------------------------------------------

        $stmt = $conn->prepare("
            INSERT INTO enseignants 
            (matricule, nom, prenom, specialite, date_embauche, telephone, email_pro, diplomes, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("sssssssss",
            $matricule, $nom, $prenom, $specialite, $date_embauche,
            $telephone, $email_pro, $diplomes, $statut
        );
    }

    // ----------------------------------------------------------------
    // 🔵 EXÉCUTION AVEC GESTION DES ERREURS SQL
    // ----------------------------------------------------------------
    // Si pas d'erreur de validation, on continue
    if (empty($error)) {
        try {
            $stmt->execute();
            header("Location: ../pages/parametres.php?page=enseignants&success=1&matricule=" . urlencode($matricule));
            exit();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "Attention : le matricule <strong>$matricule</strong> existe déjà.";
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

// Récupérer les matières pour la liste déroulante
$matieres_list = $conn->query("SELECT id, nom FROM matieres ORDER BY nom");

$enseignants = $conn->query("SELECT * FROM enseignants ORDER BY matricule");

$enseignant_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM enseignants WHERE id = $id");
    $enseignant_edit = $res->fetch_assoc();
}

?>
<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
        <i class="fas fa-check-circle mr-2"></i> Opération réussie !
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
        <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">
            <i class="fas fa-chalkboard-teacher text-blue-600 mr-2"></i>
            Gestion des Enseignants
        </h2>
        <p class="text-gray-600">Liste et gestion des enseignants de l'école</p>
    </div>
    <button onclick="addEnseignant()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Ajouter un enseignant
    </button>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom & Prénom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spécialité</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="enseignantsTableBody">
                <?php if ($enseignants->num_rows > 0): ?>
                    <?php while($ens = $enseignants->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($ens['matricule']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($ens['specialite']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($ens['telephone']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($ens['email_pro']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($ens['statut'] == 'actif'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Actif</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="editEnseignant(<?= htmlspecialchars(json_encode($ens)) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet enseignant ?')">
                                    <input type="hidden" name="action" value="delete_enseignant">
                                    <input type="hidden" name="enseignant_id" value="<?= $ens['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p>Aucun enseignant trouvé</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout/Modification Enseignant -->
<div id="modalEnseignant" class="<?= $enseignant_edit ? '' : 'hidden' ?> fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <?= $enseignant_edit ? 'Modifier l\'enseignant' : 'Ajouter un enseignant' ?>
                </h2>
                <button onclick="toggleEnseignantModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?= $enseignant_edit ? 'update_enseignant' : 'add_enseignant' ?>">
                <?php if ($enseignant_edit): ?>
                    <input type="hidden" name="enseignant_id" value="<?= $enseignant_edit['id'] ?>">
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Matricule *</label>
                        <input type="text" name="matricule" required value="<?= $enseignant_edit ? htmlspecialchars($enseignant_edit['matricule']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                        <input type="text" name="nom" required value="<?= $enseignant_edit ? htmlspecialchars($enseignant_edit['nom']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                        <input type="text" name="prenom" required value="<?= $enseignant_edit ? htmlspecialchars($enseignant_edit['prenom']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Spécialité *</label>
                        <select name="specialite" required id="specialite" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Sélectionner une matière --</option>
                            <?php 
                            if ($matieres_list->num_rows > 0) {
                                // Réinitialiser le pointeur de résultat
                                $matieres_list->data_seek(0);
                                while($matiere = $matieres_list->fetch_assoc()): 
                                    $selected = ($enseignant_edit && $enseignant_edit['specialite'] == $matiere['nom']) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($matiere['nom']) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($matiere['nom']) ?>
                                </option>
                            <?php 
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                        <input type="tel" name="telephone" id="telephone" required 
                               value="<?= $enseignant_edit ? htmlspecialchars($enseignant_edit['telephone']) : '' ?>" 
                               placeholder="0341234567"
                               pattern="03[0-9]{8}"
                               maxlength="10"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email professionnel</label>
                        <input type="email" name="email_pro" value="<?= $enseignant_edit ? htmlspecialchars($enseignant_edit['email_pro']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Diplômes</label>
                        <input type="text" name="diplomes" value="<?= $enseignant_edit ? htmlspecialchars($enseignant_edit['diplomes']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date d'embauche *</label>
                        <input type="date" name="date_embauche" required value="<?= $enseignant_edit ? $enseignant_edit['date_embauche'] : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut *</label>
                        <select name="statut" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="actif" <?= ($enseignant_edit && $enseignant_edit['statut'] == 'actif') ? 'selected' : '' ?>>Actif</option>
                            <option value="inactif" <?= ($enseignant_edit && $enseignant_edit['statut'] == 'inactif') ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="button" onclick="toggleEnseignantModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>
                        <?= $enseignant_edit ? 'Modifier' : 'Ajouter' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleEnseignantModal() {
    const modal = document.getElementById('modalEnseignant');
    modal.classList.toggle('hidden');
    if (!modal.classList.contains('hidden')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
}

function addEnseignant() {
    const form = document.querySelector('#modalEnseignant form');
    
    // Supprimer le champ enseignant_id si présent
    const idInput = form.querySelector('input[name="enseignant_id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'add_enseignant';
    
    // Réinitialiser le formulaire
    form.reset();
    
    // Changer le titre du modal
    document.querySelector('#modalEnseignant h2').textContent = 'Ajouter un enseignant';
    
    // Ouvrir le modal
    toggleEnseignantModal();
}

function editEnseignant(enseignant) {
    const form = document.querySelector('#modalEnseignant form');
    
    // Ajouter ou mettre à jour le champ hidden enseignant_id
    let idInput = form.querySelector('input[name="enseignant_id"]');
    if (!idInput) {
        idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'enseignant_id';
        form.insertBefore(idInput, form.querySelector('input[name="action"]').nextSibling);
    }
    idInput.value = enseignant.id;
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'update_enseignant';
    
    // Remplir les champs du formulaire
    form.querySelector('input[name="matricule"]').value = enseignant.matricule || '';
    form.querySelector('input[name="nom"]').value = enseignant.nom || '';
    form.querySelector('input[name="prenom"]').value = enseignant.prenom || '';
    form.querySelector('select[name="specialite"]').value = enseignant.specialite || '';
    form.querySelector('input[name="telephone"]').value = enseignant.telephone || '';
    form.querySelector('input[name="email_pro"]').value = enseignant.email_pro || '';
    form.querySelector('input[name="diplomes"]').value = enseignant.diplomes || '';
    form.querySelector('input[name="date_embauche"]').value = enseignant.date_embauche || '';
    form.querySelector('select[name="statut"]').value = enseignant.statut || 'actif';
    
    // Changer le titre du modal
    document.querySelector('#modalEnseignant h2').textContent = 'Modifier l\'enseignant';
    
    // Ouvrir le modal
    toggleEnseignantModal();
}

// Validation du téléphone
document.addEventListener('DOMContentLoaded', function() {
    const telephoneInput = document.getElementById('telephone');
    if (telephoneInput) {
        // Limiter à 10 caractères
        telephoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });
        
        // Validation au submit
        const form = telephoneInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const tel = telephoneInput.value.trim();
                if (tel.length !== 10 || !tel.startsWith('03')) {
                    e.preventDefault();
                    alert('Le numéro de téléphone doit contenir exactement 10 chiffres et commencer par "03".');
                    telephoneInput.focus();
                    return false;
                }
            });
        }
    }
});

<?php if ($enseignant_edit): ?>
    editEnseignant(<?= htmlspecialchars(json_encode($enseignant_edit)) ?>);
<?php endif; ?>
</script>
