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
    
    // Traiter le nom complet (séparer nom et prénom)
    $nom_complet = trim($_POST['nom_complet']);
    $parts = explode(' ', $nom_complet, 2);
    $nom = $parts[0]; // Premier mot = NOM
    $prenom = isset($parts[1]) ? $parts[1] : ''; // Reste = Prénom(s)
    
    $date_naissance = $_POST['date_naissance'];
    $lieu_naissance = trim($_POST['lieu_naissance']);
    $genre = $_POST['genre'];
    $adresse = trim($_POST['adresse']);
    $nom_pere = trim($_POST['nom_pere']);
    $nom_mere = trim($_POST['nom_mere']);
    $telephone_urgence = trim($_POST['telephone_urgence']);
    $classe_id = isset($_POST['classe_id']) ? intval($_POST['classe_id']) : 0;
    
    if ($id == 0) {
        // NOUVELLE INSCRIPTION - La classe est obligatoire
        if (empty($nom)) {
            $error = "Le nom est obligatoire.";
        } elseif ($classe_id <= 0) {
            $error = "Veuillez sélectionner une classe. L'inscription nécessite une classe.";
        } else {
            // Génération automatique du matricule
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
            
            // Insérer l'élève avec statut actif
            $stmt = $conn->prepare("INSERT INTO eleves (matricule, nom, prenom, date_naissance, lieu_naissance, genre, adresse, nom_pere, nom_mere, telephone_urgence, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')");
            $stmt->bind_param("ssssssssss", $matricule, $nom, $prenom, $date_naissance, $lieu_naissance, $genre, $adresse, $nom_pere, $nom_mere, $telephone_urgence);
            
            if ($stmt->execute()) {
                $eleve_id = $conn->insert_id;
                
                // Créer l'inscription dans la classe (obligatoire)
                $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
                if ($annee_result->num_rows > 0) {
                    $annee = $annee_result->fetch_assoc();
                    $annee_scolaire_id = $annee['id'];
                    
                    $stmt_inscription = $conn->prepare("INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, statut, date_inscription) VALUES (?, ?, ?, 'valide', CURDATE())");
                    $stmt_inscription->bind_param("iii", $eleve_id, $classe_id, $annee_scolaire_id);
                    
                    if ($stmt_inscription->execute()) {
                        // Vérifier que l'inscription a bien été créée
                        $check_inscription = $conn->query("SELECT id FROM inscriptions WHERE eleve_id = $eleve_id AND annee_scolaire_id = $annee_scolaire_id AND statut = 'valide'");
                        if ($check_inscription->num_rows > 0) {
                            
                            // ===== Création des frais de scolarité et paiement d'inscription (obligatoire) =====
                            $type_frais_inscription = isset($_POST['type_frais_inscription']) ? trim($_POST['type_frais_inscription']) : '';
                            // Récupérer le montant depuis le champ hidden ou nettoyer la valeur formatée
                            $montant_frais_inscription_raw = isset($_POST['montant_frais_inscription_value']) ? $_POST['montant_frais_inscription_value'] : (isset($_POST['montant_frais_inscription']) ? $_POST['montant_frais_inscription'] : '0');
                            $montant_frais_inscription = floatval(str_replace([' ', ','], ['', '.'], $montant_frais_inscription_raw));
                            $date_paiement_inscription = isset($_POST['date_paiement_inscription']) ? $_POST['date_paiement_inscription'] : date('Y-m-d');
                            $mode_paiement_inscription = isset($_POST['mode_paiement_inscription']) ? $_POST['mode_paiement_inscription'] : 'especes';
                            $ref_transaction_inscription = isset($_POST['reference_transaction_inscription']) ? trim($_POST['reference_transaction_inscription']) : '';
                            
                            // Validation des champs de paiement (obligatoires)
                            if (empty($type_frais_inscription)) {
                                $error = "Le type de frais d'inscription est obligatoire.";
                            } elseif ($montant_frais_inscription <= 0) {
                                $error = "Le montant d'inscription doit être supérieur à 0.";
                            } elseif (empty($date_paiement_inscription)) {
                                $error = "La date de paiement est obligatoire.";
                            } elseif (empty($mode_paiement_inscription)) {
                                $error = "Le mode de paiement est obligatoire.";
                            }
                            
                            // Si pas d'erreur, créer le frais de scolarité et le paiement
                            if (empty($error)) {
                                // Date d'échéance = date de paiement (pour l'inscription)
                                $date_echeance_inscription = $date_paiement_inscription;
                                
                                // Créer le frais de scolarité avec statut "paye" car c'est un paiement direct
                                $statut_frais = 'paye';
                                
                                $stmt_frais = $conn->prepare("INSERT INTO frais_scolarite (eleve_id, annee_scolaire_id, type_frais, montant, date_echeance, statut) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmt_frais->bind_param("iisdss", $eleve_id, $annee_scolaire_id, $type_frais_inscription, $montant_frais_inscription, $date_echeance_inscription, $statut_frais);
                                
                                if ($stmt_frais->execute()) {
                                    $frais_scolarite_id = $conn->insert_id;
                                    
                                    // Créer automatiquement le paiement
                                    if (empty($ref_transaction_inscription)) {
                                        $ref_transaction_inscription = 'INSCRIPTION-' . $matricule . '-' . date('YmdHis');
                                    }
                                    
                                    $stmt_paiement = $conn->prepare("INSERT INTO paiements (frais_scolarite_id, montant_paye, date_paiement, mode_paiement, reference_transaction) VALUES (?, ?, ?, ?, ?)");
                                    $stmt_paiement->bind_param("idsss", $frais_scolarite_id, $montant_frais_inscription, $date_paiement_inscription, $mode_paiement_inscription, $ref_transaction_inscription);
                                    
                                    if ($stmt_paiement->execute()) {
                                        $stmt_paiement->close();
                                    } else {
                                        $error = "Erreur lors de l'enregistrement du paiement : " . htmlspecialchars($stmt_paiement->error);
                                    }
                                    
                                    $stmt_frais->close();
                                } else {
                                    $error = "Erreur lors de l'enregistrement des frais de scolarité : " . htmlspecialchars($stmt_frais->error);
                                }
                            }
                            // ===== FIN Création des frais de scolarité et paiement d'inscription =====
                            
                            // Redirection seulement si pas d'erreur
                            if (empty($error)) {
                                header("Location: eleves.php?success=1&matricule=" . urlencode($matricule));
                                exit();
                            }
                        } else {
                            $error = "Erreur : L'inscription n'a pas pu être créée correctement.";
                        }
                    } else {
                        $error = "Erreur lors de la création de l'inscription : " . htmlspecialchars($stmt_inscription->error);
                    }
                } else {
                    $error = "Aucune année scolaire active trouvée. Veuillez activer une année scolaire.";
                }
            } else {
                $error = "Erreur lors de l'ajout de l'élève : " . htmlspecialchars($stmt->error);
            }
        }
    } elseif ($id > 0) {
        // MODIFICATION
        $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
        
        // Si l'élève est actif, la classe est obligatoire
        if ($statut == 'actif' && $classe_id <= 0) {
            $error = "Un élève actif doit avoir une classe. Veuillez sélectionner une classe ou changer le statut de l'élève.";
        } else {
            // Mettre à jour l'élève
            $stmt = $conn->prepare("UPDATE eleves SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?, genre=?, adresse=?, nom_pere=?, nom_mere=?, telephone_urgence=?, statut=? WHERE id=?");
            $stmt->bind_param("ssssssssssi", $nom, $prenom, $date_naissance, $lieu_naissance, $genre, $adresse, $nom_pere, $nom_mere, $telephone_urgence, $statut, $id);
            $stmt->execute();
            
            // Mettre à jour l'inscription
            $annee_result = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
            if ($annee_result->num_rows > 0) {
                $annee = $annee_result->fetch_assoc();
                $annee_scolaire_id = $annee['id'];
                
                // Vérifier si une inscription existe déjà pour cette année
                $check_inscription = $conn->query("SELECT id FROM inscriptions WHERE eleve_id = $id AND annee_scolaire_id = $annee_scolaire_id");
                
                if ($check_inscription->num_rows > 0) {
                    if ($classe_id > 0) {
                        // Mettre à jour la classe
                        $stmt_inscription = $conn->prepare("UPDATE inscriptions SET classe_id = ?, statut = 'valide' WHERE eleve_id = ? AND annee_scolaire_id = ?");
                        $stmt_inscription->bind_param("iii", $classe_id, $id, $annee_scolaire_id);
                        $stmt_inscription->execute();
                    } else {
                        // Désinscrire l'élève (mettre statut à annule) - seulement si l'élève n'est pas actif
                        if ($statut != 'actif') {
                            $stmt_inscription = $conn->prepare("UPDATE inscriptions SET statut = 'annule' WHERE eleve_id = ? AND annee_scolaire_id = ?");
                            $stmt_inscription->bind_param("ii", $id, $annee_scolaire_id);
                            $stmt_inscription->execute();
                        }
                    }
                } else {
                    // Créer nouvelle inscription seulement si une classe est sélectionnée
                    if ($classe_id > 0) {
                        $stmt_inscription = $conn->prepare("INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, statut, date_inscription) VALUES (?, ?, ?, 'valide', CURDATE())");
                        $stmt_inscription->bind_param("iii", $id, $classe_id, $annee_scolaire_id);
                        $stmt_inscription->execute();
                    }
                }
            }
            
            if (empty($error)) {
                $message = "Élève modifié avec succès !";
                header("Location: eleves.php?success=1");
                exit();
            }
        }
    }
}

// Recherche et filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$classe_filter = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';

// Récupérer l'année scolaire active
$annee_active = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
$annee_active_id = 0;
if ($annee_active->num_rows > 0) {
    $annee_active_id = $annee_active->fetch_assoc()['id'];
}

// Récupérer uniquement les élèves inscrits (avec inscription active)
// Afficher les élèves qui ont une inscription active pour l'année active
if ($annee_active_id > 0) {
    $sql = "SELECT e.*, c.nom as classe_nom, i.classe_id
            FROM eleves e 
            INNER JOIN inscriptions i ON e.id = i.eleve_id 
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE i.annee_scolaire_id = $annee_active_id 
            AND i.statut = 'valide'";
} else {
    // Si pas d'année active, on affiche tous les élèves avec inscription valide (peu importe l'année)
    $sql = "SELECT e.*, c.nom as classe_nom, i.classe_id
            FROM eleves e 
            INNER JOIN inscriptions i ON e.id = i.eleve_id 
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE i.statut = 'valide'";
}

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

$sql .= " GROUP BY e.id, c.nom, i.classe_id ORDER BY e.nom, e.prenom";

// Debug: Afficher la requête SQL si nécessaire (à supprimer en production)
// error_log("SQL Query: " . $sql);

$eleves = $conn->query($sql);

// Debug: Vérifier s'il y a une erreur SQL ou si aucun résultat
// Ne pas afficher d'erreur si on vient d'une redirection de succès
if (!isset($_GET['success'])) {
    if (!$eleves) {
        $error = "Erreur SQL : " . $conn->error . "<br>Requête : " . htmlspecialchars($sql);
    } elseif ($eleves->num_rows == 0 && empty($error)) {
        // Vérifier s'il y a des élèves dans la base mais sans inscription active
        $total_eleves = $conn->query("SELECT COUNT(*) as total FROM eleves")->fetch_assoc()['total'];
        if ($total_eleves > 0) {
            if ($annee_active_id == 0) {
                $total_inscriptions = $conn->query("SELECT COUNT(*) as total FROM inscriptions WHERE statut = 'valide'")->fetch_assoc()['total'];
                if ($total_inscriptions == 0) {
                    $error = "Aucun élève inscrit trouvé. Les élèves existants n'ont pas d'inscription active. Veuillez inscrire les élèves via le bouton 'Nouvelle inscription'.";
                } else {
                    $error = "Aucune année scolaire active. Veuillez activer une année scolaire dans les paramètres pour afficher les élèves inscrits.";
                }
            } else {
                $total_inscriptions_annee = $conn->query("SELECT COUNT(*) as total FROM inscriptions WHERE statut = 'valide' AND annee_scolaire_id = $annee_active_id")->fetch_assoc()['total'];
                if ($total_inscriptions_annee == 0) {
                    $error = "Aucun élève inscrit trouvé pour l'année scolaire active. Les élèves existants n'ont pas d'inscription active pour cette année. Veuillez inscrire les élèves via le bouton 'Nouvelle inscription'.";
                }
            }
        }
    }
}

// Debug: Vérifier s'il y a une erreur SQL
if (!$eleves) {
    $error = "Erreur SQL : " . $conn->error;
}

// Liste des classes pour le filtre
$classes = $conn->query("SELECT * FROM classes ORDER BY nom");

// Élève à modifier
$eleve_edit = null;
$eleve_classe_id = 0;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    // Récupérer l'année scolaire active
    $annee_active = $conn->query("SELECT id FROM annees_scolaires WHERE actif = 1 LIMIT 1");
    $annee_active_id = 0;
    if ($annee_active->num_rows > 0) {
        $annee_active_id = $annee_active->fetch_assoc()['id'];
    }
    $annee_condition = $annee_active_id > 0 ? "AND i.annee_scolaire_id = $annee_active_id" : "";
    $result = $conn->query("SELECT e.*, i.classe_id FROM eleves e LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.statut = 'valide' $annee_condition WHERE e.id = $id");
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
    <link rel="icon" type="image/png"  href="../assets/favicon.png">
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
                <div id="successMessage" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <i class="fas fa-check-circle mr-2"></i> 
                    <?php if (isset($_GET['matricule'])): ?>
                        Élève inscrit avec succès !
                        <br><strong>Matricule généré automatiquement : <?= htmlspecialchars($_GET['matricule']) ?></strong>
                    <?php else: ?>
                        Opération réussie !
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error && !isset($_GET['success'])): ?>
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
                <button onclick="addEleve()" class="mt-4 md:mt-0 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-user-plus mr-2"></i> Nouvelle inscription
                </button>
            </div>
            
            <!-- Filtres et recherche -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form id = "searchForm" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" 
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
                                            <button onclick="editEleve(<?= htmlspecialchars(json_encode(array_merge($eleve, ['classe_id' => $eleve['classe_id'] ?? 0]))) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>
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
    
    <!-- Modal Modification/Inscription -->
    <div id="modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 id="modalTitle" class="text-2xl font-bold text-gray-800">
                        Modifier l'élève
                    </h2>
                    <button onclick="toggleModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" id="eleveId" name="id" value="0">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Info matricule auto (pour nouvelle inscription) -->
                        <div id="matriculeInfo" class="md:col-span-2 bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
                                <div>
                                    <p class="font-medium text-blue-800">Matricule automatique</p>
                                    <p class="text-sm text-blue-700">Le matricule sera généré automatiquement au format : <strong>EL<?= date('Y') ?>-0001</strong></p>
                                    <p class="text-xs text-blue-600 mt-1">Exemple : EL2024-0001, EL2024-0002, etc.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Afficher le matricule pour modification -->
                        <div id="matriculeDisplay" class="md:col-span-2" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Matricule</label>
                            <input type="text" id="matriculeInput" disabled
                                   class="w-full px-4 py-2 border rounded-lg bg-gray-50 text-gray-600 cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1">Le matricule ne peut pas être modifié</p>
                        </div>
                        
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom complet de l'élève *</label>
                            <input type="text" name="nom_complet" required 
                                   placeholder="Ex: RAKOTO Jean Claude"
                                   value="<?= $eleve_edit ? htmlspecialchars($eleve_edit['nom'] . ' ' . $eleve_edit['prenom']) : '' ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Format : NOM en MAJUSCULES suivi du(des) prénom(s). Exemple : RAKOTO Jean Claude
                            </p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Classe *</label>
                            <select name="classe_id" id="classeSelect" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
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
                            <p id="classeRequired" class="text-xs text-red-600 mt-1" style="display: none;">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                La classe est obligatoire pour l'inscription
                            </p>
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
                            <select name="genre" id="genreSelect" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="M" <?= ($eleve_edit && $eleve_edit['genre'] == 'M') ? 'selected' : '' ?>>Masculin</option>
                                <option value="F" <?= ($eleve_edit && $eleve_edit['genre'] == 'F') ? 'selected' : '' ?>>Féminin</option>
                            </select>
                        </div>
                        
                        <div id="statutField" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Statut *</label>
                            <select name="statut" id="statutSelect" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="actif" <?= ($eleve_edit && $eleve_edit['statut'] == 'actif') ? 'selected' : '' ?>>Actif</option>
                                <option value="inactif" <?= ($eleve_edit && $eleve_edit['statut'] == 'inactif') ? 'selected' : '' ?>>Inactif</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Un élève actif doit avoir une classe
                            </p>
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
                        
                        <!-- Section Paiement d'inscription (obligatoire pour nouvelle inscription) -->
                        <div id="fraisInscriptionSection" class="md:col-span-2 border-t pt-6 mt-6" style="display: none;">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
                                Paiement d'inscription *
                            </h3>
                            <p class="text-sm text-gray-600 mb-4">
                                <i class="fas fa-info-circle mr-1"></i>
                                Le paiement d'inscription est obligatoire pour valider l'inscription d'un nouvel élève.
                            </p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de frais *</label>
                                    <select name="type_frais_inscription" id="typeFraisInscription" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">-- Sélectionner --</option>
                                        <option value="inscription">Inscription</option>
                                        <option value="uniforme">Uniforme</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Montant (Ar) *</label>
                                    <input type="text" name="montant_frais_inscription" id="montantFraisInscription" required
                                           placeholder="0"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                           oninput="formatMontant(this)"
                                           onblur="validateMontant(this)">
                                    <input type="hidden" id="montantFraisInscriptionValue" name="montant_frais_inscription_value">
                                    <p class="text-xs text-gray-500 mt-1">Saisissez le montant, il sera formaté automatiquement</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de paiement *</label>
                                    <input type="date" name="date_paiement_inscription" id="datePaiementInscription" required
                                           value="<?= date('Y-m-d') ?>"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Mode de paiement *</label>
                                    <select name="mode_paiement_inscription" id="modePaiementInscription" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="especes">Espèces</option>
                                        <option value="cheque">Chèque</option>
                                        <option value="virement">Virement</option>
                                        <option value="mobile_money">Mobile Money</option>
                                        <option value="carte_bancaire">Carte bancaire</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Référence transaction</label>
                                    <input type="text" name="reference_transaction_inscription" id="referenceTransactionInscription"
                                           placeholder="Auto-généré si vide"
                                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <button type="button" onclick="toggleModal()" 
                                class="px-6 py-2 border rounded-lg hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" id="submitButton"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-save mr-2"></i>
                            <span id="submitText">Modifier</span>
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
        
        function addEleve() {
            // Réinitialiser le formulaire pour une nouvelle inscription
            const form = document.querySelector('#modal form');
            form.reset();
            
            // Réinitialiser l'ID
            document.getElementById('eleveId').value = '0';
            
            // Afficher la section "matricule auto" et cacher la section "matricule affiché"
            const infoDiv = document.getElementById('matriculeInfo');
            const matriculeDisplayDiv = document.getElementById('matriculeDisplay');
            
            if (infoDiv) {
                infoDiv.style.display = 'block';
            }
            if (matriculeDisplayDiv) {
                matriculeDisplayDiv.style.display = 'none';
            }
            
            // Cacher le champ statut pour nouvelle inscription (toujours actif)
            document.getElementById('statutField').style.display = 'none';
            
            // Afficher la section frais d'inscription
            const fraisSection = document.getElementById('fraisInscriptionSection');
            if (fraisSection) {
                fraisSection.style.display = 'block';
            }
            
            // Réinitialiser les champs frais
            document.getElementById('typeFraisInscription').value = '';
            document.getElementById('montantFraisInscription').value = '';
            document.getElementById('montantFraisInscriptionValue').value = '';
            document.getElementById('datePaiementInscription').value = '<?= date('Y-m-d') ?>';
            document.getElementById('modePaiementInscription').value = 'especes';
            document.getElementById('referenceTransactionInscription').value = '';
            
            // Changer le titre et le bouton
            document.getElementById('modalTitle').textContent = 'Nouvelle inscription';
            document.getElementById('submitText').textContent = 'Inscrire l\'élève';
            
            // Ouvrir le modal
            toggleModal();
        }
        
        function editEleve(eleve) {
            // Remplir le formulaire avec les données de l'élève
            const form = document.querySelector('#modal form');
            
            // Mettre à jour le champ hidden id
            document.getElementById('eleveId').value = eleve.id;
            
            // Cacher la section "matricule auto" et afficher la section "matricule affiché"
            const infoDiv = document.getElementById('matriculeInfo');
            const matriculeDisplayDiv = document.getElementById('matriculeDisplay');
            const matriculeInput = document.getElementById('matriculeInput');
            
            if (infoDiv) {
                infoDiv.style.display = 'none';
            }
            if (matriculeDisplayDiv) {
                matriculeDisplayDiv.style.display = 'block';
            }
            if (matriculeInput) {
                matriculeInput.value = eleve.matricule;
            }
            
            // Remplir les champs du formulaire
            form.querySelector('input[name="nom_complet"]').value = (eleve.nom + ' ' + eleve.prenom).trim();
            form.querySelector('input[name="date_naissance"]').value = eleve.date_naissance;
            form.querySelector('input[name="lieu_naissance"]').value = eleve.lieu_naissance || '';
            form.querySelector('select[name="genre"]').value = eleve.genre;
            form.querySelector('input[name="adresse"]').value = eleve.adresse || '';
            form.querySelector('input[name="nom_pere"]').value = eleve.nom_pere || '';
            form.querySelector('input[name="nom_mere"]').value = eleve.nom_mere || '';
            form.querySelector('input[name="telephone_urgence"]').value = eleve.telephone_urgence || '';
            
            // Remplir la classe
            if (eleve.classe_id) {
                document.getElementById('classeSelect').value = eleve.classe_id;
            } else {
                document.getElementById('classeSelect').value = '';
            }
            
            // Afficher le champ statut pour la modification
            document.getElementById('statutField').style.display = 'block';
            if (eleve.statut) {
                document.getElementById('statutSelect').value = eleve.statut;
            }
            
            // Masquer la section frais d'inscription en mode modification
            const fraisSection = document.getElementById('fraisInscriptionSection');
            if (fraisSection) {
                fraisSection.style.display = 'none';
            }
            
            // Changer le titre et le bouton
            document.getElementById('modalTitle').textContent = 'Modifier l\'élève';
            document.getElementById('submitText').textContent = 'Modifier';
            
            // Ouvrir le modal
            toggleModal();
        }
        
        
        <?php if ($eleve_edit): ?>
            // Pré-remplir le formulaire si on vient de ?edit=
            const eleveEdit = <?= json_encode($eleve_edit) ?>;
            editEleve(eleveEdit);
        <?php endif; ?>
        
        // Validation : s'assurer que la classe est sélectionnée pour une nouvelle inscription ou un élève actif
        document.querySelector('#modal form').addEventListener('submit', function(e) {
            const eleveId = document.getElementById('eleveId').value;
            const classeId = document.getElementById('classeSelect').value;
            const statutSelect = document.getElementById('statutSelect');
            const statut = statutSelect ? statutSelect.value : 'actif';
            
            // Si c'est une nouvelle inscription (id = 0), la classe est obligatoire
            if (eleveId == '0' && classeId == '') {
                e.preventDefault();
                document.getElementById('classeRequired').style.display = 'block';
                document.getElementById('classeSelect').focus();
                return false;
            }
            
            // Si c'est une modification et l'élève est actif, la classe est obligatoire
            if (eleveId != '0' && statut == 'actif' && classeId == '') {
                e.preventDefault();
                document.getElementById('classeRequired').style.display = 'block';
                document.getElementById('classeSelect').focus();
                alert('Un élève actif doit avoir une classe. Veuillez sélectionner une classe ou changer le statut.');
                return false;
            }
        });
        
        // Vérifier le statut lors du changement
        const statutSelect = document.getElementById('statutSelect');
        if (statutSelect) {
            statutSelect.addEventListener('change', function() {
                const classeId = document.getElementById('classeSelect').value;
                if (this.value == 'actif' && classeId == '') {
                    document.getElementById('classeRequired').style.display = 'block';
                } else {
                    document.getElementById('classeRequired').style.display = 'none';
                }
            });
        }
        
        // Cacher le message d'erreur quand une classe est sélectionnée
        document.getElementById('classeSelect').addEventListener('change', function() {
            if (this.value != '') {
                document.getElementById('classeRequired').style.display = 'none';
            }
        });
        
        //Filtre automatiquement les champs en fonctions des valeurs tapées dans le champ recherche
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        let timeout = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            // Attend 500ms après la dernière frappe avant de soumettre
            timeout = setTimeout(() => {
                searchForm.submit();
            }, 500);
        });

        //Filtre les statuts
        document.querySelectorAll('#searchForm select').forEach(select => {
        select.addEventListener('change', () => searchForm.submit());
        });
        
        // Supprimer le message de succès de l'URL après 5 secondes
        if (window.location.search.includes('success=')) {
            setTimeout(function() {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('matricule');
                window.history.replaceState({}, '', url);
                const successMsg = document.getElementById('successMessage');
                if (successMsg) {
                    successMsg.style.transition = 'opacity 0.5s';
                    successMsg.style.opacity = '0';
                    setTimeout(() => successMsg.remove(), 500);
                }
            }, 5000);
        }
        
        // Fonction pour formater le montant avec séparateurs de milliers
        function formatMontant(input) {
            // Récupérer la valeur et supprimer tous les espaces
            let value = input.value.replace(/\s/g, '');
            
            // Supprimer tout ce qui n'est pas un chiffre ou une virgule/point
            value = value.replace(/[^\d,.]/g, '');
            
            // Remplacer la virgule par un point pour le traitement
            value = value.replace(',', '.');
            
            // Séparer la partie entière et décimale
            let parts = value.split('.');
            let integerPart = parts[0];
            let decimalPart = parts.length > 1 ? '.' + parts[1].substring(0, 2) : '';
            
            // Formater la partie entière avec des espaces comme séparateurs de milliers
            integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            
            // Reconstruire la valeur formatée
            let formattedValue = integerPart + decimalPart;
            
            // Mettre à jour l'affichage
            input.value = formattedValue;
            
            // Stocker la valeur numérique dans le champ hidden pour l'envoi
            let numericValue = value.replace(/\s/g, '').replace(',', '.');
            document.getElementById('montantFraisInscriptionValue').value = numericValue;
        }
        
        // Fonction pour valider et nettoyer le montant avant soumission
        function validateMontant(input) {
            let value = input.value.replace(/\s/g, '').replace(',', '.');
            let numValue = parseFloat(value);
            
            if (isNaN(numValue) || numValue <= 0) {
                input.value = '';
                document.getElementById('montantFraisInscriptionValue').value = '';
            } else {
                // Formater à nouveau pour l'affichage
                formatMontant(input);
            }
        }
        
        // Intercepter la soumission du formulaire pour utiliser la valeur numérique
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#modal form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const montantInput = document.getElementById('montantFraisInscription');
                    const montantValue = document.getElementById('montantFraisInscriptionValue');
                    
                    if (montantInput && montantValue && montantValue.value) {
                        // Remplacer la valeur formatée par la valeur numérique
                        montantInput.value = montantValue.value;
                    } else if (montantInput) {
                        // Fallback : nettoyer la valeur formatée
                        montantInput.value = montantInput.value.replace(/\s/g, '').replace(',', '.');
                    }
                });
            }
        });
    </script>

</body>
</html>