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

// --- Récupération des IDs pour suppression groupée ---
if (isset($_GET['get_ids'])) {
    $classe_id = intval($_GET['classe_id']);
    $matiere_id = intval($_GET['matiere_id']);
    $enseignant_id = intval($_GET['enseignant_id']);
    $heure_debut = $_GET['heure_debut'];
    $heure_fin = $_GET['heure_fin'];
    
    $result = $conn->query(
        "SELECT id FROM emploi_temps 
         WHERE classe_id = $classe_id 
         AND matiere_id = $matiere_id 
         AND enseignant_id = $enseignant_id 
         AND heure_debut = '$heure_debut' 
         AND heure_fin = '$heure_fin'"
    );
    
    $ids = [];
    while($row = $result->fetch_assoc()) {
        $ids[] = $row['id'];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['ids' => $ids]);
    exit();
}

// --- Suppression groupée ---
if (isset($_GET['delete_group'])) {
    $ids = $_GET['delete_group'];
    $ids_array = explode(',', $ids);
    $ids_safe = array_map('intval', $ids_array);
    $ids_string = implode(',', $ids_safe);
    $conn->query("DELETE FROM emploi_temps WHERE id IN ($ids_string)");
    header("Location: emploi-temps.php?success=1");
    exit();
}

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

// --- Requête principale pour récupérer tous les emplois du temps ---
$sql = "SELECT e.*, c.nom AS classe_nom, m.nom AS matiere_nom, ens.nom AS enseignant_nom, ens.prenom AS enseignant_prenom
        FROM emploi_temps e
        JOIN classes c ON e.classe_id = c.id
        JOIN matieres m ON e.matiere_id = m.id
        JOIN enseignants ens ON e.enseignant_id = ens.id
        WHERE 1=1";

if ($classe_filter > 0) $sql .= " AND e.classe_id = $classe_filter";
if ($enseignant_filter > 0) $sql .= " AND e.enseignant_id = $enseignant_filter";

$sql .= " ORDER BY c.nom, m.nom, e.heure_debut, FIELD(e.jour_semaine,'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi')";
$emplois_result = $conn->query($sql);

// Organiser les données par classe, matière, enseignant, heure
$emplois_organises = [];
$jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

while($e = $emplois_result->fetch_assoc()) {
    $key = $e['classe_id'] . '_' . $e['matiere_id'] . '_' . $e['enseignant_id'] . '_' . $e['heure_debut'] . '_' . $e['heure_fin'];
    
    if (!isset($emplois_organises[$key])) {
        $emplois_organises[$key] = [
            'id' => $e['id'],
            'classe_id' => $e['classe_id'],
            'classe_nom' => $e['classe_nom'],
            'matiere_id' => $e['matiere_id'],
            'matiere_nom' => $e['matiere_nom'],
            'enseignant_id' => $e['enseignant_id'],
            'enseignant_nom' => $e['enseignant_nom'],
            'enseignant_prenom' => $e['enseignant_prenom'],
            'heure_debut' => $e['heure_debut'],
            'heure_fin' => $e['heure_fin'],
            'salle' => $e['salle'],
            'jours' => []
        ];
    }
    
    // Ajouter le jour avec les détails
    $emplois_organises[$key]['jours'][$e['jour_semaine']] = [
        'id' => $e['id'],
        'salle' => $e['salle']
    ];
}

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
      <button onclick="addEmploi()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
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

    <!-- Tableau matriciel -->
    <div class="bg-white rounded-xl shadow-lg overflow-x-auto">
      <table class="w-full min-w-max">
        <thead class="bg-gray-100 sticky top-0">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-r">Classe</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-r">Matière</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-r">Heure</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase border-r">Enseignant</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-r">Lundi</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-r">Mardi</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-r">Mercredi</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-r">Jeudi</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase border-r">Vendredi</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php if (!empty($emplois_organises)): ?>
            <?php foreach($emplois_organises as $emploi): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900 border-r whitespace-nowrap">
                <?= htmlspecialchars($emploi['classe_nom']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 border-r whitespace-nowrap">
                <?= htmlspecialchars($emploi['matiere_nom']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 text-center border-r whitespace-nowrap">
                <?= $emploi['heure_debut'] ?> - <?= $emploi['heure_fin'] ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 border-r whitespace-nowrap">
                <?= htmlspecialchars($emploi['enseignant_nom'] . ' ' . $emploi['enseignant_prenom']) ?>
              </td>
              <?php foreach($jours_semaine as $jour): ?>
                <td class="px-2 py-3 text-center border-r">
                  <?php if (isset($emploi['jours'][$jour])): ?>
                    <!-- Cours présent - Vert -->
                    <div class="w-full h-10 bg-green-500 rounded flex items-center justify-center cursor-pointer hover:bg-green-600 transition group relative" 
                         title="Cours: <?= htmlspecialchars($emploi['matiere_nom']) ?> - Salle: <?= htmlspecialchars($emploi['jours'][$jour]['salle'] ?: 'Non spécifiée') ?>"
                         onclick="editEmploi(<?= $emploi['jours'][$jour]['id'] ?>)">
                      <i class="fas fa-check text-white text-sm"></i>
                      <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition whitespace-nowrap z-10">
                        <?= htmlspecialchars($emploi['jours'][$jour]['salle'] ?: 'Salle non spécifiée') ?>
                      </span>
                    </div>
                  <?php else: ?>
                    <!-- Pas de cours - Rouge -->
                    <div class="w-full h-10 bg-red-500 rounded flex items-center justify-center cursor-pointer hover:bg-red-600 transition"
                         title="Cliquez pour ajouter un cours ce jour"
                         onclick="addEmploiForDay('<?= $jour ?>', <?= $emploi['classe_id'] ?>, <?= $emploi['matiere_id'] ?>, <?= $emploi['enseignant_id'] ?>, '<?= $emploi['heure_debut'] ?>', '<?= $emploi['heure_fin'] ?>')">
                      <i class="fas fa-times text-white text-sm"></i>
                    </div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td class="px-4 py-3 text-center whitespace-nowrap">
                <button onclick="editEmploiGroup(<?= htmlspecialchars(json_encode($emploi)) ?>)" 
                        class="text-blue-600 hover:text-blue-900 mr-3" title="Modifier">
                  <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteEmploiGroup(<?= $emploi['classe_id'] ?>, <?= $emploi['matiere_id'] ?>, <?= $emploi['enseignant_id'] ?>, '<?= $emploi['heure_debut'] ?>', '<?= $emploi['heure_fin'] ?>')" 
                        class="text-red-600 hover:text-red-900" title="Supprimer">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="10" class="text-center text-gray-500 py-8">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>Aucun emploi du temps trouvé.</p>
              </td>
            </tr>
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
          <?php 
          $matieres_modal = $conn->query("SELECT * FROM matieres ORDER BY nom");
          while($m = $matieres_modal->fetch_assoc()): ?>
          <option value="<?= $m['id'] ?>" <?= ($edit_item && $edit_item['matiere_id'] == $m['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['nom']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Enseignant *</label>
        <select name="enseignant_id" required class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <?php 
          $enseignants_modal = $conn->query("SELECT * FROM enseignants ORDER BY nom");
          while($ens = $enseignants_modal->fetch_assoc()): ?>
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
function toggleModal(){ 
    document.getElementById('modal').classList.toggle('hidden');
    if (!document.getElementById('modal').classList.contains('hidden')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = 'auto';
    }
}

function addEmploi() {
    const form = document.querySelector('#modal form');
    
    // Supprimer le champ id si présent
    const idInput = form.querySelector('input[name="id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Réinitialiser le formulaire
    form.reset();
    
    // Changer le titre
    document.querySelector('#modal h2').textContent = 'Ajouter un créneau';
    
    // Ouvrir le modal
    toggleModal();
}

function addEmploiForDay(jour, classe_id, matiere_id, enseignant_id, heure_debut, heure_fin) {
    const form = document.querySelector('#modal form');
    
    // Supprimer le champ id si présent
    const idInput = form.querySelector('input[name="id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Pré-remplir les champs
    form.querySelector('select[name="classe_id"]').value = classe_id;
    form.querySelector('select[name="matiere_id"]').value = matiere_id;
    form.querySelector('select[name="enseignant_id"]').value = enseignant_id;
    form.querySelector('select[name="jour_semaine"]').value = jour;
    form.querySelector('input[name="heure_debut"]').value = heure_debut;
    form.querySelector('input[name="heure_fin"]').value = heure_fin;
    
    // Changer le titre
    document.querySelector('#modal h2').textContent = 'Ajouter un créneau';
    
    // Ouvrir le modal
    toggleModal();
}

function editEmploi(id) {
    // Charger les données du créneau via AJAX ou rediriger
    window.location.href = '?edit=' + id;
}

function editEmploiGroup(emploi) {
    // Ouvrir le modal avec les données du groupe pour modification
    const form = document.querySelector('#modal form');
    
    // Supprimer le champ id si présent
    const idInput = form.querySelector('input[name="id"]');
    if (idInput) {
        idInput.remove();
    }
    
    // Pré-remplir les champs communs
    form.querySelector('select[name="classe_id"]').value = emploi.classe_id;
    form.querySelector('select[name="matiere_id"]').value = emploi.matiere_id;
    form.querySelector('select[name="enseignant_id"]').value = emploi.enseignant_id;
    form.querySelector('input[name="heure_debut"]').value = emploi.heure_debut;
    form.querySelector('input[name="heure_fin"]').value = emploi.heure_fin;
    
    // Si plusieurs jours, on peut pré-remplir avec le premier jour trouvé
    const jours = Object.keys(emploi.jours);
    if (jours.length > 0) {
        form.querySelector('select[name="jour_semaine"]').value = jours[0];
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = emploi.jours[jours[0]].id;
        form.insertBefore(idInput, form.firstChild);
        
        form.querySelector('input[name="salle"]').value = emploi.jours[jours[0]].salle || '';
    }
    
    // Changer le titre
    document.querySelector('#modal h2').textContent = 'Modifier un créneau';
    
    // Ouvrir le modal
    toggleModal();
}

function deleteEmploiGroup(classe_id, matiere_id, enseignant_id, heure_debut, heure_fin) {
    if (confirm('Voulez-vous supprimer tous les créneaux de ce groupe (tous les jours) ?')) {
        // Récupérer tous les IDs des créneaux de ce groupe
        fetch(`?get_ids=1&classe_id=${classe_id}&matiere_id=${matiere_id}&enseignant_id=${enseignant_id}&heure_debut=${heure_debut}&heure_fin=${heure_fin}`)
            .then(response => response.json())
            .then(data => {
                if (data.ids && data.ids.length > 0) {
                    // Supprimer tous les créneaux en une seule requête
                    const ids = data.ids.join(',');
                    window.location.href = `?delete_group=${ids}`;
                }
            })
            .catch(err => {
                console.error('Erreur:', err);
                alert('Erreur lors de la suppression');
            });
    }
}

<?php if ($edit_item): ?> 
    toggleModal(); 
<?php endif; ?>
</script>
</body>
</html>
