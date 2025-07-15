<?php
require_once '../config.php';
secure_session_start();
check_auth();

header('Content-Type: application/json');

$session_id = $_SESSION['quiz_session_id'];

try {
    // Récupérer les détails de la session
    $stmt = $pdo->prepare("
        SELECT qs.score, qs.max_score, qs.percentage, qs.duration,
               s.name as student_name
        FROM quiz_sessions qs
        JOIN students s ON qs.student_id = s.id
        WHERE qs.id = ?
    ");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    
    // Récupérer les réponses détaillées
    $stmt = $pdo->prepare("
        SELECT q.question_text, q.correct_answer, q.help_text,
               a.student_answer, a.is_correct, a.points_earned, q.points
        FROM answers a
        JOIN questions q ON a.question_id = q.id
        WHERE a.session_id = ?
        ORDER BY q.order_num
    ");
    $stmt->execute([$session_id]);
    $answers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'session' => $session,
        'answers' => $answers
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la récupération des résultats']);
}
?>