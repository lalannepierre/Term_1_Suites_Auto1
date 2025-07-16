<?php
require_once '../config.php';
secure_session_start();

// Authentification simple pour la démo (à améliorer en production)
if (!isset($_GET['token']) || $_GET['token'] !== 'prof_token_123') {
    die('<h1>Accès non autorisé</h1><p>Veuillez vous connecter.</p>');
}

try {
    // Statistiques générales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            COUNT(CASE WHEN completed = 1 THEN 1 END) as completed_sessions,
            AVG(CASE WHEN completed = 1 THEN percentage END) as avg_percentage,
            AVG(CASE WHEN completed = 1 THEN duration END) as avg_duration
        FROM quiz_sessions 
        WHERE quiz_id = ?
    ");
    $stmt->execute([QUIZ_ID]);
    $stats = $stmt->fetch();
    
    // Dernières sessions
    $stmt = $pdo->prepare("
        SELECT s.name, qs.score, qs.max_score, qs.percentage, 
               qs.duration, qs.start_time, qs.completed
        FROM quiz_sessions qs
        JOIN students s ON qs.student_id = s.id
        WHERE qs.quiz_id = ?
        ORDER BY qs.start_time DESC
        LIMIT 20
    ");
    $stmt->execute([QUIZ_ID]);
    $recent_sessions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die('Erreur de base de données : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Professeur</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2em; font-weight: bold; color: #667eea; }
        .stat-label { color: #666; margin-top: 10px; }
        .sessions-table { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; margin: 5px; }
        .btn:hover { background: #5a6fd8; }
        .completed { color: #28a745; }
        .incomplete { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Dashboard Professeur</h1>
            <p>Gestion et suivi des résultats du quiz de mathématiques</p>
            <a href="api/export.php?token=prof_token_123&format=csv" class="btn">📥 Exporter CSV</a>
            <a href="api/export.php?token=prof_token_123&format=json" class="btn">📄 Exporter JSON</a>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_sessions'] ?></div>
                <div class="stat-label">Total des sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['completed_sessions'] ?></div>
                <div class="stat-label">Sessions terminées</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['avg_percentage'], 1) ?>%</div>
                <div class="stat-label">Moyenne générale</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= gmdate('i:s', $stats['avg_duration']) ?></div>
                <div class="stat-label">Temps moyen</div>
            </div>
        </div>

        <div class="sessions-table">
            <h2>📋 Dernières sessions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Score</th>
                        <th>Pourcentage</th>
                        <th>Durée</th>
                        <th>Début</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sessions as $session): ?>
                    <tr>
                        <td><?= htmlspecialchars($session['name']) ?></td>
                        <td><?= $session['score'] ?>/<?= $session['max_score'] ?></td>
                        <td><?= number_format($session['percentage'], 1) ?>%</td>
                        <td><?= gmdate('i:s', $session['duration']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($session['start_time'])) ?></td>
                        <td class="<?= $session['completed'] ? 'completed' : 'incomplete' ?>">
                            <?= $session['completed'] ? '✓ Terminé' : '⏳ En cours' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>