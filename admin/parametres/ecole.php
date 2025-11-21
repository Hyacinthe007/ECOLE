<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../config.php');

$message = '';
$error = '';

// Mise à jour des paramètres de l'école
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ecole') {
    $nom_ecole = trim($_POST['nom_ecole']);
    $adresse = trim($_POST['adresse']);
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    $devise = trim($_POST['devise']);
    $directeur_nom = trim($_POST['directeur_nom']);
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
}

$params_result = $conn->query("SELECT * FROM parametres_ecole WHERE id = 1");
if ($params_result->num_rows == 0) {
    $conn->query("INSERT INTO parametres_ecole (nom_ecole, devise) VALUES ('École Mandroso', 'Excellence et Développement')");
    $params_result = $conn->query("SELECT * FROM parametres_ecole WHERE id = 1");
}
$params = $params_result->fetch_assoc();
?>
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
                <input type="text" name="nom_ecole" required value="<?= htmlspecialchars($params['nom_ecole']) ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nom du directeur</label>
                <input type="text" name="directeur_nom" value="<?= htmlspecialchars($params['directeur_nom']) ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Adresse</label>
                <input type="text" name="adresse" value="<?= htmlspecialchars($params['adresse']) ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                <input type="tel" name="telephone" value="<?= htmlspecialchars($params['telephone']) ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($params['email']) ?>" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Devise de l'école</label>
                <input type="text" name="devise" value="<?= htmlspecialchars($params['devise']) ?>" placeholder="Ex: Excellence et Développement" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </div>
    </form>
</div>
