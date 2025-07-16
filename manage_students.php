<?php
require_once '../config.php';
secure_session_start();

// Authentification simple pour la démo (à améliorer en production)
if (!isset($_GET['token']) || $_GET['token'] !== 'prof_token_123') {
    die('<h1>Accès non autorisé</h1><p>Veuillez vous connecter.</p>');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_class':
                $name = clean_input($_POST['class_name']);
                $password = $_POST['class_password'];
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO classes (name, password_hash) VALUES (?, ?)");
                    $stmt->execute([$name, $password_hash]);
                    $success_message = "Classe ajoutée avec succès !";
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de l'ajout de la classe : " . $e->getMessage();
                }
                break;
                
            case 'delete_student':
                $student_id = intval($_POST['student_id']);
                try {
                    // Supprimer d'abord les sessions et réponses associées
                    $stmt = $pdo->prepare("DELETE FROM answers WHERE session_id IN (SELECT id FROM quiz_sessions WHERE student_id = ?)");
                    $stmt->execute([$student_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM auto_saves WHERE session_id IN (SELECT id FROM quiz_sessions WHERE student_id = ?)");
                    $stmt->execute([$student_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM quiz_sessions WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    
                    $success_message = "Élève supprimé avec succès !";
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
                
            case 'reset_sessions':
                $student_id = intval($_POST['student_id']);
                try {
                    $stmt = $pdo->prepare("DELETE FROM answers WHERE session_id IN (SELECT id FROM quiz_sessions WHERE student_id = ?)");
                    $stmt->execute([$student_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM auto_saves WHERE session_id IN (SELECT id FROM quiz_sessions WHERE student_id = ?)");
                    $stmt->execute([$student_id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM quiz_sessions WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    
                    $success_message = "Sessions réinitialisées avec succès !";
                } catch (PDOException $e) {
                    $error_message = "Erreur lors de la réinitialisation : " . $e->getMessage();
                }
                break;
        }
    }
}

try {
    // Récupérer toutes les classes
    $stmt = $pdo->prepare("SELECT * FROM classes ORDER BY name");
    $stmt->execute();
    $classes = $stmt->fetchAll();
    
    // Récupérer tous les élèves avec leurs statistiques
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.name, s.created_at,
            c.name as class_name,
            COUNT(qs.id) as total_sessions,
            COUNT(CASE WHEN qs.completed = 1 THEN 1 END) as completed_sessions,
            AVG(CASE WHEN qs.completed = 1 THEN qs.percentage END) as avg_percentage,
            MAX(qs.start_time) as last_session
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN quiz_sessions qs ON s.id = qs.student_id
        GROUP BY s.id, s.name, s.created_at, c.name
        ORDER BY s.created_at DESC
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die('Erreur de base de données : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Élèves</title>
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
        .form-group { 
            margin-bottom: 15px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
        }
        input[type="text"], input[type="password"] { 
            width: 100%; 
            max-width: 300px; 
            padding: 10px; 
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
        .btn-danger { 
            background: #dc3545; 
        }
        .btn-danger:hover { 
            background: #c82333; 
        }
        .btn-warning { 
            background: #ffc107; 
            color: #212529; 
        }
        .btn-warning:hover { 
            background: #e0a800; 
        }
        .btn-secondary { 
            background: #6c757d; 
        }
        .btn-secondary:hover { 
            background: #5a6268; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
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
        .success { 
            color: #155724; 
            background: #d4edda; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 15px 0; 
        }
        .error { 
            color: #721c24; 
            background: #f8d7da; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 15px 0; 
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); 
            gap: 10px; 
            margin-top: 10px; 
        }
        .stat-item { 
            text-align: center; 
            padding: 10px; 
            background: #f8f9fa; 
            border-radius: 5px; 
        }
        .stat-value { 
            font-size: 1.2em; 
            font-weight: bold; 
            color: #667eea; 
        }
        .stat-label { 
            font-size: 0.8em; 
            color: #666; 
        }
        .actions { 
            display: flex; 
            gap: 5px; 
            flex-wrap: wrap; 
        }
        .navigation { 
            margin-bottom: 20px; 
        }
        .form-row { 
            display: flex; 
            gap: 15px; 
            align-items: end; 
        }
        @media (max-width: 768px) {
            .container { margin: 10px; }
            .form-row { flex-direction: column; align-items: stretch; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Gestion des Élèves</h1>
            <p>Administration des classes et des élèves</p>
            <div class="navigation">
                <a href="dashboard.php?token=prof_token_123" class="btn btn-secondary">📊 Dashboard</a>
                <a href="view_results.php?token=prof_token_123" class="btn btn-secondary">📋 Résultats</a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="success"><?= $success_message ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error"><?= $error_message ?></div>
        <?php endif; ?>

        <!-- Ajout de classe -->
        <div class="section">
            <h2>➕ Ajouter une nouvelle classe</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_class">
                <div class="form-row">
                    <div class="form-group">
                        <label for="class_name">Nom de la classe :</label>
                        <input type="text" id="class_name" name="class_name" required>
                    </div>
                    <div class="form-group">
                        <label for="class_password">Mot de passe :</label>
                        <input type="password" id="class_password" name="class_password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Ajouter la classe</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des classes -->
        <div class="section">
            <h2>🏫 Classes existantes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Créée le</th>
                        <th>Nombre d'élèves</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                    <tr>
                        <td><?= htmlspecialchars($class['name']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($class['created_at'])) ?></td>
                        <td>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
                            $stmt->execute([$class['id']]);
                            echo $stmt->fetchColumn();
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Liste des élèves -->
        <div class="section">
            <h2>👨‍🎓 Élèves enregistrés</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Classe</th>
                        <th>Inscrit le</th>
                        <th>Statistiques</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['name']) ?></td>
                        <td><?= htmlspecialchars($student['class_name'] ?? 'Non définie') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($student['created_at'])) ?></td>
                        <td>
                            <div class="stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $student['total_sessions'] ?></div>
                                    <div class="stat-label">Sessions</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= $student['completed_sessions'] ?></div>
                                    <div class="stat-label">Terminées</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?= $student['avg_percentage'] ? number_format($student['avg_percentage'], 1) . '%' : 'N/A' ?>
                                    </div>
                                    <div class="stat-label">Moyenne</div>
                                </div>
                            </div>
                            <?php if ($student['last_session']): ?>
                                <small style="color: #666;">
                                    Dernière session : <?= date('d/m/Y H:i', strtotime($student['last_session'])) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="view_results.php?token=prof_token_123&student_id=<?= $student['id'] ?>" class="btn">
                                    📊 Voir résultats
                                </a>
                                <?php if ($student['total_sessions'] > 0): ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir réinitialiser toutes les sessions de cet élève ?');">
                                        <input type="hidden" name="action" value="reset_sessions">
                                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                        <button type="submit" class="btn btn-warning">🔄 Réinitialiser</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet élève ? Cette action est irréversible.');">
                                    <input type="hidden" name="action" value="delete_student">
                                    <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                    <button type="submit" class="btn btn-danger">🗑️ Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($students)): ?>
                <p style="text-align: center; color: #666; margin-top: 20px;">
                    Aucun élève enregistré pour le moment.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>