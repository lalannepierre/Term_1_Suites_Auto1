<?php
require_once '../config.php';
secure_session_start();

// Vérification basique pour l'accès professeur (à améliorer)
if (!isset($_GET['token']) || $_GET['token'] !== 'prof_token_123') {
    http_response_code(403);
    die('Accès non autorisé');
}

$format = $_GET['format'] ?? 'csv';

try {
    $stmt = $pdo->prepare("
        SELECT s.name, qs.score, qs.max_score, qs.percentage, 
               qs.duration, qs.start_time, qs.end_time, qs.completed
        FROM quiz_sessions qs
        JOIN students st ON qs.student_id = st.id
        JOIN students s ON qs.student_id = s.id
        WHERE qs.quiz_id = ?
        ORDER BY qs.start_time DESC
    ");
    $stmt->execute([QUIZ_ID]);
    $sessions = $stmt->fetchAll();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="resultats_quiz.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, [
            'Nom', 'Score', 'Score Max', 'Pourcentage', 'Durée (min)', 
            'Début', 'Fin', 'Terminé'
        ]);
        
        // Données
        foreach ($sessions as $session) {
            $duration_min = $session['duration'] ? round($session['duration'] / 60, 1) : 0;
            fputcsv($output, [
                $session['name'],
                $session['score'],
                $session['max_score'],
                $session['percentage'] . '%',
                $duration_min,
                $session['start_time'],
                $session['end_time'],
                $session['completed'] ? 'Oui' : 'Non'
            ]);
        }
        
        fclose($output);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['sessions' => $sessions]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'export']);
}
?>