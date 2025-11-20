<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include('../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_ecole = trim($_POST['nom_ecole']);
    $devise = trim($_POST['devise']);
    $adresse = trim($_POST['adresse']);
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    $directeur_nom = trim($_POST['directeur_nom']);
    
    // Gestion du logo
    $logo = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['logo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Vérifier la taille (max 2MB)
            if ($_FILES['logo']['size'] <= 2 * 1024 * 1024) {
                $upload_dir = '../assets/uploads/';
                
                // Créer le dossier si nécessaire
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Nom unique pour le fichier
                $logo = 'logo_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $logo;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    // Supprimer l'ancien logo si existe
                    $old_logo_query = $conn->query("SELECT logo FROM parametres_ecole WHERE id = 1");
                    if ($old_logo_query->num_rows > 0) {
                        $old_data = $old_logo_query->fetch_assoc();
                        if ($old_data['logo'] && file_exists($upload_dir . $old_data['logo'])) {
                            unlink($upload_dir . $old_data['logo']);
                        }
                    }
                } else {
                    $logo = null;
                }
            }
        }
    }
    
    // Vérifier si un enregistrement existe
    $check = $conn->query("SELECT id FROM parametres_ecole WHERE id = 1");
    
    if ($check->num_rows > 0) {
        // Mise à jour
        if ($logo) {
            $stmt = $conn->prepare("UPDATE parametres_ecole SET nom_ecole=?, logo=?, adresse=?, telephone=?, email=?, devise=?, directeur_nom=? WHERE id=1");
            $stmt->bind_param("sssssss", $nom_ecole, $logo, $adresse, $telephone, $email, $devise, $directeur_nom);
        } else {
            $stmt = $conn->prepare("UPDATE parametres_ecole SET nom_ecole=?, adresse=?, telephone=?, email=?, devise=?, directeur_nom=? WHERE id=1");
            $stmt->bind_param("ssssss", $nom_ecole, $adresse, $telephone, $email, $devise, $directeur_nom);
        }
    } else {
        // Insertion
        $stmt = $conn->prepare("INSERT INTO parametres_ecole (nom_ecole, logo, adresse, telephone, email, devise, directeur_nom) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $nom_ecole, $logo, $adresse, $telephone, $email, $devise, $directeur_nom);
    }
    
    if ($stmt->execute()) {
        header("Location: bulletins.php?success=1");
    } else {
        header("Location: bulletins.php?error=1");
    }
    exit();
}

header("Location: bulletins.php");
exit();
?>