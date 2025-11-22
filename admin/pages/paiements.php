<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

$message = '';
$error = '';

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_frais') {
    $frais_id = intval($_POST['frais_id']);
    $conn->query("DELETE FROM frais_scolarite WHERE id = $frais_id");
    header("Location: paiements.php?success=1");
    exit();
}

// Ajout/Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_frais') {
        $eleve_id = intval($_POST['eleve_id']);
        $type_frais = trim($_POST['type_frais']);
        $montant = floatval($_POST['montant']);
        $statut = $_POST['statut'] ?? 'en_attente';
        $date_echeance = $_POST['date_echeance'] ?? null;
        
        // Récupérer l'année scolaire active
        $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
        $annee_scolaire_id = 0;
        if ($annee_result->num_rows > 0) {
            $annee_scolaire_id = $annee_result->fetch_assoc()['id'];
        }
        
        // Validation
        if ($eleve_id <= 0) {
            $error = "Veuillez sélectionner un élève.";
        } elseif (empty($type_frais)) {
            $error = "Le type de frais est obligatoire.";
        } elseif ($montant <= 0) {
            $error = "Le montant doit être supérieur à 0.";
        } elseif (empty($date_echeance)) {
            $error = "La date d'échéance est obligatoire.";
        } elseif ($annee_scolaire_id <= 0) {
            $error = "Aucune année scolaire active trouvée. Veuillez activer une année scolaire.";
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO frais_scolarite (eleve_id, annee_scolaire_id, type_frais, montant, date_echeance, statut) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdss", $eleve_id, $annee_scolaire_id, $type_frais, $montant, $date_echeance, $statut);
            if ($stmt->execute()) {
                header("Location: paiements.php?success=1");
                exit();
            } else {
                $error = "Erreur lors de l'enregistrement : " . htmlspecialchars($stmt->error);
            }
        }
    } elseif ($_POST['action'] === 'update_frais') {
        $id = intval($_POST['frais_id']);
        $eleve_id = intval($_POST['eleve_id']);
        $type_frais = trim($_POST['type_frais']);
        $montant = floatval($_POST['montant']);
        $statut = $_POST['statut'] ?? 'en_attente';
        $date_echeance = $_POST['date_echeance'] ?? null;
        
        // Validation
        if ($eleve_id <= 0) {
            $error = "Veuillez sélectionner un élève.";
        } elseif (empty($type_frais)) {
            $error = "Le type de frais est obligatoire.";
        } elseif ($montant <= 0) {
            $error = "Le montant doit être supérieur à 0.";
        } elseif (empty($date_echeance)) {
            $error = "La date d'échéance est obligatoire.";
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE frais_scolarite SET eleve_id=?, type_frais=?, montant=?, date_echeance=?, statut=? WHERE id=?");
            $stmt->bind_param("isdssi", $eleve_id, $type_frais, $montant, $date_echeance, $statut, $id);
            if ($stmt->execute()) {
                header("Location: paiements.php?success=1");
                exit();
            } else {
                $error = "Erreur lors de la modification : " . htmlspecialchars($stmt->error);
            }
        }
    }
}

// Récupérer l'année scolaire active
$annee_active = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
$annee_active_id = 0;
if ($annee_active->num_rows > 0) {
    $annee_active_id = $annee_active->fetch_assoc()['id'];
}

// Récupération des frais de scolarité avec informations des élèves
$annee_condition = $annee_active_id > 0 ? "AND f.annee_scolaire_id = $annee_active_id" : "";
$frais_scolarite = $conn->query(
    "SELECT f.*, 
            e.nom as eleve_nom, 
            e.prenom as eleve_prenom, 
            e.matricule as eleve_matricule,
            COALESCE(SUM(p.montant_paye), 0) as montant_paye,
            (f.montant - COALESCE(SUM(p.montant_paye), 0)) as montant_restant
     FROM frais_scolarite f
     LEFT JOIN eleves e ON f.eleve_id = e.id
     LEFT JOIN paiements p ON f.id = p.frais_scolarite_id
     WHERE 1=1 $annee_condition
     GROUP BY f.id
     ORDER BY f.id DESC, e.nom, e.prenom"
);

// Frais à modifier
$frais_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM frais_scolarite WHERE id = $id");
    $frais_edit = $res->fetch_assoc();
}

// Liste des élèves pour le formulaire
$eleves_list = $conn->query("SELECT id, nom, prenom, matricule FROM eleves WHERE statut = 'actif' ORDER BY nom, prenom");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des Frais de Scolarité | École Mandroso</title>
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
      <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
          <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 mb-2">
            <i class="fas fa-money-bill-wave text-blue-600 mr-2"></i>
            Gestion des Frais de Scolarité
          </h1>
          <p class="text-gray-600">Liste et gestion des frais de scolarité des élèves</p>
        </div>
        <button onclick="addFrais()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
          <i class="fas fa-plus mr-2"></i> Ajouter des frais
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant payé</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reste à payer</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date échéance</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <?php if ($frais_scolarite->num_rows > 0): ?>
                <?php while($frais = $frais_scolarite->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                          <i class="fas fa-user text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($frais['eleve_nom'] . ' ' . $frais['eleve_prenom']) ?>
                          </div>
                          <div class="text-sm text-gray-500">
                            <?= htmlspecialchars($frais['eleve_matricule']) ?>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <?= htmlspecialchars(ucfirst($frais['type_frais'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      <?= number_format((float)$frais['montant'], 0, ',', ' ') ?> Ar
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                      <?= number_format((float)$frais['montant_paye'], 0, ',', ' ') ?> Ar
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= (float)$frais['montant_restant'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                      <?= number_format((float)$frais['montant_restant'], 0, ',', ' ') ?> Ar
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <?= $frais['date_echeance'] ? date('d/m/Y', strtotime($frais['date_echeance'])) : '-' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <?php 
                      $statut_class = '';
                      $statut_text = '';
                      switch($frais['statut']) {
                          case 'paye':
                              $statut_class = 'bg-green-100 text-green-800';
                              $statut_text = 'Payé';
                              break;
                          case 'en_retard':
                              $statut_class = 'bg-orange-100 text-orange-800';
                              $statut_text = 'En retard';
                              break;
                          case 'en_attente':
                          default:
                              $statut_class = 'bg-red-100 text-red-800';
                              $statut_text = 'En attente';
                              break;
                      }
                      ?>
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statut_class ?>">
                        <?= $statut_text ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <button onclick="editFrais(<?= htmlspecialchars(json_encode($frais)) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ces frais de scolarité ?')">
                        <input type="hidden" name="action" value="delete_frais">
                        <input type="hidden" name="frais_id" value="<?= $frais['id'] ?>">
                        <button type="submit" class="text-red-600 hover:text-red-900">
                          <i class="fas fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Aucun frais de scolarité enregistré</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Ajout/Modification Frais de Scolarité -->
  <div id="modalFrais" class="<?= $frais_edit ? '' : 'hidden' ?> fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">
            <?= $frais_edit ? 'Modifier les frais de scolarité' : 'Ajouter des frais de scolarité' ?>
          </h2>
          <button onclick="toggleFraisModal()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-2xl"></i>
          </button>
        </div>
        <form method="POST" class="space-y-6">
          <input type="hidden" name="action" value="<?= $frais_edit ? 'update_frais' : 'add_frais' ?>">
          <?php if ($frais_edit): ?>
            <input type="hidden" name="frais_id" value="<?= $frais_edit['id'] ?>">
          <?php endif; ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Élève *</label>
              <select name="eleve_id" required id="eleve_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">-- Sélectionner un élève --</option>
                <?php 
                if ($eleves_list->num_rows > 0) {
                    $eleves_list->data_seek(0);
                    while($eleve = $eleves_list->fetch_assoc()): 
                        $selected = ($frais_edit && $frais_edit['eleve_id'] == $eleve['id']) ? 'selected' : '';
                ?>
                  <option value="<?= $eleve['id'] ?>" <?= $selected ?>>
                    <?= htmlspecialchars($eleve['matricule'] . ' - ' . $eleve['nom'] . ' ' . $eleve['prenom']) ?>
                  </option>
                <?php 
                    endwhile;
                }
                ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Type de frais *</label>
              <select name="type_frais" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">-- Sélectionner --</option>
                <option value="inscription" <?= ($frais_edit && $frais_edit['type_frais'] == 'inscription') ? 'selected' : '' ?>>Inscription</option>
                <option value="mensuel" <?= ($frais_edit && $frais_edit['type_frais'] == 'mensuel') ? 'selected' : '' ?>>Mensuel</option>
                <option value="cantine" <?= ($frais_edit && $frais_edit['type_frais'] == 'cantine') ? 'selected' : '' ?>>Cantine</option>
                <option value="transport" <?= ($frais_edit && $frais_edit['type_frais'] == 'transport') ? 'selected' : '' ?>>Transport</option>
                <option value="uniforme" <?= ($frais_edit && $frais_edit['type_frais'] == 'uniforme') ? 'selected' : '' ?>>Uniforme</option>
                <option value="autre" <?= ($frais_edit && $frais_edit['type_frais'] == 'autre') ? 'selected' : '' ?>>Autre</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Montant (Ar) *</label>
              <input type="number" step="0.01" name="montant" required 
                     value="<?= $frais_edit ? $frais_edit['montant'] : '' ?>" 
                     min="0"
                     class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Date d'échéance *</label>
              <input type="date" name="date_echeance" required
                     value="<?= $frais_edit && $frais_edit['date_echeance'] ? $frais_edit['date_echeance'] : '' ?>" 
                     class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Statut *</label>
              <select name="statut" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="en_attente" <?= (!$frais_edit || $frais_edit['statut'] == 'en_attente') ? 'selected' : '' ?>>En attente</option>
                <option value="paye" <?= ($frais_edit && $frais_edit['statut'] == 'paye') ? 'selected' : '' ?>>Payé</option>
                <option value="en_retard" <?= ($frais_edit && $frais_edit['statut'] == 'en_retard') ? 'selected' : '' ?>>En retard</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end space-x-4 pt-6 border-t">
            <button type="button" onclick="toggleFraisModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">Annuler</button>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              <i class="fas fa-save mr-2"></i> <?= $frais_edit ? 'Modifier' : 'Ajouter' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
  function toggleFraisModal() {
    const modal = document.getElementById('modalFrais');
    modal.classList.toggle('hidden');
    if (!modal.classList.contains('hidden')) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'auto';
    }
  }

  function addFrais() {
    const form = document.querySelector('#modalFrais form');
    
    // Supprimer le champ frais_id si présent
    const idInput = form.querySelector('input[name="frais_id"]');
    if (idInput) {
      idInput.remove();
    }
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'add_frais';
    
    // Réinitialiser le formulaire
    form.reset();
    
    // Réinitialiser le statut à "en_attente"
    form.querySelector('select[name="statut"]').value = 'en_attente';
    
    // Changer le titre du modal
    document.querySelector('#modalFrais h2').textContent = 'Ajouter des frais de scolarité';
    
    // Ouvrir le modal
    toggleFraisModal();
  }

  function editFrais(frais) {
    const form = document.querySelector('#modalFrais form');
    
    // Ajouter ou mettre à jour le champ hidden frais_id
    let idInput = form.querySelector('input[name="frais_id"]');
    if (!idInput) {
      idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'frais_id';
      form.insertBefore(idInput, form.querySelector('input[name="action"]').nextSibling);
    }
    idInput.value = frais.id;
    
    // Changer l'action
    form.querySelector('input[name="action"]').value = 'update_frais';
    
    // Remplir les champs du formulaire
    form.querySelector('select[name="eleve_id"]').value = frais.eleve_id || '';
    form.querySelector('select[name="type_frais"]').value = frais.type_frais || '';
    form.querySelector('input[name="montant"]').value = frais.montant || '';
    form.querySelector('input[name="date_echeance"]').value = frais.date_echeance || '';
    form.querySelector('select[name="statut"]').value = frais.statut || 'en_attente';
    
    // Changer le titre du modal
    document.querySelector('#modalFrais h2').textContent = 'Modifier les frais de scolarité';
    
    // Ouvrir le modal
    toggleFraisModal();
  }

  // Ouvrir modal si mode édition
  <?php if ($frais_edit): ?>
    editFrais(<?= htmlspecialchars(json_encode($frais_edit)) ?>);
  <?php endif; ?>
  </script>
</body>
</html>
