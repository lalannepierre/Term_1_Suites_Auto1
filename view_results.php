<?php
require_once '../config.php';
secure_session_start();

// Authentification simple pour la démo (à améliorer en production)
if (!isset($_GET['token']) || $_GET['token'] !== 'prof_token_123') {
    die('<h1>Accès non autorisé</h1><p>Veuillez vous connecter.</p>');
}

// Récupérer les paramètres de filtrage
$selected_student = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    // Récupérer toutes les classes pour le filtre
    $stmt = $pdo->prepare("SELECT id, name FROM classes ORDER BY name");
    $stmt->execute();
    $classes = $stmt->fetchAll();
    
    // Récupérer tous les élèves pour le filtre
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, c.name as class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        ORDER BY s.name
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
    
    // Construire la requête des résultats avec filtres
    $where_conditions = ["qs.quiz_id = ?"];
    $params = [QUIZ_ID];
    
    if ($selected_student) {
        $where_conditions[] = "s.id = ?";
        $params[] = $selected_student;
    }
    
    if ($selected_class) {
        $where_conditions[] = "s.class_id = ?";
        $params[] = $selected_class;
    }
    
    if ($date_from) {
        $where_conditions[] = "DATE(qs.start_time) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "DATE(qs.start_time) <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Récupérer les sessions avec filtres
    $stmt = $pdo->prepare("
        SELECT 
            qs.id as session_id,
            s.name as student_name,
            c.name as class_name,
            qs.score,
            qs.max_score,
            qs.percentage,
            qs.duration,
            qs.start_time,
            qs.end_time,
            qs.completed
        FROM quiz_sessions qs
        JOIN students s ON qs.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE $where_clause
        ORDER BY qs.start_time DESC
    ");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
    
    // Statistiques générales (avec filtres)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            COUNT(CASE WHEN qs.completed = 1 THEN 1 END) as completed_sessions,
            AVG(CASE WHEN qs.completed = 1 THEN qs.percentage END) as avg_percentage,
            MIN(CASE WHEN qs.completed = 1 THEN qs.percentage END) as min_percentage,
            MAX(CASE WHEN qs.completed = 1 THEN qs.percentage END) as max_percentage,
            AVG(CASE WHEN qs.completed = 1 THEN qs.duration END) as avg_duration
        FROM quiz_sessions qs
        JOIN students s ON qs.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
    // Récupérer les détails d'une session spécifique si demandé
    $session_details = null;
    if (isset($_GET['session_id'])) {
        $session_id = intval($_GET['session_id']);
        
        // Détails de la session
        $stmt = $pdo->prepare("
            SELECT 
                qs.id, qs.score, qs.max_score, qs.percentage, qs.duration,
                qs.start_time, qs.end_time, qs.completed,
                s.name as student_name, c.name as class_name
            FROM quiz_sessions qs
            JOIN students s ON qs.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE qs.id = ?
        ");
        $stmt->execute([$session_id]);
        $session_info = $stmt->fetch();
        
        // Réponses détaillées
        $stmt = $pdo->prepare("
            SELECT 
                q.question_text,
                q.correct_answer,
                q.help_text,
                q.points,
                a.student_answer,
                a.is_correct,
                a.points_earned,
                a.answered_at
            FROM answers a
            JOIN questions q ON a.question_id = q.id
            WHERE a.session_id = ?
            ORDER BY q.order_num
        ");
        $stmt->execute([$session_id]);
        $answers = $stmt->fetchAll();
        
        $session_details = [
            'info' => $session_info,
            'answers' => $answers
        ];
    }
    
} catch (PDOException $e) {
    die('Erreur de base de données : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation des Résultats</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 40px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        .header { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .section { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .filters { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
        }
        .filter-row { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 15px; 
        }
        .form-group { 
            display: flex; 
            flex-direction: column; 
        }
        label { 
            margin-bottom: 5px; 
            font-weight: bold; 
            font-size: 14px; 
        }
        select, input[type="date"] { 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 14px; 
        }
        .btn { 
            background: #667eea; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            margin: 5px; 
            font-size: 14px; 
        }
        .btn:hover { 
            background: #5a6fd8; 
        }
        .btn-secondary { 
            background: #6c757d; 
        }
        .btn-secondary:hover { 
            background: #5a6268; 
        }
        .btn-info { 
            background: #17a2b8; 
        }
        .btn-info:hover { 
            background: #138496; 
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .stat-card { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            text-align: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        .stat-value { 
            font-size: 2em; 
            font-weight: bold; 
            color: #667eea; 
        }
        .stat-label { 
            color: #666; 
            margin-top: 5px; 
            font-size: 0.9em; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background: #f8f9fa; 
            font-weight: bold; 
        }
        .completed { 
            color: #28a745; 
        }
        .incomplete { 
            color: #dc3545; 
        }
        .score-excellent { 
            color: #28a745; 
            font-weight: bold; 
        }
        .score-good { 
            color: #ffc107; 
            font-weight: bold; 
        }
        .score-poor { 
            color: #dc3545; 
            font-weight: bold; 
        }
        .navigation { 
            margin-bottom: 20px; 
        }
        .session-detail { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 10px; 
            margin-top: 20px; 
        }
        .question-detail { 
            background: white; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 10px 0; 
            border-left: 4px solid #667eea; 
        }
        .answer-correct { 
            color: #28a745; 
            font-weight: bold; 
        }
        .answer-incorrect { 
            color: #dc3545; 
            font-weight: bold; 
        }
        .help-text { 
            background: #e7f3ff; 
            padding: 10px; 
            border-radius: 5px; 
            margin-top: 10px; 
            font-size: 0.9em; 
        }
        @media (max-width: 768px) {
            .container { margin: 10px; }
            .filter-row { grid-template-columns: 1fr; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Visualisation des Résultats</h1>
            <p>Analyse détaillée des performances des élèves</p>
            <div class="navigation">
                <a href="dashboard.php?token=prof_token_123" class="btn btn-secondary">📊 Dashboard</a>
                <a href="manage_students.php?token=prof_token_123" class="btn btn-secondary">👥 Gestion des élèves</a>
                <a href="../api/export.php?token=prof_token_123&format=csv" class="btn">📥 Export CSV</a>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <h3>🔍 Filtres</h3>
            <form method="get">
                <input type="hidden" name="token" value="prof_token_123">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="student_id">Élève :</label>
                        <select name="student_id" id="student_id">
                            <option value="">Tous les élèves</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= $selected_student == $student['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['name']) ?> 
                                    (<?= htmlspecialchars($student['class_name'] ?? 'Sans classe') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="class_id">Classe :</label>
                        <select name="class_id" id="class_id">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $selected_class == $class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['name']) ?>
                                </option>
                            <?php endforeach