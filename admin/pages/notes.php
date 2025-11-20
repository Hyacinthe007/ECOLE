<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

// SUPPRESSION
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM notes WHERE id = $id");
    header("Location: notes.php?success=1");
    exit();
}

// AJOUT/MODIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $eleve_id = intval($_POST['eleve_id']);
    $matiere_id = intval($_POST['matiere_id']);
    $enseignant_id = $_SESSION['user_id']; // L'admin qui saisit
    $type_evaluation = $_POST['type_evaluation'];
    $note = floatval($_POST['note']);
    $note_max = floatval($_POST['note_max']);
    $coefficient = floatval($_POST['coefficient']);
    $date_evaluation = $_POST['date_evaluation'];
    $trimestre = intval($_POST['trimestre']);
    $commentaire = trim($_POST['commentaire']);
    
    // Année scolaire active
    $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
    $annee = $annee_result->fetch_assoc();
    $annee_scolaire_id = $annee['id'];
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE notes SET eleve_id=?, matiere_id=?, enseignant_id=?, type_evaluation=?, note=?, note_max=?, coefficient=?, date_evaluation=?, trimestre=?, commentaire=? WHERE id=?");
        $stmt->bind_param("iiisdddsisi", $eleve_id, $matiere_id, $enseignant_id, $type_evaluation, $note, $note_max, $coefficient, $date_evaluation, $trimestre, $commentaire, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO notes (eleve_id, matiere_id, enseignant_id, type_evaluation, note, note_max, coefficient, date_evaluation, trimestre, annee_scolaire_id, commentaire) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisdddsiss", $eleve_id, $matiere_id, $enseignant_id, $type_evaluation, $note, $note_max, $coefficient, $date_evaluation, $trimestre, $annee_scolaire_id, $commentaire);
    }
    
    $stmt->execute();
    header("Location: notes.php?success=1");
    exit();
}

// Filtres
$classe_filter = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$matiere_filter = isset($_GET['matiere']) ? intval($_GET['matiere']) : 0;
$trimestre_filter = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 0;

$sql = "SELECT n.*, e.nom as eleve_nom, e.prenom as eleve_prenom, e.matricule,
               m.nom as matiere_nom, m.code as matiere_code, c.nom as classe_nom
        FROM notes n
        JOIN eleves e ON n.eleve_id = e.id
        JOIN matieres m ON n.matiere_id = m.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'active'
        LEFT JOIN classes c ON i.classe_id = c.id
        WHERE 1=1";

if ($classe_filter > 0) {
    $sql .= " AND i.classe_id = $classe_filter";
}
if ($matiere_filter > 0) {
    $sql .= " AND n.matiere_id = $matiere_filter";
}
if ($trimestre_filter > 0) {
    $sql .= " AND n.trimestre = $trimestre_filter";
}

$sql .= " ORDER BY n.date_evaluation DESC, e.nom";
$notes = $conn->query($sql);

// Listes pour les filtres et formulaires
$classes = $conn->query("SELECT * FROM classes ORDER BY nom");
$matieres = $conn->query("SELECT * FROM matieres ORDER BY nom");
$eleves = $conn->query("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'active' LEFT JOIN classes c ON i.classe_id = c.id WHERE e.statut = 'actif' ORDER BY e.nom");

// Note à modifier
$note_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM notes WHERE id = $id");
    $note_edit = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <title>Gestion des Notes | École Mandroso</title>
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    
    <?php include('../includes/nav.php'); ?>
    
    <div class="flex">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="main-content flex-1 p-4 md:p-8 mt-16">
            
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <i class="fas fa-check-circle mr-2"></i> Opération réussie !
                </div>
            <?php endif; ?>
            
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                        Gestion des Notes
                    </h1>
                    <p class="text-gray-600">Saisie et consultation des notes</p>
                </div>
                <button onclick="toggleModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i> Ajouter une note
                </button>
            </div>
            
            <!-- Filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Classe</label>
                        <select name="classe" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Toutes les classes</option>
                            <?php 
                            $classes_filter = $conn->query("SELECT * FROM classes ORDER BY nom");
                            while($classe = $classes_filter->fetch_assoc()): 
                            ?>
                                <option value="<?= $classe['id'] ?>" <?= $classe_filter == $classe['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classe['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Matière</label>
                        <select name="matiere" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Toutes les matières</option>
                            <?php 
                            $matieres_filter = $conn->query("SELECT * FROM matieres ORDER BY nom");
                            while($matiere = $matieres_filter->fetch_assoc()): 
                            ?>
                                <option value="<?= $matiere['id'] ?>" <?= $matiere_filter == $matiere['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($matiere['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Trimestre</label>
                        <select name="trimestre" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous</option>
                            <option value="1" <?= $trimestre_filter == 1 ? 'selected' : '' ?>>Trimestre 1</option>
                            <option value="2" <?= $trimestre_filter == 2 ? 'selected' : '' ?>>Trimestre 2</option>
                            <option value="3" <?= $trimestre_filter == 3 ? 'selected' : '' ?>>Trimestre 3</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Liste des notes -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Élève</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Classe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matière</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trimestre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if ($notes->num_rows > 0): ?>
                                <?php while($note = $notes->fetch_assoc()): ?>
                                    <?php 
                                    $pourcentage = ($note['note'] / $note['note_max']) * 100;
                                    $color = $pourcentage >= 50 ? 'text-green-600' : 'text-red-600';
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($note['eleve_nom'] . ' ' . $note['eleve_prenom']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?= $note['matricule'] ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($note['classe_nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($note['matiere_nom']) ?></div>
                                            <div class="text-sm text-gray-500">Coef: <?= $note['coefficient'] ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                <?= ucfirst($note['type_evaluation']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-lg font-bold <?= $color ?>">
                                                <?= number_format($note['note'], 2) ?> / <?= number_format($note['note_max'], 2) ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?= number_format($pourcentage, 1) ?>%</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($note['date_evaluation'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                                T<?= $note['trimestre'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <a href="?edit=<?= $note['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?= $note['id'] ?>" onclick="return confirm('Supprimer cette note ?')" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2"></i>
                                        <p>Aucune note trouvée</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?= $note_edit ? 'Modifier la note' : 'Ajouter une note' ?>
                    </h2>
                    <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <?php if ($note_edit): ?>
                        <input type="hidden" name="id" value="<?= $note_edit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Élève *</label>
                            <select name="eleve_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Sélectionner un élève</option>
                                <?php 
                                $eleves_form = $conn->query("SELECT e.*, c.nom as classe_nom FROM eleves e LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'active' LEFT JOIN classes c ON i.classe_id = c.id WHERE e.statut = 'actif' ORDER BY e.nom");
                                while($eleve = $eleves_form->fetch_assoc()): 
                                ?>
                                    <option value="<?= $eleve['id'] ?>" <?= ($note_edit && $note_edit['eleve_id'] == $eleve['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']) ?> - <?= $eleve['classe_nom'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Matière *</label>
                            <select name="matiere_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Sélectionner une matière</option>
                                <?php 
                                $matieres_form = $conn->query("SELECT * FROM matieres ORDER BY nom");
                                while($matiere = $matieres_form->fetch_assoc()): 
                                ?>
                                    <option value="<?= $matiere['id'] ?>" <?= ($note_edit && $note_edit['matiere_id'] == $matiere['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($matiere['nom']) ?> (Coef: <?= $matiere['coefficient'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type d'évaluation *</label>
                            <select name="type_evaluation" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="devoir" <?= ($note_edit && $note_edit['type_evaluation'] == 'devoir') ? 'selected' : '' ?>>Devoir</option>
                                <option value="controle" <?= ($note_edit && $note_edit['type_evaluation'] == 'controle') ? 'selected' : '' ?>>Contrôle</option>
                                <option value="composition" <?= ($note_edit && $note_edit['type_evaluation'] == 'composition') ? 'selected' : '' ?>>Composition</option>
                                <option value="examen" <?= ($note_edit && $note_edit['type_evaluation'] == 'examen') ? 'selected' : '' ?>>Examen</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Trimestre *</label>
                            <select name="trimestre" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="1" <?= ($note_edit && $note_edit['trimestre'] == 1) ? 'selected' : '' ?>>Trimestre 1</option>
                                <option value="2" <?= ($note_edit && $note_edit['trimestre'] == 2) ? 'selected' : '' ?>>Trimestre 2</option>
                                <option value="3" <?= ($note_edit && $note_edit['trimestre'] == 3) ? 'selected' : '' ?>>Trimestre 3</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Note obtenue *</label>
                            <input type="number" step="0.01" name="note" required
                                   value="<?= $note_edit ? $note_edit['note'] : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Note maximale *</label>
                            <input type="number" step="0.01" name="note_max" required
                                   value="<?= $note_edit ? $note_edit['note_max'] : '20' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Coefficient *</label>
                            <input type="number" step="0.1" name="coefficient" required
                                   value="<?= $note_edit ? $note_edit['coefficient'] : '1' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date d'évaluation *</label>
                            <input type="date" name="date_evaluation" required
                                   value="<?= $note_edit ? $note_edit['date_evaluation'] : date('Y-m-d') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Commentaire</label>
                            <textarea name="commentaire" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= $note_edit ? htmlspecialchars($note_edit['commentaire']) : '' ?></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <button type="button" onclick="toggleModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>
                            <?= $note_edit ? 'Modifier' : 'Ajouter' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleModal() {
            document.getElementById('modal').classList.toggle('hidden');
        }
        <?php if ($note_edit): ?>
            toggleModal();
        <?php endif; ?>
    </script>
    
</body>
</html>