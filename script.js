class QuizApp {
    constructor() {
        this.currentPage = 'login';
        this.timeLeft = 0;
        this.timer = null;
        this.autoSaveInterval = null;
        this.questions = [];
        this.sessionId = null;
        
        this.initEventListeners();
    }
    
    initEventListeners() {
        document.getElementById('loginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });
        
        document.getElementById('quizForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
        
        // Sauvegarde automatique
        document.getElementById('quizForm').addEventListener('input', () => {
            this.debounceAutoSave();
        });
        
        // Prévenir la fermeture accidentelle
        window.addEventListener('beforeunload', (e) => {
            if (this.currentPage === 'quiz') {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
    
    async handleLogin() {
        const formData = new FormData(document.getElementById('loginForm'));
        const data = {
            name: formData.get('name'),
            password: formData.get('password')
        };
        
        try {
            const response = await fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.sessionId = result.session_id;
                this.questions = result.questions;
                this.timeLeft = result.quiz.time_limit;
                this.showQuizPage(result);
            } else {
                document.getElementById('loginError').textContent = result.error;
            }
        } catch (error) {
            document.getElementById('loginError').textContent = 'Erreur de connexion';
        }
    }
    
    showQuizPage(data) {
        document.getElementById('loginPage').classList.remove('active');
        document.getElementById('quizPage').classList.add('active');
        document.getElementById('studentInfo').innerHTML = `<strong>Élève :</strong> ${data.student_name}`;
        
        this.generateQuestions();
        this.startTimer();
        this.startAutoSave();
        this.currentPage = 'quiz';
    }
    
    generateQuestions() {
        const container = document.getElementById('questionsContainer');
        container.innerHTML = '';
        
        this.questions.forEach((question, index) => {
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question';
            
            let inputHTML = '';
            if (question.question_type === 'number') {
                inputHTML = `<input type="number" id="q${question.id}" name="q${question.id}" placeholder="Votre réponse">`;
            } else if (question.question_type === 'text') {
                inputHTML = `<input type="text" id="q${question.id}" name="q${question.id}" placeholder="Votre réponse">`;
            } else if (question.question_type === 'radio') {
                const options = JSON.parse(question.options || '[]');
                inputHTML = '<div class="radio-group">';
                options.forEach(option => {
                    inputHTML += `
                        <label>
                            <input type="radio" name="q${question.id}" value="${option.value}">
                            ${option.text}
                        </label>
                    `;
                });
                inputHTML += '</div>';
            }
            
            questionDiv.innerHTML = `
                <h3>Question ${index + 1}</h3>
                <p>${question.question_text}</p>
                ${inputHTML}
            `;
            
            container.appendChild(questionDiv);
        });
    }
    
    startTimer() {
        this.timer = setInterval(() => {
            this.timeLeft--;
            const minutes = Math.floor(this.timeLeft / 60);
            const seconds = this.timeLeft % 60;
            
            document.getElementById('timer').textContent = 
                `Temps restant : ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (this.timeLeft <= 0) {
                alert("Temps écoulé ! Le quiz va être soumis automatiquement.");
                this.handleSubmit();
            }
        }, 1000);
    }
    
    startAutoSave() {
        this.autoSaveInterval = setInterval(() => {
            this.autoSave();
        }, 30000); // Toutes les 30 secondes
    }
    
    debounceAutoSave() {
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(() => {
            this.autoSave();
        }, 3000); // 3 secondes après la dernière modification
    }
    
    async autoSave() {
        const answers = this.getAnswers();
        
        try {
            const response = await fetch('api/auto_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ answers })
            });
            
            if (response.ok) {
                const statusDiv = document.getElementById('autoSaveStatus');
                statusDiv.textContent = 'Sauvegardé automatiquement';
                statusDiv.style.color = 'green';
                setTimeout(() => {
                    statusDiv.textContent = '';
                }, 2000);
            }
        } catch (error) {
            console.error('Erreur de sauvegarde automatique:', error);
        }
    }
    
    getAnswers() {
        const answers = {};
        this.questions.forEach(question => {
            const input = document.querySelector(`[name="q${question.id}"]`);
            if (input) {
                if (input.type === 'radio') {
                    const checked = document.querySelector(`[name="q${question.id}"]:checked`);
                    answers[`q${question.id}`] = checked ? checked.value : null;
                } else {
                    answers[`q${question.id}`] = input.value.trim() || null;
                }
            }
        });
        return answers;
    }
    
    async handleSubmit() {
        if (this.timer) {
            clearInterval(this.timer);
        }
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
        }
        
        const answers = this.getAnswers();
        
        try {
            const response = await fetch('api/submit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ answers })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showResults();
            } else {
                alert('Erreur lors de la soumission : ' + result.error);
            }
        } catch (error) {
            alert('Erreur de connexion lors de la soumission');
        }
    }
    
    async showResults() {
        try {
            const response = await fetch('api/get_results.php');
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('quizPage').classList.remove('active');
                document.getElementById('resultsPage').classList.add('active');
                
                const session = result.session;
                const answers = result.answers;
                
                document.getElementById('studentResults').innerHTML = `
                    <h2>Félicitations ${session.student_name} !</h2>
                    <div class="success">
                        Score : ${session.score}/${session.max_score} (${session.percentage}%)
                    </div>
                    <p>Temps utilisé : ${this.formatDuration(session.duration)}</p>
                `;
                
                let detailedHTML = '<h3>Correction détaillée :</h3>';
                answers.forEach((answer, index) => {
                    const statusClass = answer.is_correct ? 'correct' : 'incorrect';
                    detailedHTML += `
                        <div class="question">
                            <h4>Question ${index + 1}</h4>
                            <p><strong>Question :</strong> ${answer.question_text}</p>
                            <p><strong>Votre réponse :</strong> 
                                <span class="${statusClass}">
                                    ${answer.student_answer || 'Non répondu'}
                                </span>
                            </p>
                            <p><strong>Réponse correcte :</strong> 
                                <span class="correct">${answer.correct_answer}</span>
                            </p>
                            <p><strong>Points :</strong> ${answer.points_earned}/${answer.points}</p>
                            ${!answer.is_correct ? `<div class="help"><strong>Aide :</strong> ${answer.help_text}</div>` : ''}
                        </div>
                    `;
                });
                
                document.getElementById('detailedResults').innerHTML = detailedHTML;
                this.currentPage = 'results';
            }
        } catch (error) {
            alert('Erreur lors de la récupération des résultats');
        }
    }
    
    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
}

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', () => {
    new QuizApp();
});