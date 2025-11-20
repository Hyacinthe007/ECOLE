<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

// --- Filtres ---
$classe_filter = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$enseignant_filter = isset($_GET['enseignant']) ? intval($_GET['enseignant']) : 0;

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM emploi_temps WHERE id = $id");
    header("Location: emploi-temps.php?success=1");
    exit();
}

// --- Ajout / modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $classe_id = intval($_POST['classe_id']);
    $matiere_id = intval($_POST['matiere_id']);
    $enseignant_id = intval($_POST['enseignant_id']);
    $jour = $_POST['jour_semaine'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $salle = trim($_POST['salle']);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE emploi_temps SET classe_id=?, matiere_id=?, enseignant_id=?, jour_semaine=?, heure_debut=?, heure_fin=?, salle=? WHERE id=?");
        $stmt->bind_param("iiissssi", $classe_id, $matiere_id, $enseignant_id, $jour, $heure_debut, $heure_fin, $salle, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO emploi_temps (classe_id, matiere_id, enseignant_id, jour_semaine, heure_debut, heure_fin, salle)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $classe_id, $matiere_id, $enseignant_id, $jour, $heure_debut, $heure_fin, $salle);
    }
    $stmt->execute();
    header("Location: emploi-temps.php?success=1");
    exit();
}

// --- Récupération des listes ---
$classes = $conn->query("SELECT * FROM classes ORDER BY nom");
$enseignants = $conn->query("SELECT * FROM enseignants ORDER BY nom");
$matieres = $conn->query("SELECT * FROM matieres ORDER BY nom");

// --- Requête principale ---
$sql = "SELECT e.*, c.nom AS classe_nom, m.nom AS matiere_nom, ens.nom AS enseignant_nom, ens.prenom AS enseignant_prenom
        FROM emploi_temps e
        JOIN classes c ON e.classe_id = c.id
        JOIN matieres m ON e.matiere_id = m.id
        JOIN enseignants ens ON e.enseignant_id = ens.id
        WHERE 1=1";

if ($classe_filter > 0) $sql .= " AND e.classe_id = $classe_filter";
if ($enseignant_filter > 0) $sql .= " AND e.enseignant_id = $enseignant_filter";

$sql .= " ORDER BY FIELD(e.jour_semaine,'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), e.heure_debut";
$emplois = $conn->query($sql);

// --- Pour modification ---
$edit_item = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $edit_item = $conn->query("SELECT * FROM emploi_temps WHERE id = $id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Emploi du Temps | École Mandroso</title>
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
        <i class="fas fa-check-circle mr-2"></i> Opération réussie.
      </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
          <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
          Gestion des Emplois du Temps
        </h1>
        <p class="text-gray-600">Ajout et affichage des horaires de cours</p>
      </div>
      <button onclick="toggleModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Ajouter un créneau
      </button>
    </div>

    <!-- Filtres -->
    <div class="bg-white p-4 rounded-xl shadow mb-6">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Classe</label>
          <select name="classe" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <option value="">Toutes</option>
            <?php 
            $classes_f = $conn->query("SELECT * FROM classes ORDER BY nom");
            while($c = $classes_f->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>" <?= $classe_filter == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nom']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Enseignant</label>
          <select name="enseignant" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <option value="">Tous</option>
            <?php 
            $ens_f = $conn->query("SELECT * FROM enseignants ORDER BY nom");
            while($ens = $ens_f->fetch_assoc()): ?>
              <option value="<?= $ens['id'] ?>" <?= $enseignant_filter == $ens['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ens['nom'].' '.$ens['prenom']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="flex items-end">
          <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-filter mr-2"></i> Filtrer
          </button>
        </div>
      </form>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Classe</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Jour</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Heure</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Matière</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Enseignant</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Salle</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if ($emplois->num_rows > 0): ?>
            <?php while($e = $emplois->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3"><?= htmlspecialchars($e['classe_nom']) ?></td>
              <td class="px-4 py-3"><?= $e['jour_semaine'] ?></td>
              <td class="px-4 py-3"><?= $e['heure_debut'] ?> - <?= $e['heure_fin'] ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($e['matiere_nom']) ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($e['enseignant_nom'].' '.$e['enseignant_prenom']) ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($e['salle']) ?></td>
              <td class="px-4 py-3">
                <a href="?edit=<?= $e['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3"><i class="fas fa-edit"></i></a>
                <a href="?delete=<?= $e['id'] ?>" onclick="return confirm('Supprimer ce créneau ?')" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center text-gray-500 py-6">Aucun emploi du temps trouvé.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Modal -->
<div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full p-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-gray-800"><?= $edit_item ? 'Modifier un créneau' : 'Ajouter un créneau' ?></h2>
      <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
        <i class="fas fa-times text-2xl"></i>
      </button>
    </div>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php if ($edit_item): ?><input type="hidden" name="id" value="<?= $edit_item['id'] ?>"><?php endif; ?>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Classe *</label>
        <select name="classe_id" required class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <?php
          $cls_q = $conn->query("SELECT * FROM classes ORDER BY nom");
          while($cls = $cls_q->fetch_assoc()):
          ?>
          <option value="<?= $cls['id'] ?>" <?= ($edit_item && $edit_item['classe_id'] == $cls['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cls['nom']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Matière *</label>
        <select name="matiere_id" required class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <?php while($m = $matieres->fetch_assoc()): ?>
          <option value="<?= $m['id'] ?>" <?= ($edit_item && $edit_item['matiere_id'] == $m['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['nom']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Enseignant *</label>
        <select name="enseignant_id" required class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <?php while($ens = $enseignants->fetch_assoc()): ?>
          <option value="<?= $ens['id'] ?>" <?= ($edit_item && $edit_item['enseignant_id'] == $ens['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($ens['nom'].' '.$ens['prenom']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Jour *</label>
        <select name="jour_semaine" required class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <?php
          $jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
          foreach ($jours as $j):
          ?>
          <option value="<?= $j ?>" <?= ($edit_item && $edit_item['jour_semaine'] == $j) ? 'selected' : '' ?>>
            <?= $j ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Heure début *</label>
        <input type="time" name="heure_debut" value="<?= $edit_item['heure_debut'] ?? '' ?>" required class="w-full border rounded-lg px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Heure fin *</label>
        <input type="time" name="heure_fin" value="<?= $edit_item['heure_fin'] ?? '' ?>" required class="w-full border rounded-lg px-3 py-2">
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Salle</label>
        <input type="text" name="salle" value="<?= htmlspecialchars($edit_item['salle'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2">
      </div>

      <div class="flex justify-end space-x-4 pt-6 border-t md:col-span-2">
        <button type="button" onclick="toggleModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          <i class="fas fa-save mr-2"></i> <?= $edit_item ? 'Modifier' : 'Ajouter' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleModal(){ document.getElementById('modal').classList.toggle('hidden'); }
<?php if ($edit_item): ?> toggleModal(); <?php endif; ?>
</script>
</body>
</html>
