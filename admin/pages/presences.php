<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

$message = '';
$date_selected = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$classe_selected = isset($_GET['classe']) ? intval($_GET['classe']) : 0;

// ENREGISTREMENT DES PRÉSENCES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['presences'])) {
    $date = $_POST['date'];
    $classe_id = intval($_POST['classe_id']);
    $presences = $_POST['presences'];
    
    foreach ($presences as $eleve_id => $status) {
        $present = ($status == 'present') ? 1 : 0;
        $remarque = isset($_POST['remarques'][$eleve_id]) ? trim($_POST['remarques'][$eleve_id]) : '';
        
        // Vérifier si l'entrée existe déjà
        $check = $conn->query("SELECT id FROM presences WHERE eleve_id = $eleve_id AND date = '$date'");
        
        if ($check->num_rows > 0) {
            // Mise à jour
            $stmt = $conn->prepare("UPDATE presences SET present = ?, remarque = ?, saisie_par_enseignant_id = ? WHERE eleve_id = ? AND date = ?");
            $enseignant_id = $_SESSION['user_id'];
            $stmt->bind_param("isiss", $present, $remarque, $enseignant_id, $eleve_id, $date);
        } else {
            // Insertion
            $stmt = $conn->prepare("INSERT INTO presences (eleve_id, classe_id, date, present, remarque, saisie_par_enseignant_id) VALUES (?, ?, ?, ?, ?, ?)");
            $enseignant_id = $_SESSION['user_id'];
            $stmt->bind_param("iisisi", $eleve_id, $classe_id, $date, $present, $remarque, $enseignant_id);
        }
        
        $stmt->execute();
        
        // Créer une absence si absent
        if (!$present) {
            $absence_check = $conn->query("SELECT id FROM absences WHERE eleve_id = $eleve_id AND date = '$date'");
            if ($absence_check->num_rows == 0) {
                $conn->query("INSERT INTO absences (eleve_id, date, justifiee, motif) VALUES ($eleve_id, '$date', 0, '$remarque')");
            }
        } else {
            // Supprimer l'absence si marqué présent
            $conn->query("DELETE FROM absences WHERE eleve_id = $eleve_id AND date = '$date'");
        }
    }
    
    $message = "Présences enregistrées avec succès !";
}

// Liste des classes
$classes = $conn->query("SELECT * FROM classes ORDER BY nom");

// Si une classe est sélectionnée, récupérer les élèves
$eleves = null;
if ($classe_selected > 0) {
    $eleves = $conn->query("
        SELECT e.*, 
               p.present, p.remarque,
               (SELECT COUNT(*) FROM absences WHERE eleve_id = e.id AND MONTH(date) = MONTH(CURDATE())) as absences_mois
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        LEFT JOIN presences p ON e.id = p.eleve_id AND p.date = '$date_selected'
        WHERE i.classe_id = $classe_selected 
        AND i.statut = 'active'
        AND e.statut = 'actif'
        ORDER BY e.nom, e.prenom
    ");
}

// Statistiques
$stats = [];
if ($classe_selected > 0) {
    $result = $conn->query("
        SELECT 
            COUNT(DISTINCT e.id) as total_eleves,
            SUM(CASE WHEN p.present = 1 THEN 1 ELSE 0 END) as presents,
            SUM(CASE WHEN p.present = 0 THEN 1 ELSE 0 END) as absents
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        LEFT JOIN presences p ON e.id = p.eleve_id AND p.date = '$date_selected'
        WHERE i.classe_id = $classe_selected AND i.statut = 'active' AND e.statut = 'actif'
    ");
    $stats = $result->fetch_assoc();
    $stats['non_renseignes'] = $stats['total_eleves'] - ($stats['presents'] + $stats['absents']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Présences | École Mandroso</title>
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    
    <?php include('../includes/nav.php'); ?>
    
    <div class="flex">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="main-content flex-1 p-4 md:p-8 mt-16">
            
            <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-calendar-check text-blue-600 mr-2"></i>
                    Gestion des Présences
                </h1>
                <p class="text-gray-600">Faire l'appel quotidien des élèves</p>
            </div>
            
            <!-- Sélection classe et date -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Classe *</label>
                        <select name="classe" onchange="this.form.submit()" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Sélectionner une classe</option>
                            <?php 
                            $classes_select = $conn->query("SELECT * FROM classes ORDER BY nom");
                            while($classe = $classes_select->fetch_assoc()): 
                            ?>
                                <option value="<?= $classe['id'] ?>" <?= $classe_selected == $classe['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classe['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                        <input type="date" name="date" value="<?= $date_selected ?>" onchange="this.form.submit()"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="button" onclick="marquerTousPresents()" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-check-double mr-2"></i> Tous présents
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Statistiques -->
            <?php if ($classe_selected > 0 && $stats): ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                                <i class="fas fa-users text-blue-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Total élèves</p>
                                <p class="text-2xl font-bold text-gray-800"><?= $stats['total_eleves'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                                <i class="fas fa-check text-green-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Présents</p>
                                <p class="text-2xl font-bold text-green-600"><?= $stats['presents'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-100 rounded-lg p-3">
                                <i class="fas fa-times text-red-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Absents</p>
                                <p class="text-2xl font-bold text-red-600"><?= $stats['absents'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 rounded-lg p-3">
                                <i class="fas fa-question text-gray-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Non renseignés</p>
                                <p class="text-2xl font-bold text-gray-600"><?= $stats['non_renseignes'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Formulaire de présences -->
            <?php if ($eleves && $eleves->num_rows > 0): ?>
                <form method="POST" class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <input type="hidden" name="date" value="<?= $date_selected ?>">
                    <input type="hidden" name="classe_id" value="<?= $classe_selected ?>">
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom & Prénom</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remarque</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Absences ce mois</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php while($eleve = $eleves->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($eleve['matricule']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <div class="flex justify-center space-x-2">
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="radio" name="presences[<?= $eleve['id'] ?>]" value="present" 
                                                           <?= ($eleve['present'] === '1' || $eleve['present'] === null) ? 'checked' : '' ?>
                                                           class="form-radio text-green-600 h-5 w-5">
                                                    <span class="ml-2 text-green-600">
                                                        <i class="fas fa-check-circle text-xl"></i>
                                                    </span>
                                                </label>
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="radio" name="presences[<?= $eleve['id'] ?>]" value="absent" 
                                                           <?= ($eleve['present'] === '0') ? 'checked' : '' ?>
                                                           class="form-radio text-red-600 h-5 w-5">
                                                    <span class="ml-2 text-red-600">
                                                        <i class="fas fa-times-circle text-xl"></i>
                                                    </span>
                                                </label>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <input type="text" name="remarques[<?= $eleve['id'] ?>]" 
                                                   value="<?= htmlspecialchars($eleve['remarque'] ?? '') ?>"
                                                   placeholder="Remarque..."
                                                   class="w-full px-3 py-1 text-sm border rounded focus:ring-2 focus:ring-blue-500">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $eleve['absences_mois'] > 3 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= $eleve['absences_mois'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="bg-gray-50 px-6 py-4 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i> Enregistrer les présences
                        </button>
                    </div>
                </form>
            <?php elseif ($classe_selected > 0): ?>
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-user-slash text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Aucun élève trouvé dans cette classe</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-hand-pointer text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Sélectionnez une classe pour commencer</p>
                </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <script>
        function marquerTousPresents() {
            const radios = document.querySelectorAll('input[type="radio"][value="present"]');
            radios.forEach(radio => radio.checked = true);
        }
    </script>
    
</body>
</html>