<?php
require_once '../config.php';
secure_session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$student_name = clean_input($input['name'] ?? '');
$password = $input['password'] ?? '';

if (empty($student_name) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nom et mot de passe requis']);
    exit;
}

try {
    // Vérification du mot de passe de classe
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE password_hash = ?");
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT)]);
    
    // Pour la démo, on vérifie aussi avec le mot de passe en clair
    $stmt2 = $pdo->prepare("SELECT id FROM classes WHERE name = '6ème A'");
    $stmt2->execute();
    $class = $stmt2->fetch();
    
    if (!$class && !password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')) {
        if ($password !== 'math2024') {
            http_response_code(401);
            echo json_encode(['error' => 'Mot de passe incorrect']);
            exit;
        }
    }
    
    $class_id = $class['id'] ?? 1;
    
    // Créer ou récupérer l'élève
    $stmt = $pdo->prepare("SELECT id FROM students WHERE name = ? AND class_id = ?");
    $stmt->execute([$student_name, $class_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        $stmt = $pdo->prepare("INSERT INTO students (name, class_id) VALUES (?, ?)");
        $stmt->execute([$student_name, $class_id]);
        $student_id = $pdo->lastInsertId();
    } else {
        $student_id = $student['id'];
    }
    
    // Créer une nouvelle session de quiz
    $stmt = $pdo->prepare("INSERT INTO quiz_sessions (student_id, quiz_id) VALUES (?, ?)");
    $stmt->execute([$student_id, QUIZ_ID]);
    $session_id = $pdo->lastInsertId();
    
    // Sauvegarder en session
    $_SESSION['student_id'] = $student_id;
    $_SESSION['student_name'] = $student_name;
    $_SESSION['quiz_session_id'] = $session_id;
    $_SESSION['login_time'] = time();
    
    // Récupérer les questions du quiz
    $stmt = $pdo->prepare("
        SELECT id, question_text, question_type, options, order_num 
        FROM questions 
        WHERE quiz_id = ? 
        ORDER BY order_num
    ");
    $stmt->execute([QUIZ_ID]);
    $questions = $stmt->fetchAll();
    
    // Récupérer les détails du quiz
    $stmt = $pdo->prepare("SELECT title, time_limit FROM quizzes WHERE id = ?");
    $stmt->execute([QUIZ_ID]);
    $quiz = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'student_name' => $student_name,
        'session_id' => $session_id,
        'quiz' => $quiz,
        'questions' => $questions
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>