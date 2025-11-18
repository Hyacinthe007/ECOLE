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
    $conn->query("DELETE FROM classes WHERE id = $id");
    header("Location: classes.php?success=1");
    exit();
}

// AJOUT / MODIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nom = trim($_POST['nom']);
    $niveau = $_POST['niveau'];
    $section = trim($_POST['section']);
    $capacite = intval($_POST['capacite_max']);
    $annee_id = intval($_POST['annee_scolaire_id']);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE classes SET nom=?, niveau=?, section=?, capacite_max=?, annee_scolaire_id=? WHERE id=?");
        $stmt->bind_param("sssiii", $nom, $niveau, $section, $capacite, $annee_id, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO classes (nom, niveau, section, capacite_max, annee_scolaire_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $nom, $niveau, $section, $capacite, $annee_id);
    }

    $stmt->execute();
    header("Location: classes.php?success=1");
    exit();
}

// Lister toutes les classes
$classes = $conn->query("
    SELECT c.*, a.libelle AS annee_libelle 
    FROM classes c 
    JOIN annees_scolaires a ON c.annee_scolaire_id = a.id
    ORDER BY niveau, nom, section
");

// Liste des années pour le formulaire
$annees = $conn->query("SELECT id, libelle FROM annees_scolaires ORDER BY date_debut DESC");

// Classe à modifier
$classe_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM classes WHERE id = $id");
    $classe_edit = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Paramètres - Classes | École Mandroso</title>
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
            <i class="fas fa-school text-blue-600 mr-2"></i> Gestion des Classes
          </h1>
          <p class="text-gray-600">Ajout, modification et suppression des classes et sections</p>
        </div>
        <button onclick="toggleModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
          <i class="fas fa-plus mr-2"></i> Ajouter une classe
        </button>
      </div>

      <!-- Liste -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Niveau</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Section</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacité</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Année scolaire</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php if ($classes->num_rows > 0): ?>
                <?php while($cls = $classes->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4"><?= htmlspecialchars($cls['nom']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars(ucfirst($cls['niveau'])) ?></td>
                    <td class="px-6 py-4 text-center"><?= htmlspecialchars($cls['section']) ?></td>
                    <td class="px-6 py-4 text-center"><?= htmlspecialchars($cls['capacite_max']) ?></td>
                    <td class="px-6 py-4 text-center"><?= htmlspecialchars($cls['annee_libelle']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <a href="?edit=<?= $cls['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="?delete=<?= $cls['id'] ?>" onclick="return confirm('Supprimer cette classe ?')" class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Aucune classe enregistrée</p>
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
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
      <div class="p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">
            <?= $classe_edit ? 'Modifier la classe' : 'Ajouter une classe' ?>
          </h2>
          <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-2xl"></i>
          </button>
        </div>

        <form method="POST" class="space-y-4">
          <?php if ($classe_edit): ?>
            <input type="hidden" name="id" value="<?= $classe_edit['id'] ?>">
          <?php endif; ?>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
            <input type="text" name="nom" required value="<?= $classe_edit ? $classe_edit['nom'] : '' ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Niveau *</label>
            <select name="niveau" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="maternelle">Maternelle</option>
              <option value="primaire">Primaire</option>
              <option value="college">Collège</option>
              <option value="lycee">Lycée</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Section</label>
            <input type="text" name="section" value="<?= $classe_edit ? $classe_edit['section'] : '' ?>" placeholder="Ex: A, D, 1, 2..." class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Capacité maximale</label>
            <input type="number" name="capacite_max" value="<?= $classe_edit ? $classe_edit['capacite_max'] : 40 ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Année scolaire</label>
            <select name="annee_scolaire_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
              <?php while($a = $annees->fetch_assoc()): ?>
                <option value="<?= $a['id'] ?>" <?= $classe_edit && $classe_edit['annee_scolaire_id'] == $a['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($a['libelle']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="flex justify-end space-x-4 pt-6 border-t">
            <button type="button" onclick="toggleModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              <i class="fas fa-save mr-2"></i> <?= $classe_edit ? 'Modifier' : 'Ajouter' ?>
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
    <?php if ($classe_edit): ?> toggleModal(); <?php endif; ?>
  </script>

</body>
</html>
