<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

$message = '';
$error = '';

// MISE À JOUR DES PARAMÈTRES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_ecole':
                $nom_ecole = trim($_POST['nom_ecole']);
                $adresse = trim($_POST['adresse']);
                $telephone = trim($_POST['telephone']);
                $email = trim($_POST['email']);
                $devise = trim($_POST['devise']);
                $directeur_nom = trim($_POST['directeur_nom']);
                
                // Gestion du logo
                $logo = '';
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                    $upload_dir = '../../assets/uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $logo = 'logo_' . time() . '_' . $_FILES['logo']['name'];
                    move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo);
                    
                    $stmt = $conn->prepare("UPDATE parametres_ecole SET nom_ecole=?, logo=?, adresse=?, telephone=?, email=?, devise=?, directeur_nom=? WHERE id=1");
                    $stmt->bind_param("sssssss", $nom_ecole, $logo, $adresse, $telephone, $email, $devise, $directeur_nom);
                } else {
                    $stmt = $conn->prepare("UPDATE parametres_ecole SET nom_ecole=?, adresse=?, telephone=?, email=?, devise=?, directeur_nom=? WHERE id=1");
                    $stmt->bind_param("ssssss", $nom_ecole, $adresse, $telephone, $email, $devise, $directeur_nom);
                }
                
                if ($stmt->execute()) {
                    $message = "Informations de l'école mises à jour avec succès !";
                } else {
                    $error = "Erreur lors de la mise à jour.";
                }
                break;
                
            case 'update_annee':
                $annee_id = intval($_POST['annee_id']);
                $libelle = trim($_POST['libelle']);
                $date_debut = $_POST['date_debut'];
                $date_fin = $_POST['date_fin'];
                
                if ($annee_id > 0) {
                    $stmt = $conn->prepare("UPDATE annees_scolaires SET libelle=?, date_debut=?, date_fin=? WHERE id=?");
                    $stmt->bind_param("sssi", $libelle, $date_debut, $date_fin, $annee_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO annees_scolaires (libelle, date_debut, date_fin, actif) VALUES (?, ?, ?, 0)");
                    $stmt->bind_param("sss", $libelle, $date_debut, $date_fin);
                }
                
                if ($stmt->execute()) {
                    $message = "Année scolaire enregistrée avec succès !";
                } else {
                    $error = "Erreur lors de l'enregistrement.";
                }
                break;
                
            case 'activer_annee':
                $annee_id = intval($_POST['annee_id']);
                $conn->query("UPDATE annees_scolaires SET actif = 0");
                $conn->query("UPDATE annees_scolaires SET actif = 1 WHERE id = $annee_id");
                $message = "Année scolaire activée avec succès !";
                break;
                
            case 'add_matiere':
                $nom = trim($_POST['nom']);
                $code = trim($_POST['code']);
                $coefficient = floatval($_POST['coefficient']);
                $niveau = $_POST['niveau'];
                $description = trim($_POST['description']);
                
                $stmt = $conn->prepare("INSERT INTO matieres (nom, code, coefficient, niveau, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdss", $nom, $code, $coefficient, $niveau, $description);
                
                if ($stmt->execute()) {
                    $message = "Matière ajoutée avec succès !";
                } else {
                    $error = "Erreur : Cette matière existe peut-être déjà.";
                }
                break;
                
            case 'delete_matiere':
                $matiere_id = intval($_POST['matiere_id']);
                $conn->query("DELETE FROM matieres WHERE id = $matiere_id");
                $message = "Matière supprimée avec succès !";
                break;
                
            // ENSEIGNANTS
            case 'add_enseignant':
                $matricule = trim($_POST['matricule']);
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $specialite = trim($_POST['specialite']);
                $telephone = trim($_POST['telephone']);
                $email = trim($_POST['email']);
                $date_embauche = $_POST['date_embauche'];
                
                // Créer l'utilisateur
                $username = strtolower(substr($prenom, 0, 1) . $nom);
                $password_hash = password_hash('password123', PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, telephone, actif) VALUES (?, ?, ?, 'enseignant', ?, 1)");
                $stmt->bind_param("ssss", $username, $email, $password_hash, $telephone);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    $stmt = $conn->prepare("INSERT INTO enseignants (user_id, matricule, nom, prenom, specialite, telephone, email_pro, date_embauche, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'actif')");
                    $stmt->bind_param("isssssss", $user_id, $matricule, $nom, $prenom, $specialite, $telephone, $email, $date_embauche);
                    $stmt->execute();
                    
                    $message = "Enseignant ajouté avec succès !";
                } else {
                    $error = "Erreur : Cet enseignant existe peut-être déjà.";
                }
                break;
                
            case 'delete_enseignant':
                $enseignant_id = intval($_POST['enseignant_id']);
                $result = $conn->query("SELECT user_id FROM enseignants WHERE id = $enseignant_id");
                $ens = $result->fetch_assoc();
                if ($ens && $ens['user_id']) {
                    $conn->query("DELETE FROM users WHERE id = " . $ens['user_id']);
                }
                $conn->query("DELETE FROM enseignants WHERE id = $enseignant_id");
                $message = "Enseignant supprimé avec succès !";
                break;
                
            // CLASSES
            case 'add_classe':
                $nom = trim($_POST['nom']);
                $niveau = $_POST['niveau'];
                $section = trim($_POST['section']);
                $capacite_max = intval($_POST['capacite_max']);
                
                $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
                $annee = $annee_result->fetch_assoc();
                $annee_scolaire_id = $annee['id'];
                
                $stmt = $conn->prepare("INSERT INTO classes (nom, niveau, section, capacite_max, annee_scolaire_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssii", $nom, $niveau, $section, $capacite_max, $annee_scolaire_id);
                
                if ($stmt->execute()) {
                    $message = "Classe ajoutée avec succès !";
                } else {
                    $error = "Erreur lors de l'ajout de la classe.";
                }
                break;
                
            case 'delete_classe':
                $classe_id = intval($_POST['classe_id']);
                $conn->query("DELETE FROM classes WHERE id = $classe_id");
                $message = "Classe supprimée avec succès !";
                break;
                
            case 'update_password':
                $old_password = $_POST['old_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($new_password !== $confirm_password) {
                    $error = "Les mots de passe ne correspondent pas.";
                } else {
                    $user_id = $_SESSION['user_id'];
                    $result = $conn->query("SELECT password_hash FROM users WHERE id = $user_id");
                    $user = $result->fetch_assoc();
                    
                    if (password_verify($old_password, $user['password_hash'])) {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $conn->query("UPDATE users SET password_hash = '$new_hash' WHERE id = $user_id");
                        $message = "Mot de passe modifié avec succès !";
                    } else {
                        $error = "Ancien mot de passe incorrect.";
                    }
                }
                break;
        }
    }
}

// Récupération des paramètres
$params_result = $conn->query("SELECT * FROM parametres_ecole WHERE id = 1");
if ($params_result->num_rows == 0) {
    $conn->query("INSERT INTO parametres_ecole (nom_ecole, devise) VALUES ('École Mandroso', 'Excellence et Développement')");
    $params_result = $conn->query("SELECT * FROM parametres_ecole WHERE id = 1");
}
$params = $params_result->fetch_assoc();

// Années scolaires
$annees = $conn->query("SELECT * FROM annees_scolaires ORDER BY date_debut DESC");

// Matières
$matieres = $conn->query("SELECT * FROM matieres ORDER BY niveau, nom");

// Enseignants
$enseignants = $conn->query("SELECT e.*, u.email FROM enseignants e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.nom");

// Classes
$classes = $conn->query("
    SELECT c.*, 
           COUNT(i.id) as nb_eleves,
           a.libelle as annee_libelle
    FROM classes c
    LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'active'
    LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id
    GROUP BY c.id
    ORDER BY FIELD(c.niveau, 'maternelle', 'primaire', 'college', 'lycee'), c.nom
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres | École Mandroso</title>
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-style.css">
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
            
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">
                    <i class="fas fa-cog text-blue-600 mr-2"></i>
                    Paramètres
                </h1>
                <p class="text-gray-600">Configuration générale de l'école</p>
            </div>
            
            <!-- Onglets -->
            <div class="mb-6 border-b border-gray-200 overflow-x-auto">
                <nav class="flex space-x-2 md:space-x-4">
                    <button onclick="showTab('ecole')" id="tab-ecole" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium border-b-2 border-blue-600 text-blue-600">
                        <i class="fas fa-school mr-1 md:mr-2"></i> <span class="hidden sm:inline">Informations</span> École
                    </button>
                    <button onclick="showTab('annees')" id="tab-annees" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent">
                        <i class="fas fa-calendar-alt mr-1 md:mr-2"></i> Années <span class="hidden sm:inline">Scolaires</span>
                    </button>
                    <button onclick="showTab('matieres')" id="tab-matieres" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent">
                        <i class="fas fa-book mr-1 md:mr-2"></i> Matières
                    </button>
                    <button onclick="showTab('enseignants')" id="tab-enseignants" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent">
                        <i class="fas fa-chalkboard-teacher mr-1 md:mr-2"></i> Enseignants
                    </button>
                    <button onclick="showTab('classes')" id="tab-classes" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent">
                        <i class="fas fa-door-open mr-1 md:mr-2"></i> Classes
                    </button>
                    <button onclick="showTab('securite')" id="tab-securite" class="tab-button whitespace-nowrap px-3 md:px-4 py-2 text-sm md:text-base font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent">
                        <i class="fas fa-lock mr-1 md:mr-2"></i> Sécurité
                    </button>
                </nav>
            </div>
            
            <!-- Contenu des onglets -->
            
            <!-- Onglet Informations École -->
            <div id="content-ecole" class="tab-content">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Informations de l'école</h2>
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="update_ecole">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Logo de l'école</label>
                                <?php if ($params['logo']): ?>
                                    <div class="mb-4">
                                        <img src="../../assets/uploads/<?= $params['logo'] ?>" alt="Logo" class="h-24 rounded">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo" accept="image/*" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Format accepté : JPG, PNG (max 2MB)</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom de l'école *</label>
                                <input type="text" name="nom_ecole" required value="<?= htmlspecialchars($params['nom_ecole']) ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom du directeur</label>
                                <input type="text" name="directeur_nom" value=""
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                                <input type="text" name="adresse" value=""
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                <input type="tel" name="telephone" value=""
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="email" value=""
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Devise de l'école</label>
                                <input type="text" name="devise" value="<?= htmlspecialchars($params['devise']) ?>"
                                       placeholder="Ex: Excellence et Développement"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Onglet Années Scolaires -->
            <div id="content-annees" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Ajouter une année scolaire</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_annee">
                            <input type="hidden" name="annee_id" value="0">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Libellé *</label>
                                <input type="text" name="libelle" required placeholder="Ex: 2024-2025"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date de début *</label>
                                <input type="date" name="date_debut" required
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date de fin *</label>
                                <input type="date" name="date_fin" required
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i> Ajouter
                            </button>
                        </form>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Années scolaires</h2>
                        <div class="space-y-3">
                            <?php 
                            $annees_display = $conn->query("SELECT * FROM annees_scolaires ORDER BY date_debut DESC");
                            while($annee = $annees_display->fetch_assoc()): 
                            ?>
                                <div class="flex items-center justify-between p-4 border rounded-lg <?= $annee['actif'] ? 'bg-green-50 border-green-200' : 'bg-gray-50' ?>">
                                    <div>
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($annee['libelle']) ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($annee['date_debut'])) ?> - <?= date('d/m/Y', strtotime($annee['date_fin'])) ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <?php if ($annee['actif']): ?>
                                            <span class="px-3 py-1 bg-green-600 text-white text-xs rounded-full">Active</span>
                                        <?php else: ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="activer_annee">
                                                <input type="hidden" name="annee_id" value="<?= $annee['id'] ?>">
                                                <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-xs rounded-full hover:bg-blue-700">
                                                    Activer
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Matières -->
            <div id="content-matieres" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Ajouter une matière</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_matiere">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la matière *</label>
                                <input type="text" name="nom" required placeholder="Ex: Mathématiques"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Code *</label>
                                <input type="text" name="code" required placeholder="Ex: MATH"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Coefficient *</label>
                                <input type="number" step="0.1" name="coefficient" required value="1"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Niveau *</label>
                                <select name="niveau" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="maternelle">Maternelle</option>
                                    <option value="primaire">Primaire</option>
                                    <option value="college">Collège</option>
                                    <option value="lycee">Lycée</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i> Ajouter
                            </button>
                        </form>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des matières</h2>
                        <div class="space-y-2 max-h-[600px] overflow-y-auto">
                            <?php 
                            $matieres_display = $conn->query("SELECT * FROM matieres ORDER BY niveau, nom");
                            while($matiere = $matieres_display->fetch_assoc()): 
                            ?>
                                <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($matiere['nom']) ?></div>
                                        <div class="text-sm text-gray-500">
                                            Code: <?= $matiere['code'] ?> | Coef: <?= $matiere['coefficient'] ?> | <?= ucfirst($matiere['niveau']) ?>
                                        </div>
                                    </div>
                                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette matière ?')">
                                        <input type="hidden" name="action" value="delete_matiere">
                                        <input type="hidden" name="matiere_id" value="<?= $matiere['id'] ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Enseignants -->
            <div id="content-enseignants" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Ajouter un enseignant</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_enseignant">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                                    <input type="text" name="nom" required
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                                    <input type="text" name="prenom" required
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Spécialité *</label>
                                    <input type="text" name="specialite" required placeholder="Ex: Mathématiques"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                    <input type="email" name="email" required
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone *</label>
                                    <input type="tel" name="telephone" required
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date d'embauche *</label>
                                <input type="date" name="date_embauche" required
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="bg-blue-50 border-l-4 border-blue-500 p-3 text-sm text-blue-700">
                                <i class="fas fa-info-circle mr-2"></i>
                                Mot de passe par défaut : <strong>password123</strong>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i> Ajouter
                            </button>
                        </form>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des enseignants</h2>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto">
                            <?php 
                            $enseignants_display = $conn->query("SELECT e.*, u.email FROM enseignants e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.nom");
                            while($ens = $enseignants_display->fetch_assoc()): 
                            ?>
                                <div class="border rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-800">
                                                <?= htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <div><i class="fas fa-id-badge w-4"></i> <?= $ens['matricule'] ?></div>
                                                <div><i class="fas fa-chalkboard w-4"></i> <?= htmlspecialchars($ens['specialite']) ?></div>
                                                <div><i class="fas fa-envelope w-4"></i> <?= htmlspecialchars($ens['email_pro']) ?></div>
                                                <div><i class="fas fa-phone w-4"></i> <?= htmlspecialchars($ens['telephone']) ?></div>
                                            </div>
                                            <div class="mt-2">
                                                <span class="px-2 py-1 bg-<?= $ens['statut'] == 'actif' ? 'green' : 'red' ?>-100 text-<?= $ens['statut'] == 'actif' ? 'green' : 'red' ?>-800 text-xs rounded-full">
                                                    <?= ucfirst($ens['statut']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <form method="POST" class="ml-4" onsubmit="return confirm('Supprimer cet enseignant ?')">
                                            <input type="hidden" name="action" value="delete_enseignant">
                                            <input type="hidden" name="enseignant_id" value="<?= $ens['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xl">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Classes -->
            <div id="content-classes" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Ajouter une classe</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_classe">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la classe *</label>
                                <input type="text" name="nom" required placeholder="Ex: CP, 6ème A, Terminale S"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Niveau *</label>
                                <select name="niveau" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="maternelle">Maternelle</option>
                                    <option value="primaire">Primaire</option>
                                    <option value="college">Collège</option>
                                    <option value="lycee">Lycée</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Section</label>
                                <input type="text" name="section" placeholder="Ex: A, B, S, L"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacité maximale *</label>
                                <input type="number" name="capacite_max" required min="1" value="30"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i> Ajouter
                            </button>
                        </form>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des classes</h2>
                        <div class="space-y-3 max-h-[600px] overflow-y-auto">
                            <?php 
                            $classes_display = $conn->query("
                                SELECT c.*, 
                                       COUNT(i.id) as nb_eleves,
                                       a.libelle as annee_libelle
                                FROM classes c
                                LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.statut = 'active'
                                LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id
                                GROUP BY c.id
                                ORDER BY FIELD(c.niveau, 'maternelle', 'primaire', 'college', 'lycee'), c.nom
                            ");
                            while($classe = $classes_display->fetch_assoc()): 
                            ?>
                                <div class="border rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between mb-2">
                                                <div>
                                                    <span class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($classe['nom']) ?></span>
                                                    <?php if ($classe['section']): ?>
                                                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                            <?= htmlspecialchars($classe['section']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-sm text-gray-500 space-y-1">
                                                <div><i class="fas fa-layer-group w-4"></i> <?= ucfirst($classe['niveau']) ?></div>
                                                <div><i class="fas fa-users w-4"></i> <?= $classe['nb_eleves'] ?> / <?= $classe['capacite_max'] ?> élèves</div>
                                                <div><i class="fas fa-calendar w-4"></i> <?= $classe['annee_libelle'] ?></div>
                                            </div>
                                            <div class="mt-2">
                                                <?php 
                                                $percent = $classe['capacite_max'] > 0 ? ($classe['nb_eleves'] / $classe['capacite_max']) * 100 : 0;
                                                $color = $percent >= 90 ? 'bg-red-500' : ($percent >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                                                ?>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="<?= $color ?> h-2 rounded-full transition-all" style="width: <?= min($percent, 100) ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <form method="POST" class="ml-4" onsubmit="return confirm('Supprimer cette classe ?')">
                                            <input type="hidden" name="action" value="delete_classe">
                                            <input type="hidden" name="classe_id" value="<?= $classe['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xl">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Onglet Sécurité -->
            <div id="content-securite" class="tab-content hidden">
                <div class="max-w-2xl">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Changer le mot de passe</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ancien mot de passe *</label>
                                <input type="password" name="old_password" required
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nouveau mot de passe *</label>
                                <input type="password" name="new_password" required minlength="6"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 caractères</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe *</label>
                                <input type="password" name="confirm_password" required minlength="6"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-key mr-2"></i> Modifier le mot de passe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
    
    <script>
        function showTab(tabName) {
            // Cacher tous les contenus
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Réinitialiser tous les boutons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-600', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Afficher le contenu sélectionné
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Activer le bouton sélectionné
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.remove('border-transparent', 'text-gray-500');
            activeButton.classList.add('border-blue-600', 'text-blue-600');
        }
    </script>
    
</body>
</html>