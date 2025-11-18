<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

// 🔹 Suppression
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM paiements WHERE id = $id");
    header("Location: paiements.php?success=1");
    exit();
}

// 🔹 Ajout / Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $frais_id = intval($_POST['frais_scolarite_id']);
    $montant_paye = floatval($_POST['montant_paye']);
    $date_paiement = $_POST['date_paiement'];
    $mode = $_POST['mode_paiement'];
    $ref = trim($_POST['reference_transaction']);
    $recu = $_SESSION['user_id'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE paiements SET frais_scolarite_id=?, montant_paye=?, date_paiement=?, mode_paiement=?, reference_transaction=?, recu_par_user_id=? WHERE id=?");
        $stmt->bind_param("idsssii", $frais_id, $montant_paye, $date_paiement, $mode, $ref, $recu, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO paiements (frais_scolarite_id, montant_paye, date_paiement, mode_paiement, reference_transaction, recu_par_user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsssi", $frais_id, $montant_paye, $date_paiement, $mode, $ref, $recu);
    }

    $stmt->execute();
    header("Location: paiements.php?success=1");
    exit();
}

// 🔹 Liste des paiements
$paiements = $conn->query("
SELECT p.*, f.type_frais, f.montant AS montant_total, e.nom AS eleve_nom, e.prenom AS eleve_prenom 
FROM paiements p
JOIN frais_scolarite f ON p.frais_scolarite_id = f.id
JOIN eleves e ON f.eleve_id = e.id
ORDER BY p.date_paiement DESC
");

// 🔹 Listes pour formulaire
$frais = $conn->query("
SELECT f.id, e.nom, e.prenom, f.type_frais, f.montant 
FROM frais_scolarite f
JOIN eleves e ON f.eleve_id = e.id
ORDER BY e.nom
");

// 🔹 Paiement à modifier
$paiement_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM paiements WHERE id = $id");
    $paiement_edit = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des Paiements | École Mandroso</title>
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
            <i class="fas fa-file-invoice-dollar text-blue-600 mr-2"></i>
            Gestion des Paiements
          </h1>
          <p class="text-gray-600">Suivi des paiements de scolarité</p>
        </div>
        <button onclick="toggleModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
          <i class="fas fa-plus mr-2"></i> Ajouter un paiement
        </button>
      </div>

      <!-- Liste -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Élève</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type de frais</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant payé</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php if ($paiements->num_rows > 0): ?>
                <?php while($p = $paiements->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-800">
                      <?= htmlspecialchars($p['eleve_nom'] . ' ' . $p['eleve_prenom']) ?>
                    </td>
                    <td class="px-6 py-4"><?= htmlspecialchars(ucfirst($p['type_frais'])) ?></td>
                    <td class="px-6 py-4 text-green-700 font-semibold"><?= number_format($p['montant_paye'], 2) ?> Ar</td>
                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($p['mode_paiement']) ?></td>
                    <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($p['reference_transaction']) ?></td>
                    <td class="px-6 py-4 text-gray-500"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                      <a href="?edit=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3"><i class="fas fa-edit"></i></a>
                      <a href="?delete=<?= $p['id'] ?>" onclick="return confirm('Supprimer ce paiement ?')" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Aucun paiement enregistré</p>
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
            <?= $paiement_edit ? 'Modifier un paiement' : 'Ajouter un paiement' ?>
          </h2>
          <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-2xl"></i>
          </button>
        </div>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php if ($paiement_edit): ?>
            <input type="hidden" name="id" value="<?= $paiement_edit['id'] ?>">
          <?php endif; ?>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Frais de scolarité *</label>
            <select name="frais_scolarite_id" required class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
              <option value="">-- Sélectionner --</option>
              <?php while($f = $frais->fetch_assoc()): ?>
                <option value="<?= $f['id'] ?>" <?= ($paiement_edit && $paiement_edit['frais_scolarite_id'] == $f['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($f['nom'].' '.$f['prenom'].' — '.$f['type_frais'].' ('.$f['montant'].' Ar)') ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Montant payé *</label>
            <input type="number" step="0.01" name="montant_paye" required value="<?= $paiement_edit['montant_paye'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
            <input type="date" name="date_paiement" required value="<?= $paiement_edit['date_paiement'] ?? date('Y-m-d') ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Mode de paiement *</label>
            <select name="mode_paiement" required class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
              <option value="espèces">Espèces</option>
              <option value="virement">Virement</option>
              <option value="mobile_money">Mobile Money</option>
              <option value="chèque">Chèque</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Référence transaction</label>
            <input type="text" name="reference_transaction" value="<?= $paiement_edit['reference_transaction'] ?? '' ?>" class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="flex justify-end space-x-4 pt-6 border-t md:col-span-2">
            <button type="button" onclick="toggleModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              <i class="fas fa-save mr-2"></i> <?= $paiement_edit ? 'Modifier' : 'Ajouter' ?>
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
    <?php if ($paiement_edit): ?> toggleModal(); <?php endif; ?>
  </script>
</body>
</html>