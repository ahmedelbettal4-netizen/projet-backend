-- Création de la base de données
CREATE DATABASE IF NOT EXISTS gestion_absences;
USE gestion_absences;

-- Table des enseignants
CREATE TABLE enseignants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des filières
CREATE TABLE filieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL
);

-- Table des modules
CREATE TABLE modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    filiere_id INT,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE
);

-- Table des étudiants
CREATE TABLE etudiants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_barre VARCHAR(50) UNIQUE NOT NULL,
    matricule VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    filiere_id INT,
    semestre INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

-- Table des séances
CREATE TABLE seances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT,
    enseignant_id INT,
    date_seance DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME,
    semestre INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE CASCADE
);

-- Table des présences/absences
CREATE TABLE absences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seance_id INT,
    etudiant_id INT,
    statut ENUM('present', 'absent') DEFAULT 'absent',
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_presence (seance_id, etudiant_id)
);

-- Insertion des données de test
INSERT INTO enseignants (email, password, nom, prenom) VALUES 
('prof@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dupont', 'Jean');

INSERT INTO filieres (code, nom) VALUES 
('INFO', 'Informatique'),
('GEST', 'Gestion'),
('MECA', 'Mécanique');

INSERT INTO modules (code, nom, filiere_id) VALUES 
('PROG1', 'Programmation 1', 1),
('BD1', 'Base de données 1', 1),
('ALGO', 'Algorithmique', 1);

INSERT INTO etudiants (code_barre, matricule, nom, prenom, email, filiere_id, semestre) VALUES 
('2024001', 'ETU001', 'Martin', 'Sophie', 'sophie.martin@email.com', 1, 1),
('2024002', 'ETU002', 'Bernard', 'Lucas', 'lucas.bernard@email.com', 1, 1),
('2024003', 'ETU003', 'Petit', 'Emma', 'emma.petit@email.com', 1, 1),
('2024004', 'ETU004', 'Robert', 'Thomas', 'thomas.robert@email.com', 1, 1),
('2024005', 'ETU005', 'Richard', 'Julie', 'julie.richard@email.com', 1, 1);