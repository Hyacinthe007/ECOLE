<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

$message = '';
$error = '';

// Récupération des données pour les filtres
$classes = $conn->query("SELECT * FROM classes ORDER BY nom");
$annees = $conn->query("SELECT * FROM annees_scolaires ORDER BY date_debut DESC");

// Filtres
$classe_filter = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$trimestre_filter = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;
$eleve_id = isset($_GET['eleve']) ? intval($_GET['eleve']) : 0;

// Liste des élèves si une classe est sélectionnée
$eleves = null;
if ($classe_filter > 0) {
    $eleves = $conn->query("
        SELECT e.*, c.nom as classe_nom
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        JOIN classes c ON i.classe_id = c.id
        WHERE i.classe_id = $classe_filter 
        AND i.statut = 'active'
        AND e.statut = 'actif'
        ORDER BY e.nom, e.prenom
    ");
}

// Récupération des paramètres de l'école
$params_result = $conn->query("SELECT * FROM parametres_ecole WHERE id = 1");
$params = $params_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <title>Bulletins Trimestriels | École Mandroso</title>
    <link rel="icon" type="image/png" href="../assets/favicon.png">
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
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-file-pdf text-red-600 mr-2"></i>
                        Bulletins Trimestriels
                    </h1>
                    <p class="text-gray-600">Génération et impression des bulletins de notes</p>
                </div>
                <button onclick="openConfigModal()" class="mt-4 md:mt-0 bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-cog mr-2"></i> Configuration en-tête
                </button>
            </div>
            
            <!-- Filtres de sélection -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-filter mr-2 text-blue-600"></i>
                    Sélection
                </h2>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Classe *</label>
                        <select name="classe" required onchange="this.form.submit()" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Sélectionner une classe</option>
                            <?php 
                            $classes_select = $conn->query("SELECT * FROM classes ORDER BY nom");
                            while($classe = $classes_select->fetch_assoc()): 
                            ?>
                                <option value="<?= $classe['id'] ?>" <?= $classe_filter == $classe['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classe['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Trimestre *</label>
                        <select name="trimestre" required onchange="this.form.submit()" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="1" <?= $trimestre_filter == 1 ? 'selected' : '' ?>>Trimestre 1</option>
                            <option value="2" <?= $trimestre_filter == 2 ? 'selected' : '' ?>>Trimestre 2</option>
                            <option value="3" <?= $trimestre_filter == 3 ? 'selected' : '' ?>>Trimestre 3</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Élève (optionnel)</label>
                        <select name="eleve" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous les élèves</option>
                            <?php if ($eleves && $eleves->num_rows > 0): ?>
                                <?php while($eleve = $eleves->fetch_assoc()): ?>
                                    <option value="<?= $eleve['id'] ?>" <?= $eleve_id == $eleve['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Actions de génération -->
            <?php if ($classe_filter > 0): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-download mr-2 text-green-600"></i>
                        Génération des bulletins
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if ($eleve_id > 0): ?>
                            <!-- Bulletin individuel -->
                            <a href="generer_bulletin.php?eleve=<?= $eleve_id ?>&trimestre=<?= $trimestre_filter ?>" 
                               target="_blank"
                               class="flex flex-col items-center justify-center p-6 bg-blue-50 hover:bg-blue-100 rounded-lg transition group border-2 border-blue-200">
                                <i class="fas fa-file-pdf text-4xl text-blue-600 mb-3 group-hover:scale-110 transition"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Générer le bulletin sélectionné</span>
                                <span class="text-xs text-gray-500 mt-1">Format PDF</span>
                            </a>
                        <?php else: ?>
                            <!-- Tous les bulletins -->
                            <a href="generer_bulletin.php?classe=<?= $classe_filter ?>&trimestre=<?= $trimestre_filter ?>&mode=tous" 
                               target="_blank"
                               class="flex flex-col items-center justify-center p-6 bg-green-50 hover:bg-green-100 rounded-lg transition group border-2 border-green-200">
                                <i class="fas fa-file-pdf text-4xl text-green-600 mb-3 group-hover:scale-110 transition"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Générer tous les bulletins</span>
                                <span class="text-xs text-gray-500 mt-1">Format PDF</span>
                            </a>
                            
                            <a href="generer_bulletin.php?classe=<?= $classe_filter ?>&trimestre=<?= $trimestre_filter ?>&mode=zip" 
                               class="flex flex-col items-center justify-center p-6 bg-purple-50 hover:bg-purple-100 rounded-lg transition group border-2 border-purple-200">
                                <i class="fas fa-file-archive text-4xl text-purple-600 mb-3 group-hover:scale-110 transition"></i>
                                <span class="text-sm font-medium text-gray-700 text-center">Télécharger en ZIP</span>
                                <span class="text-xs text-gray-500 mt-1">Bulletins séparés</span>
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="openPreviewModal()" 
                               class="flex flex-col items-center justify-center p-6 bg-orange-50 hover:bg-orange-100 rounded-lg transition group border-2 border-orange-200">
                            <i class="fas fa-eye text-4xl text-orange-600 mb-3 group-hover:scale-110 transition"></i>
                            <span class="text-sm font-medium text-gray-700 text-center">Aperçu avant impression</span>
                            <span class="text-xs text-gray-500 mt-1">Prévisualisation</span>
                        </button>
                    </div>
                </div>
                
                <!-- Liste des élèves avec aperçu des moyennes -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-list mr-2 text-blue-600"></i>
                            Élèves de la classe
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom & Prénom</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Moyenne</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rang</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                $eleves_list = $conn->query("
                                    SELECT e.*
                                    FROM eleves e
                                    JOIN inscriptions i ON e.id = i.eleve_id
                                    WHERE i.classe_id = $classe_filter 
                                    AND i.statut = 'active'
                                    AND e.statut = 'actif'
                                    ORDER BY e.nom, e.prenom
                                ");
                                
                                if ($eleves_list->num_rows > 0):
                                    while($eleve = $eleves_list->fetch_assoc()):
                                        // Calcul de la moyenne
                                        $moyenne_result = $conn->query("
                                            SELECT AVG((n.note / n.note_max) * 20) as moyenne
                                            FROM notes n
                                            WHERE n.eleve_id = {$eleve['id']}
                                            AND n.trimestre = $trimestre_filter
                                        ");
                                        $moyenne_data = $moyenne_result->fetch_assoc();
                                        $moyenne = $moyenne_data['moyenne'] ? round($moyenne_data['moyenne'], 2) : 0;
                                        
                                        $color_class = $moyenne >= 10 ? 'text-green-600' : 'text-red-600';
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($eleve['matricule']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="text-lg font-bold <?= $color_class ?>">
                                                <?= number_format($moyenne, 2) ?> / 20
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-3 py-1 bg-gray-100 text-gray-800 text-sm rounded-full">
                                                -
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <a href="generer_bulletin.php?eleve=<?= $eleve['id'] ?>&trimestre=<?= $trimestre_filter ?>" 
                                               target="_blank"
                                               class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition">
                                                <i class="fas fa-download mr-1"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-inbox text-4xl mb-2"></i>
                                            <p>Aucun élève dans cette classe</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                    <i class="fas fa-hand-pointer text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Sélectionnez une classe et un trimestre pour commencer</p>
                </div>
            <?php endif; ?>
            
        </main>
    </div>
    
    <!-- Modal Configuration En-tête -->
    <div id="configModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-cog mr-2 text-purple-600"></i>
                        Configuration de l'en-tête du bulletin
                    </h2>
                    <button onclick="closeConfigModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <form action="save_config_bulletin.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-image mr-2"></i>Logo de l'école
                            </label>
                            <?php if ($params && $params['logo']): ?>
                                <div class="mb-3">
                                    <img src="../assets/uploads/<?= htmlspecialchars($params['logo']) ?>" alt="Logo actuel" class="h-20 rounded border">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="logo" accept="image/*" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                            <p class="text-xs text-gray-500 mt-1">Recommandé: 200x200px, PNG avec fond transparent</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom de l'établissement</label>
                            <input type="text" name="nom_ecole" value="<?= htmlspecialchars($params['nom_ecole'] ?? 'École Mandroso') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                            <input type="text" name="devise" value="<?= htmlspecialchars($params['devise'] ?? '') ?>"
                                   placeholder="Ex: Excellence et Développement"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adresse complète</label>
                            <input type="text" name="adresse" value="<?= htmlspecialchars($params['adresse'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="text" name="telephone" value="<?= htmlspecialchars($params['telephone'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($params['email'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom du directeur</label>
                            <input type="text" name="directeur_nom" value="<?= htmlspecialchars($params['directeur_nom'] ?? '') ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Titre du directeur</label>
                            <input type="text" name="directeur_titre" value="Le Directeur"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <button type="button" onclick="closeConfigModal()" class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            <i class="fas fa-save mr-2"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openConfigModal() {
            document.getElementById('configModal').classList.remove('hidden');
        }
        
        function closeConfigModal() {
            document.getElementById('configModal').classList.add('hidden');
        }
        
        function openPreviewModal() {
            <?php if ($classe_filter > 0 && $eleve_id > 0): ?>
                window.open('generer_bulletin.php?eleve=<?= $eleve_id ?>&trimestre=<?= $trimestre_filter ?>&preview=1', '_blank');
            <?php elseif ($classe_filter > 0): ?>
                alert('Veuillez sélectionner un élève pour l\'aperçu');
            <?php else: ?>
                alert('Veuillez sélectionner une classe et un élève');
            <?php endif; ?>
        }
    </script>
    
</body>
</html>