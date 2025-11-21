<?php

declare(strict_types=1);

// --- TRAITEMENT PHP AVANT TOUT AFFICHAGE ---
// Note: session_start() et vérifications déjà faites dans parametres.php
require_once __DIR__ . '/../config.php';

$message = '';
$error = '';

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_matiere') {
    $matiere_id = intval($_POST['matiere_id']);
    $conn->query("DELETE FROM matieres WHERE id = $matiere_id");
    header("Location: ../pages/parametres.php?page=matieres&success=1");
    exit();
}


// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_matiere') {
        $nom = trim($_POST['nom']);
        $code = trim($_POST['code']);
        $coefficient = floatval($_POST['coefficient']);
        $niveau = $_POST['niveau'];
        $description = trim($_POST['description']);
        $stmt = $conn->prepare("INSERT INTO matieres (nom, code, coefficient, niveau, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $nom, $code, $coefficient, $niveau, $description);
        if ($stmt->execute()) {
            header("Location: ../pages/parametres.php?page=matieres&success=1");
            exit();
        } else {
            if ($stmt->errno == 1062) {
                $error = "Le code ou le nom de la matière existe déjà.";
            } else {
                $error = "Erreur lors de l'enregistrement : " . htmlspecialchars($stmt->error);
            }
        }
    } elseif ($_POST['action'] === 'update_matiere') {
        $id = intval($_POST['matiere_id']);
        $nom = trim($_POST['nom']);
        $code = trim($_POST['code']);
        $coefficient = floatval($_POST['coefficient']);
        $niveau = $_POST['niveau'];
        $description = trim($_POST['description']);
        $stmt = $conn->prepare("UPDATE matieres SET nom=?, code=?, coefficient=?, niveau=?, description=? WHERE id=?");
        $stmt->bind_param("ssdssi", $nom, $code, $coefficient, $niveau, $description, $id);
        if ($stmt->execute()) {
            header("Location: ../pages/parametres.php?page=matieres&success=1");
            exit();
        } else {
            if ($stmt->errno == 1062) {
                $error = "Le code ou le nom de la matière existe déjà.";
            } else {
                $error = "Erreur lors de la modification : " . htmlspecialchars($stmt->error);
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

$matieres = $conn->query("SELECT * FROM matieres ORDER BY niveau, nom");

// Pour édition
$matiere_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM matieres WHERE id = $id");
    $matiere_edit = $res->fetch_assoc();
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
            <i class="fas fa-book text-blue-600 mr-2"></i>
            Gestion des Matières
        </h2>
        <p class="text-gray-600">Liste et gestion des matières de l'école</p>
    </div>
    <button onclick="toggleMatiereModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Ajouter une matière
    </button>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coefficient</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Niveau</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="matieresTableBody">
                <?php if ($matieres->num_rows > 0): ?>
                    <?php while($matiere = $matieres->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($matiere['nom']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($matiere['code']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($matiere['coefficient']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= ucfirst($matiere['niveau']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($matiere['description']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="?page=<?= $current_page ?? 'matieres' ?>&edit=<?= $matiere['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette matière ?')">
                                    <input type="hidden" name="action" value="delete_matiere">
                                    <input type="hidden" name="matiere_id" value="<?= $matiere['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p>Aucune matière trouvée</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajout/Modification Matière -->
<div id="modalMatiere" class="<?= $matiere_edit ? '' : 'hidden' ?> fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800"><?= $matiere_edit ? 'Modifier la matière' : 'Ajouter une matière' ?></h2>
                <button onclick="toggleMatiereModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?= $matiere_edit ? 'update_matiere' : 'add_matiere' ?>">
                <?php if ($matiere_edit): ?>
                    <input type="hidden" name="matiere_id" value="<?= $matiere_edit['id'] ?>">
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la matière *</label>
                        <input type="text" name="nom" required placeholder="Ex: Mathématiques" value="<?= $matiere_edit ? htmlspecialchars($matiere_edit['nom']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Code *</label>
                        <input type="text" name="code" required placeholder="Ex: MATH" value="<?= $matiere_edit ? htmlspecialchars($matiere_edit['code']) : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Coefficient *</label>
                        <input type="number" step="0.1" name="coefficient" required value="<?= $matiere_edit ? $matiere_edit['coefficient'] : '1' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Niveau *</label>
                        <select name="niveau" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="maternelle" <?= ($matiere_edit && $matiere_edit['niveau'] == 'maternelle') ? 'selected' : '' ?>>Maternelle</option>
                            <option value="primaire" <?= ($matiere_edit && $matiere_edit['niveau'] == 'primaire') ? 'selected' : '' ?>>Primaire</option>
                            <option value="college" <?= ($matiere_edit && $matiere_edit['niveau'] == 'college') ? 'selected' : '' ?>>Collège</option>
                            <option value="lycee" <?= ($matiere_edit && $matiere_edit['niveau'] == 'lycee') ? 'selected' : '' ?>>Lycée</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= $matiere_edit ? htmlspecialchars($matiere_edit['description']) : '' ?></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="button" onclick="toggleMatiereModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i> <?= $matiere_edit ? 'Modifier' : 'Ajouter' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleMatiereModal() {
    const modal = document.getElementById('modalMatiere');
    modal.classList.toggle('hidden');
    if (!modal.classList.contains('hidden')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
}
// Ouvre le modal si édition
<?php if ($matiere_edit): ?>
    toggleMatiereModal();
<?php endif; ?>
</script>
