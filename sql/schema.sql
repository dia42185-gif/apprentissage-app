CREATE DATABASE IF NOT EXISTS learning_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE learning_app;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT '#0d6efd',
    icon VARCHAR(50) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    platform VARCHAR(100),
    url VARCHAR(500),
    level VARCHAR(50),
    status ENUM('a_commencer','en_cours','en_pause','terminee','abandonnee') DEFAULT 'a_commencer',
    priority ENUM('urgent','important','normal','faible') DEFAULT 'normal',
    registration_date DATE DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    planned_end_date DATE DEFAULT NULL,
    estimated_hours DECIMAL(6,2) DEFAULT 0,
    actual_hours DECIMAL(6,2) DEFAULT 0,
    progress_percent DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS formation_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formation_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    order_index INT DEFAULT 0,
    status ENUM('a_faire','en_cours','termine') DEFAULT 'a_faire',
    estimated_duration_min INT DEFAULT 0,
    actual_duration_min INT DEFAULT 0,
    planned_date DATE DEFAULT NULL,
    completion_date DATE DEFAULT NULL,
    difficulty ENUM('facile','moyen','difficile') DEFAULT 'moyen',
    notes TEXT,
    url VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vacation_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    hours_per_day DECIMAL(4,2) DEFAULT 2,
    learning_days VARCHAR(50) DEFAULT '1,2,3,4,5,6,7',
    goals_description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vacation_period_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('quotidien','hebdomadaire','long_terme') NOT NULL,
    start_date DATE DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    priority ENUM('urgent','important','normal','faible') DEFAULT 'normal',
    progress_percent DECIMAL(5,2) DEFAULT 0,
    status ENUM('a_faire','en_cours','terminee','abandonnee') DEFAULT 'a_faire',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vacation_period_id) REFERENCES vacation_periods(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    task_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    duration_min INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    formation_id INT DEFAULT NULL,
    goal_id INT DEFAULT NULL,
    priority ENUM('urgent','important','normal','faible') DEFAULT 'normal',
    status ENUM('a_faire','en_cours','terminee','reportee','annulee') DEFAULT 'a_faire',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS learning_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    formation_id INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    task_id INT DEFAULT NULL,
    session_date DATE NOT NULL,
    duration_min INT NOT NULL,
    duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
    started_at BIGINT UNSIGNED DEFAULT NULL,
    ended_at BIGINT UNSIGNED DEFAULT NULL,
    session_type ENUM('cours','pratique','revision','lecture','projet') DEFAULT 'cours',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    entry_date DATE NOT NULL,
    what_learned TEXT,
    what_was_hard TEXT,
    what_to_review TEXT,
    next_step TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, entry_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    formation_id INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    notion VARCHAR(200) NOT NULL,
    learned_date DATE DEFAULT NULL,
    mastery_level ENUM('debutant','moyen','bon','maitrise') DEFAULT 'debutant',
    last_review_date DATE DEFAULT NULL,
    next_review_date DATE DEFAULT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;
