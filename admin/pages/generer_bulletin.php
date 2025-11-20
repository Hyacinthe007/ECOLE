<?php
session_start();
require_once('../config.php');
require_once __DIR__ . '/../../vendor/autoload.php';


use TCPDF as PDF;

// Vérification des paramètres
$eleve_id = isset($_GET['eleve']) ? intval($_GET['eleve']) : 0;
$classe_id = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$trimestre = isset($_GET['trimestre']) ? intval($_GET['trimestre']) : 1;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'single';
$preview = isset($_GET['preview']) ? true : false;

// Récupération des paramètres de l'école
$params_result = $conn->query("SELECT * FROM parametres_ecole WHERE id = 1");
$params = $params_result->fetch_assoc();

// Récupération de l'année scolaire active
$annee_result = $conn->query("SELECT * FROM annees_scolaires WHERE actif = 1 LIMIT 1");
$annee = $annee_result->fetch_assoc();

/**
 * Fonction pour générer un bulletin PDF pour un élève
 */
function genererBulletinEleve($eleve_id, $trimestre, $params, $annee, $conn) {
    // Récupération des informations de l'élève
    $eleve_query = $conn->query("
        SELECT e.*, c.nom as classe_nom, c.niveau
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        JOIN classes c ON i.classe_id = c.id
        WHERE e.id = $eleve_id 
        AND i.statut = 'active'
        LIMIT 1
    ");
    
    if ($eleve_query->num_rows == 0) {
        die("Élève non trouvé");
    }
    
    $eleve = $eleve_query->fetch_assoc();
    
    // Récupération des notes par matière
    $notes_query = $conn->query("
        SELECT 
            m.nom as matiere_nom,
            m.code as matiere_code,
            m.coefficient,
            n.note,
            n.note_max,
            n.type_evaluation,
            n.commentaire,
            (n.note / n.note_max * 20) as note_sur_20,
            (n.note / n.note_max * 20 * m.coefficient) as note_ponderee
        FROM notes n
        JOIN matieres m ON n.matiere_id = m.id
        WHERE n.eleve_id = $eleve_id
        AND n.trimestre = $trimestre
        ORDER BY m.nom
    ");
    
    // Calcul des statistiques
    $total_notes = 0;
    $total_coefficient = 0;
    $notes_par_matiere = [];
    
    while ($note = $notes_query->fetch_assoc()) {
        $matiere_nom = $note['matiere_nom'];
        
        if (!isset($notes_par_matiere[$matiere_nom])) {
            $notes_par_matiere[$matiere_nom] = [
                'code' => $note['matiere_code'],
                'notes' => [],
                'coefficient' => $note['coefficient'],
                'total' => 0,
                'count' => 0
            ];
        }
        
        $notes_par_matiere[$matiere_nom]['notes'][] = $note['note_sur_20'];
        $notes_par_matiere[$matiere_nom]['total'] += $note['note_sur_20'];
        $notes_par_matiere[$matiere_nom]['count']++;
    }
    
    // Calcul des moyennes par matière
    $resultats = [];
    foreach ($notes_par_matiere as $matiere => $data) {
        $moyenne = $data['total'] / $data['count'];
        $resultats[] = [
            'matiere' => $matiere,
            'code' => $data['code'],
            'moyenne' => $moyenne,
            'coefficient' => $data['coefficient'],
            'total' => $moyenne * $data['coefficient']
        ];
        
        $total_notes += $moyenne * $data['coefficient'];
        $total_coefficient += $data['coefficient'];
    }
    
    $moyenne_generale = $total_coefficient > 0 ? $total_notes / $total_coefficient : 0;
    
    // Calcul du rang dans la classe
    $rang_query = $conn->query("
        SELECT COUNT(*) + 1 as rang
        FROM (
            SELECT 
                n.eleve_id,
                AVG((n.note / n.note_max * 20) * m.coefficient) / AVG(m.coefficient) as moy
            FROM notes n
            JOIN matieres m ON n.matiere_id = m.id
            JOIN inscriptions i ON n.eleve_id = i.eleve_id
            WHERE i.classe_id = (
                SELECT classe_id FROM inscriptions WHERE eleve_id = $eleve_id AND statut = 'active' LIMIT 1
            )
            AND n.trimestre = $trimestre
            AND i.statut = 'active'
            GROUP BY n.eleve_id
            HAVING moy > $moyenne_generale
        ) as classement
    ");
    $rang_data = $rang_query->fetch_assoc();
    $rang = $rang_data['rang'];
    
    // Effectif de la classe
    $effectif_query = $conn->query("
        SELECT COUNT(DISTINCT i.eleve_id) as effectif
        FROM inscriptions i
        WHERE i.classe_id = (
            SELECT classe_id FROM inscriptions WHERE eleve_id = $eleve_id AND statut = 'active' LIMIT 1
        )
        AND i.statut = 'active'
    ");
    $effectif_data = $effectif_query->fetch_assoc();
    $effectif = $effectif_data['effectif'];
    
    // Création du PDF
    $pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
    
    // Métadonnées
    $pdf->SetCreator('École Mandroso');
    $pdf->SetAuthor($params['nom_ecole']);
    $pdf->SetTitle('Bulletin Trimestre ' . $trimestre . ' - ' . $eleve['nom'] . ' ' . $eleve['prenom']);
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // EN-TÊTE PERSONNALISÉ
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Logo (si existe)
    if ($params['logo'] && file_exists('../assets/uploads/' . $params['logo'])) {
        $pdf->Image('../assets/uploads/' . $params['logo'], 15, 10, 30, 30, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetXY(50, 15);
    } else {
        $pdf->SetXY(15, 15);
    }
    
    // Nom de l'école
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, strtoupper($params['nom_ecole']), 0, 1, 'C');
    
    // Devise
    if ($params['devise']) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, $params['devise'], 0, 1, 'C');
    }
    
    // Adresse et contact
    $pdf->SetFont('helvetica', '', 8);
    $contact = [];
    if ($params['adresse']) $contact[] = $params['adresse'];
    if ($params['telephone']) $contact[] = 'Tél: ' . $params['telephone'];
    if ($params['email']) $contact[] = 'Email: ' . $params['email'];
    
    if (!empty($contact)) {
        $pdf->Cell(0, 4, implode(' | ', $contact), 0, 1, 'C');
    }
    
    // Ligne de séparation
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->Line(15, $pdf->GetY() + 3, 195, $pdf->GetY() + 3);
    $pdf->Ln(8);
    
    // TITRE DU BULLETIN
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'BULLETIN DE NOTES', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, 'Trimestre ' . $trimestre . ' - Année scolaire ' . $annee['libelle'], 0, 1, 'C');
    $pdf->Ln(5);
    
    // INFORMATIONS ÉLÈVE
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 7, 'Nom et Prénom:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(120, 7, strtoupper($eleve['nom']) . ' ' . ucfirst($eleve['prenom']), 1, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 7, 'Matricule:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, $eleve['matricule'], 1, 0, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 7, 'Classe:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(30, 7, $eleve['classe_nom'], 1, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(60, 7, 'Date de naissance:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(120, 7, date('d/m/Y', strtotime($eleve['date_naissance'])), 1, 1, 'L');
    
    $pdf->Ln(5);
    
    // TABLEAU DES NOTES
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    
    // En-têtes
    $pdf->Cell(70, 8, 'MATIÈRES', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'MOYENNE', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'COEF', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'APPRÉCIATION', 1, 1, 'C', true);
    
    // Lignes de notes
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    foreach ($resultats as $index => $result) {
        $fill = ($index % 2 == 0) ? [250, 250, 250] : [255, 255, 255];
        $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
        
        $pdf->Cell(70, 7, $result['matiere'], 1, 0, 'L', true);
        
        // Couleur selon la note
        if ($result['moyenne'] >= 10) {
            $pdf->SetTextColor(0, 128, 0);
        } else {
            $pdf->SetTextColor(255, 0, 0);
        }
        $pdf->Cell(30, 7, number_format($result['moyenne'], 2), 1, 0, 'C', true);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(25, 7, number_format($result['coefficient'], 1), 1, 0, 'C', true);
        $pdf->Cell(30, 7, number_format($result['total'], 2), 1, 0, 'C', true);
        
        // Appréciation
        $appreciation = '';
        if ($result['moyenne'] >= 16) $appreciation = 'TB';
        elseif ($result['moyenne'] >= 14) $appreciation = 'B';
        elseif ($result['moyenne'] >= 12) $appreciation = 'AB';
        elseif ($result['moyenne'] >= 10) $appreciation = 'P';
        else $appreciation = 'I';
        
        $pdf->Cell(25, 7, $appreciation, 1, 1, 'C', true);
    }
    
    // MOYENNE GÉNÉRALE
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(70, 8, 'MOYENNE GÉNÉRALE', 1, 0, 'L', true);
    
    if ($moyenne_generale >= 10) {
        $pdf->SetTextColor(0, 128, 0);
    } else {
        $pdf->SetTextColor(255, 0, 0);
    }
    $pdf->Cell(30, 8, number_format($moyenne_generale, 2) . ' / 20', 1, 0, 'C', true);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(25, 8, number_format($total_coefficient, 1), 1, 0, 'C', true);
    $pdf->Cell(30, 8, number_format($total_notes, 2), 1, 0, 'C', true);
    
    // Appréciation générale
    $appreciation_generale = '';
    if ($moyenne_generale >= 16) $appreciation_generale = 'Excellent';
    elseif ($moyenne_generale >= 14) $appreciation_generale = 'Très Bien';
    elseif ($moyenne_generale >= 12) $appreciation_generale = 'Bien';
    elseif ($moyenne_generale >= 10) $appreciation_generale = 'Assez Bien';
    else $appreciation_generale = 'Insuffisant';
    
    $pdf->Cell(25, 8, $appreciation_generale, 1, 1, 'C', true);
    
    $pdf->Ln(3);
    
    // STATISTIQUES
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    
    $pdf->Cell(60, 7, 'Rang:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(120, 7, $rang . ' / ' . $effectif, 1, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 7, 'Effectif de la classe:', 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(120, 7, $effectif . ' élèves', 1, 1, 'L');
    
    $pdf->Ln(5);
    
    // OBSERVATIONS ET SIGNATURES
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, 'Observations du Conseil de Classe:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $observation = '';
    if ($moyenne_generale >= 14) {
        $observation = "Excellent travail ! Continue sur cette lancée.";
    } elseif ($moyenne_generale >= 12) {
        $observation = "Bon travail. Peut mieux faire.";
    } elseif ($moyenne_generale >= 10) {
        $observation = "Travail satisfaisant. Doit fournir plus d'efforts.";
    } else {
        $observation = "Résultats insuffisants. Doit redoubler d'efforts.";
    }
    
    $pdf->MultiCell(0, 6, $observation, 1, 'L', false);
    
    $pdf->Ln(10);
    
    // Signatures
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 7, 'Le Directeur', 0, 0, 'C');
    $pdf->Cell(60, 7, '', 0, 0, 'C');
    $pdf->Cell(60, 7, 'Visa des Parents', 0, 1, 'C');
    
    $pdf->Ln(15);
    
    $pdf->SetFont('helvetica', 'I', 8);
    if ($params['directeur_nom']) {
        $pdf->Cell(60, 5, $params['directeur_nom'], 0, 0, 'C');
    }
    
    $pdf->Ln(10);
    
    // Pied de page
    $pdf->SetY(-20);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Date d\'édition: ' . date('d/m/Y à H:i'), 0, 1, 'C');
    
    return $pdf;
}

// Génération selon le mode
if ($mode == 'tous' && $classe_id > 0) {
    // Générer tous les bulletins de la classe dans un seul PDF
    $pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
    
    $eleves_query = $conn->query("
        SELECT e.id
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        WHERE i.classe_id = $classe_id 
        AND i.statut = 'active'
        AND e.statut = 'actif'
        ORDER BY e.nom, e.prenom
    ");
    
    while ($eleve = $eleves_query->fetch_assoc()) {
        $bulletin = genererBulletinEleve($eleve['id'], $trimestre, $params, $annee, $conn);
        
        // Copier les pages du bulletin dans le PDF principal
        $pageCount = $bulletin->getNumPages();
        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf->AddPage();
            $pdf->writeHTML($bulletin->getPageBuffer($i), true, false, true, false, '');
        }
    }
    
    $classe_info = $conn->query("SELECT nom FROM classes WHERE id = $classe_id")->fetch_assoc();
    $pdf->Output('Bulletins_' . $classe_info['nom'] . '_T' . $trimestre . '.pdf', 'I');
    
} elseif ($eleve_id > 0) {
    // Générer un seul bulletin
    $pdf = genererBulletinEleve($eleve_id, $trimestre, $params, $annee, $conn);
    
    $eleve_info = $conn->query("SELECT nom, prenom FROM eleves WHERE id = $eleve_id")->fetch_assoc();
    $filename = 'Bulletin_' . $eleve_info['nom'] . '_' . $eleve_info['prenom'] . '_T' . $trimestre . '.pdf';
    
    $pdf->Output($filename, 'I');
} else {
    die("Paramètres invalides");
}
?>