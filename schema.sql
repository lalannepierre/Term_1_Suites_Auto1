-- Création de la base de données
CREATE DATABASE quiz_math CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quiz_math;

-- Table des classes/groupes
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des élèves
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    class_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

-- Table des quiz
CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    time_limit INT DEFAULT 900, -- 15 minutes en secondes
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des questions
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT,
    question_text TEXT NOT NULL,
    question_type ENUM('number', 'text', 'radio') NOT NULL,
    options JSON, -- Pour les questions à choix multiples
    correct_answer TEXT NOT NULL,
    help_text TEXT,
    points INT DEFAULT 1,
    order_num INT,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
);

-- Table des sessions de quiz
CREATE TABLE quiz_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    quiz_id INT,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration INT, -- en secondes
    score INT DEFAULT 0,
    max_score INT,
    percentage DECIMAL(5,2),
    completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id)
);

-- Table des réponses
CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT,
    question_id INT,
    student_answer TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    points_earned INT DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES quiz_sessions(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- Table des sauvegardes auto
CREATE TABLE auto_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT,
    answers_data JSON,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES quiz_sessions(id)
);

-- Insertion des données de test
INSERT INTO classes (name, password_hash) VALUES 
('6ème A', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password: math2024
('5ème B', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO quizzes (title, description, time_limit) VALUES 
('Quiz de Mathématiques - Test', 'Quiz de révision sur les bases', 900);

INSERT INTO questions (quiz_id, question_text, question_type, correct_answer, help_text, order_num) VALUES 
(1, 'Calculez : 15 × 8 = ?', 'number', '120', 'Pour multiplier par 8, vous pouvez multiplier par 10 puis soustraire 2 fois le nombre : 15 × 10 - 15 × 2 = 150 - 30 = 120', 1),
(1, 'Résolvez : 2x + 5 = 13. x = ?', 'number', '4', 'Pour résoudre 2x + 5 = 13, soustrayez 5 des deux côtés : 2x = 8, puis divisez par 2 : x = 4', 2),
(1, 'Quelle est l\'aire d\'un rectangle de longueur 12 cm et de largeur 8 cm ?', 'radio', '96', 'L\'aire d\'un rectangle = longueur × largeur = 12 × 8 = 96 cm²', 3),
(1, 'Calculez : 3/4 + 1/2 = ? (sous forme de fraction)', 'text', '5/4', 'Pour additionner 3/4 + 1/2, mettez au même dénominateur : 3/4 + 2/4 = 5/4', 4),
(1, 'Dans une classe de 25 élèves, 15 sont présents. Quel est le pourcentage d\'élèves présents ?', 'number', '60', 'Pourcentage = (nombre présents / nombre total) × 100 = (15/25) × 100 = 60%', 5);

-- Mise à jour des options pour la question à choix multiples
UPDATE questions SET options = JSON_ARRAY(
    JSON_OBJECT('value', '96', 'text', '96 cm²'),
    JSON_OBJECT('value', '40', 'text', '40 cm²'),
    JSON_OBJECT('value', '20', 'text', '20 cm²'),
    JSON_OBJECT('value', '192', 'text', '192 cm²')
) WHERE id = 3;