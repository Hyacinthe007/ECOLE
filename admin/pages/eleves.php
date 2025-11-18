<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

// Traitement des actions (Ajouter, Modifier, Supprimer)
$message = '';
$error = '';

// SUPPRESSION
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM eleves WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Élève supprimé avec succès !";
    } else {
        $error = "Erreur lors de la suppression.";
    }
}

// AJOUT/MODIFICATION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $date_naissance = $_POST['date_naissance'];
    $lieu_naissance = trim($_POST['lieu_naissance']);
    $genre = $_POST['genre'];
    $adresse = trim($_POST['adresse']);
    $nom_pere = trim($_POST['nom_pere']);
    $nom_mere = trim($_POST['nom_mere']);
    $telephone_urgence = trim($_POST['telephone_urgence']);
    $classe_id = isset($_POST['classe_id']) ? intval($_POST['classe_id']) : 0;
    
    if ($id > 0) {
        // MODIFICATION (pas besoin de matricule)
        $stmt = $conn->prepare("UPDATE eleves SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?, genre=?, adresse=?, nom_pere=?, nom_mere=?, telephone_urgence=? WHERE id=?");
        $stmt->bind_param("sssssssssi", $nom, $prenom, $date_naissance, $lieu_naissance, $genre, $adresse, $nom_pere, $nom_mere, $telephone_urgence, $id);
        $stmt->execute();
        
        // Mettre à jour l'inscription si classe changée
        if ($classe_id > 0) {
            $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
            if ($annee_result->num_rows > 0) {
                $annee = $annee_result->fetch_assoc();
                $annee_scolaire_id = $annee['id'];
                
                // Vérifier si une inscription existe déjà pour cette année
                $check_inscription = $conn->query("SELECT id FROM inscriptions WHERE eleve_id = $id AND annee_scolaire_id = $annee_scolaire_id");
                
                if ($check_inscription->num_rows > 0) {
                    // Mettre à jour la classe
                    $conn->query("UPDATE inscriptions SET classe_id = $classe_id WHERE eleve_id = $id AND annee_scolaire_id = $annee_scolaire_id");
                } else {
                    // Créer nouvelle inscription
                    $conn->query("INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, statut) VALUES ($id, $classe_id, $annee_scolaire_id, 'active')");
                }
            }
        }
        
        $message = "Élève modifié avec succès !";
        header("Location: eleves.php?success=1");
        exit();
    } else {
        // AJOUT - Génération automatique du matricule
        $annee_courante = date('Y');
        
        // Trouver le dernier numéro de matricule pour l'année en cours
        $result = $conn->query("SELECT matricule FROM eleves WHERE matricule LIKE 'EL{$annee_courante}%' ORDER BY matricule DESC LIMIT 1");
        
        if ($result->num_rows > 0) {
            $last_matricule = $result->fetch_assoc()['matricule'];
            // Extraire le numéro et l'incrémenter
            $last_number = intval(substr($last_matricule, -4));
            $new_number = $last_number + 1;
        } else {
            // Premier élève de l'année
            $new_number = 1;
        }
        
        // Formater le matricule : EL2024-0001
        $matricule = 'EL' . $annee_courante . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare("INSERT INTO eleves (matricule, nom, prenom, date_naissance, lieu_naissance, genre, adresse, nom_pere, nom_mere, telephone_urgence, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')");
        $stmt->bind_param("ssssssssss", $matricule, $nom, $prenom, $date_naissance, $lieu_naissance, $genre, $adresse, $nom_pere, $nom_mere, $telephone_urgence);
        $stmt->execute();
        $eleve_id = $conn->insert_id;
        
        // Créer l'inscription dans la classe
        if ($classe_id > 0) {
            $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
            if ($annee_result->num_rows > 0) {
                $annee = $annee_result->fetch_assoc();
                $annee_scolaire_id = $annee['id'];
                
                $stmt_inscription = $conn->prepare("INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, statut) VALUES (?, ?, ?, 'active')");
                $stmt_inscription->bind_param("iii", $eleve_id, $classe_id, $annee_scolaire_id);
                $stmt_inscription->execute();
            }
        }
        
        $message = "Élève ajouté avec succès ! Matricule généré : " . $matricule;
        header("Location: eleves.php?success=1&matricule=" . urlencode($matricule));
        exit();
    }
}

// Recherche et filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$classe_filter = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';

$sql = "SELECT e.*, c.nom as classe_nom FROM eleves e 
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'active'
        LEFT JOIN classes c ON i.classe_id = c.id
        WHERE 1=1";

if ($search) {
    $search_safe = $conn->real_escape_string($search);
    $sql .= " AND (e.nom LIKE '%$search_safe%' OR e.prenom LIKE '%$search_safe%' OR e.matricule LIKE '%$search_safe%')";
}
if ($classe_filter > 0) {
    $sql .= " AND i.classe_id = $classe_filter";
}
if ($statut_filter) {
    $sql .= " AND e.statut = '$statut_filter'";
}

$sql .= " ORDER BY e.nom, e.prenom";
$eleves = $conn->query($sql);

// Liste des classes pour le filtre
$classes = $conn->query("SELECT * FROM classes ORDER BY nom");

// Élève à modifier
$eleve_edit = null;
$eleve_classe_id = 0;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT e.*, i.classe_id FROM eleves e LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'active' WHERE e.id = $id");
    $eleve_edit = $result->fetch_assoc();
    if ($eleve_edit) {
        $eleve_classe_id = $eleve_edit['classe_id'] ?? 0;
    }
}

// Liste des classes pour le formulaire
$classes_form = $conn->query("SELECT * FROM classes ORDER BY nom");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Élèves | École Mandroso</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    
    <!-- Navigation -->
    <?php include('../includes/nav.php'); ?>
    
    <div class="flex">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Contenu principal -->
        <main class="main-content flex-1 p-4 md:p-8 mt-16">
            
            <!-- Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <i class="fas fa-check-circle mr-2"></i> Opération réussie !
                    <?php if (isset($_GET['matricule'])): ?>
                        <br><strong>Matricule généré automatiquement : <?= htmlspecialchars($_GET['matricule']) ?></strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <!-- En-tête -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-user-graduate text-blue-600 mr-2"></i>
                        Gestion des Élèves
                    </h1>
                    <p class="text-gray-600">Liste et gestion des élèves de l'école</p>
                </div>
                <button onclick="toggleModal()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i> Ajouter un élève
                </button>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Nom, prénom ou matricule..."
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Classe</label>
                        <select name="classe" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Toutes les classes</option>
                            <?php while($classe = $classes->fetch_assoc()): ?>
                                <option value="<?= $classe['id'] ?>" <?= $classe_filter == $classe['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($classe['nom']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select name="statut" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Tous</option>
                            <option value="actif" <?= $statut_filter == 'actif' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactif" <?= $statut_filter == 'inactif' ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Liste des élèves -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom & Prénom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Naissance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Classe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($eleves->num_rows > 0): ?>
                                <?php while($eleve = $eleves->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
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
                                                    <div class="text-sm text-gray-500">
                                                        <?= $eleve['genre'] == 'M' ? 'Masculin' : 'Féminin' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($eleve['date_naissance'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= $eleve['classe_nom'] ? htmlspecialchars($eleve['classe_nom']) : 'Non inscrit' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($eleve['statut'] == 'actif'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Actif
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Inactif
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="?edit=<?= $eleve['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete=<?= $eleve['id'] ?>" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet élève ?')"
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2"></i>
                                        <p>Aucun élève trouvé</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>
    
    <!-- Modal Ajout/Modification -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?= $eleve_edit ? 'Modifier l\'élève' : 'Ajouter un élève' ?>
                    </h2>
                    <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-6">
                    <?php if ($eleve_edit): ?>
                        <input type="hidden" name="id" value="<?= $eleve_edit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if (!$eleve_edit): ?>
                            <!-- Info matricule auto pour nouvel élève -->
                            <div class="md:col-span-2 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                                    <div>
                                        <p class="font-medium text-blue-800">Matricule automatique</p>
                                        <p class="text-sm text-blue-700">Le matricule sera généré automatiquement au format : <strong>EL<?= date('Y') ?>-0001</strong></p>
                                        <p class="text-xs text-blue-600 mt-1">Exemple : EL2024-0001, EL2024-0002, etc.</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Afficher le matricule pour modification -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Matricule</label>
                                <input type="text" value="<?= htmlspecialchars($eleve_edit['matricule']) ?>" disabled
                                       class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-600 cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-1">Le matricule ne peut pas être modifié</p>
                            </div>
                        <?php endif; ?>
                        
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom complet de l'élève *</label>
                            <input type="text" name="nom_complet" required 
                                   placeholder="Ex: Hajaniaina Hyacinthe"
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['nom'] . ' ' . $eleve_edit['prenom']) : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Format : NOM en MAJUSCULES suivi du(des) prénom(s). Exemple : Hajaniaina Hyacinthe
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Classe *</label>
                            <select name="classe_id" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Sélectionner une classe</option>
                                <?php 
                                $classes_form_display = $conn->query("SELECT * FROM classes ORDER BY nom");
                                while($classe = $classes_form_display->fetch_assoc()): 
                                ?>
                                    <option value="<?= $classe['id'] ?>" <?= ($eleve_classe_id == $classe['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($classe['nom']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de naissance *</label>
                            <input type="date" name="date_naissance" required
                                   value="<?= $eleve_edit ? $eleve_edit['date_naissance'] : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lieu de naissance</label>
                            <input type="text" name="lieu_naissance"
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['lieu_naissance']) : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Genre *</label>
                            <select name="genre" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="M" <?= ($eleve_edit && $eleve_edit['genre'] == 'M') ? 'selected' : '' ?>>Masculin</option>
                                <option value="F" <?= ($eleve_edit && $eleve_edit['genre'] == 'F') ? 'selected' : '' ?>>Féminin</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                            <input type="text" name="adresse"
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['adresse']) : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom du père</label>
                            <input type="text" name="nom_pere"
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['nom_pere']) : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la mère</label>
                            <input type="text" name="nom_mere"
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['nom_mere']) : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone urgence *</label>
                            <input type="tel" name="telephone_urgence" required
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['telephone_urgence']) : '' ?>"
                                   placeholder="+261 34 12 345 67"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <button type="button" onclick="toggleModal()" 
                                class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>
                            <?= $eleve_edit ? 'Modifier' : 'Ajouter' ?>
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
        
        <?php if ($eleve_edit): ?>
            toggleModal();
        <?php endif; ?>
    </script>
    
</body>
</html>