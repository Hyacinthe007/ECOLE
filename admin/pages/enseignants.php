<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

// ✅ SUPPRESSION
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM enseignants WHERE id = $id");
    header("Location: enseignants.php?success=1");
    exit();
}

// ✅ AJOUT / MODIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $matricule = trim($_POST['matricule']);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $specialite = trim($_POST['specialite']);
    $date_embauche = $_POST['date_embauche'];
    $telephone = trim($_POST['telephone']);
    $email_pro = trim($_POST['email_pro']);
    $diplomes = trim($_POST['diplomes']);
    $statut = $_POST['statut'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE enseignants 
            SET matricule=?, nom=?, prenom=?, specialite=?, date_embauche=?, telephone=?, email_pro=?, diplomes=?, statut=? 
            WHERE id=?");
        $stmt->bind_param("sssssssssi", $matricule, $nom, $prenom, $specialite, $date_embauche, $telephone, $email_pro, $diplomes, $statut, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO enseignants (matricule, nom, prenom, specialite, date_embauche, telephone, email_pro, diplomes, statut)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $matricule, $nom, $prenom, $specialite, $date_embauche, $telephone, $email_pro, $diplomes, $statut);
    }

    $stmt->execute();
    header("Location: enseignants.php?success=1");
    exit();
}

// ✅ LISTE
$enseignants = $conn->query("SELECT * FROM enseignants ORDER BY nom, prenom");

// ✅ Pour édition
$enseignant_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM enseignants WHERE id = $id");
    $enseignant_edit = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des Enseignants | École Mandroso</title>
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
            <i class="fas fa-chalkboard-teacher text-blue-600 mr-2"></i>
            Gestion des Enseignants
          </h1>
          <p class="text-gray-600">Ajout, modification et consultation des enseignants</p>
        </div>
        <button onclick="toggleModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
          <i class="fas fa-plus mr-2"></i> Ajouter un enseignant
        </button>
      </div>

      <!-- Liste des enseignants -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Spécialité</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Téléphone</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php if ($enseignants->num_rows > 0): ?>
                <?php while($ens = $enseignants->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4"><?= htmlspecialchars($ens['matricule']) ?></td>
                    <td class="px-6 py-4 font-medium text-gray-800">
                      <?= htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']) ?>
                    </td>
                    <td class="px-6 py-4"><?= htmlspecialchars($ens['specialite']) ?></td>
                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($ens['telephone']) ?></td>
                    <td class="px-6 py-4 text-blue-600"><?= htmlspecialchars($ens['email_pro']) ?></td>
                    <td class="px-6 py-4">
                      <span class="px-2 py-1 text-xs rounded-full <?= $ens['statut'] === 'actif' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= ucfirst($ens['statut']) ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <a href="?edit=<?= $ens['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="?delete=<?= $ens['id'] ?>" onclick="return confirm('Supprimer cet enseignant ?')" class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Aucun enseignant enregistré</p>
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
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full">
      <div class="p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">
            <?= $enseignant_edit ? 'Modifier un enseignant' : 'Ajouter un enseignant' ?>
          </h2>
          <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-2xl"></i>
          </button>
        </div>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php if ($enseignant_edit): ?>
            <input type="hidden" name="id" value="<?= $enseignant_edit['id'] ?>">
          <?php endif; ?>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Matricule *</label>
            <input type="text" name="matricule" required value="<?= $enseignant_edit['matricule'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
            <input type="text" name="nom" required value="<?= $enseignant_edit['nom'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
            <input type="text" name="prenom" required value="<?= $enseignant_edit['prenom'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Spécialité *</label>
            <input type="text" name="specialite" required value="<?= $enseignant_edit['specialite'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
            <input type="text" name="telephone" value="<?= $enseignant_edit['telephone'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Email professionnel</label>
            <input type="email" name="email_pro" value="<?= $enseignant_edit['email_pro'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Diplômes</label>
            <input type="text" name="diplomes" value="<?= $enseignant_edit['diplomes'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date d’embauche</label>
            <input type="date" name="date_embauche" value="<?= $enseignant_edit['date_embauche'] ?? date('Y-m-d') ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
            <select name="statut" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
              <option value="actif" <?= ($enseignant_edit && $enseignant_edit['statut'] == 'actif') ? 'selected' : '' ?>>Actif</option>
              <option value="inactif" <?= ($enseignant_edit && $enseignant_edit['statut'] == 'inactif') ? 'selected' : '' ?>>Inactif</option>
            </select>
          </div>

          <div class="flex justify-end space-x-4 pt-6 border-t md:col-span-2">
            <button type="button" onclick="toggleModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              <i class="fas fa-save mr-2"></i> <?= $enseignant_edit ? 'Modifier' : 'Ajouter' ?>
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
    <?php if ($enseignant_edit): ?> toggleModal(); <?php endif; ?>
  </script>

</body>
</html>
