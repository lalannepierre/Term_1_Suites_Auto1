<?php
require_once '../config.php';
secure_session_start();
check_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$answers = $input['answers'] ?? [];
$session_id = $_SESSION['quiz_session_id'];

try {
    $pdo->beginTransaction();
    
    // Marquer la session comme terminée
    $stmt = $pdo->prepare("
        UPDATE quiz_sessions 
        SET end_time = NOW(), 
            duration = TIMESTAMPDIFF(SECOND, start_time, NOW()),
            completed = TRUE 
        WHERE id = ?
    ");
    $stmt->execute([$session_id]);
    
    // Récupérer les bonnes réponses
    $stmt = $pdo->prepare("
        SELECT id, correct_answer, points 
        FROM questions 
        WHERE quiz_id = ? 
        ORDER BY order_num
    ");
    $stmt->execute([QUIZ_ID]);
    $questions = $stmt->fetchAll();
    
    $total_score = 0;
    $max_score = 0;
    
    // Enregistrer chaque réponse
    foreach ($questions as $question) {
        $question_id = $question['id'];
        $correct_answer = $question['correct_answer'];
        $points = $question['points'];
        $student_answer = $answers["q{$question_id}"] ?? null;
        
        $max_score += $points;
        
        // Vérifier si la réponse est correcte
        $is_correct = false;
        $points_earned = 0;
        
        if ($student_answer !== null) {
            // Normaliser les réponses pour la comparaison
            $normalized_student = strtolower(trim($student_answer));
            $normalized_correct = strtolower(trim($correct_answer));
            
            if ($normalized_student === $normalized_correct) {
                $is_correct = true;
                $points_earned = $points;
                $total_score += $points;
            }
        }
        
        // Insérer la réponse
        $stmt = $pdo->prepare("
            INSERT INTO answers (session_id, question_id, student_answer, is_correct, points_earned) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$session_id, $question_id, $student_answer, $is_correct, $points_earned]);
    }
    
    // Mettre à jour le score de la session
    $percentage = ($max_score > 0) ? round(($total_score / $max_score) * 100, 2) : 0;
    
    $stmt = $pdo->prepare("
        UPDATE quiz_sessions 
        SET score = ?, max_score = ?, percentage = ? 
        WHERE id = ?
    ");
    $stmt->execute([$total_score, $max_score, $percentage, $session_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'score' => $total_score,
        'max_score' => $max_score,
        'percentage' => $percentage
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la soumission']);
}
?>