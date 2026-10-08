CREATE DATABASE IF NOT EXISTS pt_tracker;

USE pt_tracker;


CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('coach', 'client') NOT NULL,
    date_of_birth DATE NULL,
    phone VARCHAR(30) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);


CREATE TABLE trainer_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trainer_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_trainer_clients_trainer
        FOREIGN KEY (trainer_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_trainer_clients_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    UNIQUE (trainer_id, client_id)
);


CREATE TABLE daily_check_ins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    check_in_date DATE NOT NULL,
    bodyweight DECIMAL(5,2) NULL,
    sleep_hours DECIMAL(4,2) NULL,
    sleep_quality TINYINT UNSIGNED NULL,
    energy_level TINYINT UNSIGNED NULL,
    stress_level TINYINT UNSIGNED NULL,
    steps INT UNSIGNED NULL,
    water_litres DECIMAL(4,2) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_checkins_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    UNIQUE (client_id, check_in_date)
);


CREATE TABLE progress_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_in_id BIGINT UNSIGNED NOT NULL,
    photo_type ENUM('front', 'side', 'back', 'other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_progress_photos_checkin
        FOREIGN KEY (check_in_id)
        REFERENCES daily_check_ins(id)
        ON DELETE CASCADE
);


CREATE TABLE meals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    meal_date DATE NOT NULL,
    meal_type ENUM(
        'breakfast',
        'lunch',
        'dinner',
        'snack',
        'pre_workout',
        'post_workout',
        'other'
    ) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_meals_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


CREATE TABLE meal_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meal_id BIGINT UNSIGNED NOT NULL,
    food_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(8,2) NULL,
    unit VARCHAR(30) NULL,
    calories INT UNSIGNED NULL,
    protein DECIMAL(7,2) NULL,
    carbohydrates DECIMAL(7,2) NULL,
    fat DECIMAL(7,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_meal_items_meal
        FOREIGN KEY (meal_id)
        REFERENCES meals(id)
        ON DELETE CASCADE
);


CREATE TABLE workouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    trainer_id BIGINT UNSIGNED NULL,
    workout_name VARCHAR(150) NOT NULL,
    workout_date DATE NOT NULL,
    notes TEXT NULL,
    completed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_workouts_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_workouts_trainer
        FOREIGN KEY (trainer_id)
        REFERENCES users(id)
        ON DELETE SET NULL
);


CREATE TABLE exercises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_name VARCHAR(150) NOT NULL,
    muscle_group VARCHAR(100) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);


CREATE TABLE workout_exercises (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_id BIGINT UNSIGNED NOT NULL,
    exercise_id BIGINT UNSIGNED NOT NULL,
    exercise_order INT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_workout_exercises_workout
        FOREIGN KEY (workout_id)
        REFERENCES workouts(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_workout_exercises_exercise
        FOREIGN KEY (exercise_id)
        REFERENCES exercises(id)
        ON DELETE CASCADE
);


CREATE TABLE exercise_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_exercise_id BIGINT UNSIGNED NOT NULL,
    set_number INT UNSIGNED NOT NULL,
    reps INT UNSIGNED NULL,
    weight DECIMAL(7,2) NULL,
    rir DECIMAL(3,1) NULL,
    completed BOOLEAN NOT NULL DEFAULT FALSE,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_exercise_sets_workout_exercise
        FOREIGN KEY (workout_exercise_id)
        REFERENCES workout_exercises(id)
        ON DELETE CASCADE,

    UNIQUE (workout_exercise_id, set_number)
);


CREATE TABLE training_clips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    exercise_set_id BIGINT UNSIGNED NULL,
    file_path VARCHAR(500) NOT NULL,
    client_notes TEXT NULL,
    uploaded_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_training_clips_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_training_clips_set
        FOREIGN KEY (exercise_set_id)
        REFERENCES exercise_sets(id)
        ON DELETE SET NULL
);


CREATE TABLE clip_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_clip_id BIGINT UNSIGNED NOT NULL,
    trainer_id BIGINT UNSIGNED NOT NULL,
    feedback TEXT NOT NULL,
    video_timestamp_seconds INT UNSIGNED NULL,
    review_status ENUM('pending', 'reviewed') NOT NULL DEFAULT 'pending',
    feedback_date DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_clip_feedback_clip
        FOREIGN KEY (training_clip_id)
        REFERENCES training_clips(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_clip_feedback_trainer
        FOREIGN KEY (trainer_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


CREATE TABLE supplements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplement_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);


CREATE TABLE supplement_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    supplement_id BIGINT UNSIGNED NOT NULL,
    taken_at DATETIME NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_supplement_logs_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_supplement_logs_supplement
        FOREIGN KEY (supplement_id)
        REFERENCES supplements(id)
        ON DELETE CASCADE
);


CREATE TABLE phases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    phases_name VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_cycles_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


CREATE TABLE cycle_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_id BIGINT UNSIGNED NOT NULL,
    substance VARCHAR(150) NOT NULL,
    taken_at DATETIME NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    route VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_cycle_entries_cycle
        FOREIGN KEY (cycle_id)
        REFERENCES phases(id)
        ON DELETE CASCADE
);


CREATE TABLE competitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    competition_name VARCHAR(200) NOT NULL,
    competition_date DATE NOT NULL,
    location VARCHAR(200) NULL,
    category VARCHAR(150) NULL,
    target_weight DECIMAL(5,2) NULL,
    placing VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_competitions_client
        FOREIGN KEY (client_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);



INSERT INTO users
(name, email, password, role, date_of_birth, phone, is_active, created_at, updated_at)
VALUES
('James Wilson', 'james@pttracker.test', '$2y$12$examplehashedpassword1', 'coach', '1990-04-12', '07111111111', TRUE, NOW(), NOW()),
('Sarah Thompson', 'sarah@pttracker.test', '$2y$12$examplehashedpassword2', 'coach', '1993-08-25', '07222222222', TRUE, NOW(), NOW()),
('Daniel Murphy', 'daniel@pttracker.test', '$2y$12$examplehashedpassword3', 'coach', '1988-02-16', '07333333333', TRUE, NOW(), NOW()),

('Adam Brown', 'adam@example.com', '$2y$12$examplehashedpassword4', 'client', '2000-06-14', '07444444444', TRUE, NOW(), NOW()),
('Emily Smith', 'emily@example.com', '$2y$12$examplehashedpassword5', 'client', '1998-11-03', '07555555555', TRUE, NOW(), NOW()),
('Ryan Campbell', 'ryan@example.com', '$2y$12$examplehashedpassword6', 'client', '2002-01-19', '07666666666', TRUE, NOW(), NOW()),
('Chloe Martin', 'chloe@example.com', '$2y$12$examplehashedpassword7', 'client', '2001-09-27', '07777777777', TRUE, NOW(), NOW()),
('Luke Kelly', 'luke@example.com', '$2y$12$examplehashedpassword8', 'client', '1997-05-08', '07888888888', TRUE, NOW(), NOW());


INSERT INTO trainer_clients
(trainer_id, client_id, start_date, status, created_at, updated_at)
VALUES
(1, 4, '2026-09-01', 'active', NOW(), NOW()),
(1, 5, '2026-09-10', 'active', NOW(), NOW()),
(2, 6, '2026-08-15', 'active', NOW(), NOW()),
(2, 7, '2026-09-20', 'active', NOW(), NOW()),
(3, 8, '2026-07-01', 'active', NOW(), NOW());


INSERT INTO daily_check_ins
(client_id, check_in_date, bodyweight, sleep_hours, sleep_quality,
 energy_level, stress_level, steps, water_litres, notes,
 created_at, updated_at)
VALUES
(4, '2026-10-05', 82.40, 7.50, 4, 4, 2, 10500, 3.20,
 'Feeling good today.', NOW(), NOW()),

(5, '2026-10-05', 64.20, 8.00, 5, 5, 2, 9200, 2.70,
 'Good sleep and energy.', NOW(), NOW()),

(6, '2026-10-05', 91.70, 6.50, 3, 3, 4, 7500, 2.40,
 'Slightly tired after training.', NOW(), NOW()),

(7, '2026-10-05', 58.80, 7.25, 4, 4, 3, 11000, 3.00,
 'Recovery feels good.', NOW(), NOW()),

(8, '2026-10-05', 76.50, 8.25, 5, 5, 1, 12500, 3.50,
 'Very good day.', NOW(), NOW()),

(4, '2026-10-06', 82.10, 7.75, 4, 4, 2, 9800, 3.00,
 'Weight slightly down.', NOW(), NOW()),

(5, '2026-10-06', 64.00, 7.50, 4, 4, 2, 10100, 2.80,
 'Training later today.', NOW(), NOW());


INSERT INTO meals
(client_id, meal_date, meal_type, notes, created_at, updated_at)
VALUES
(4, '2026-10-06', 'breakfast', 'Breakfast before work.', NOW(), NOW()),
(4, '2026-10-06', 'lunch', 'Meal prep lunch.', NOW(), NOW()),
(4, '2026-10-06', 'dinner', 'Post-training dinner.', NOW(), NOW()),

(5, '2026-10-06', 'breakfast', NULL, NOW(), NOW()),
(5, '2026-10-06', 'lunch', NULL, NOW(), NOW()),

(6, '2026-10-06', 'breakfast', NULL, NOW(), NOW()),
(6, '2026-10-06', 'dinner', NULL, NOW(), NOW());


INSERT INTO meal_items
(meal_id, food_name, quantity, unit, calories, protein, carbohydrates, fat,
 created_at, updated_at)
VALUES
(1, 'Oats', 80, 'g', 304, 10.00, 54.00, 6.00, NOW(), NOW()),
(1, 'Banana', 1, 'banana', 105, 1.30, 27.00, 0.30, NOW(), NOW()),
(1, 'Whey Protein', 30, 'g', 120, 24.00, 3.00, 2.00, NOW(), NOW()),

(2, 'Chicken Breast', 200, 'g', 330, 62.00, 0.00, 7.00, NOW(), NOW()),
(2, 'Rice', 250, 'g', 325, 6.00, 70.00, 1.00, NOW(), NOW()),
(2, 'Broccoli', 100, 'g', 35, 2.40, 7.00, 0.40, NOW(), NOW()),

(3, 'Lean Beef', 180, 'g', 360, 47.00, 0.00, 18.00, NOW(), NOW()),
(3, 'Potatoes', 300, 'g', 231, 6.00, 51.00, 0.30, NOW(), NOW()),

(4, 'Greek Yogurt', 250, 'g', 180, 25.00, 12.00, 3.00, NOW(), NOW()),
(4, 'Blueberries', 100, 'g', 57, 0.70, 14.00, 0.30, NOW(), NOW()),

(5, 'Turkey', 150, 'g', 200, 43.00, 0.00, 3.00, NOW(), NOW()),
(5, 'Pasta', 200, 'g', 310, 11.00, 62.00, 2.00, NOW(), NOW()),

(6, 'Eggs', 4, 'eggs', 288, 25.00, 2.00, 20.00, NOW(), NOW()),
(6, 'Toast', 2, 'slices', 190, 7.00, 34.00, 2.00, NOW(), NOW()),

(7, 'Salmon', 180, 'g', 374, 40.00, 0.00, 23.00, NOW(), NOW()),
(7, 'Rice', 200, 'g', 260, 5.00, 56.00, 1.00, NOW(), NOW());


INSERT INTO exercises
(exercise_name, muscle_group, description, created_at, updated_at)
VALUES
('Barbell Bench Press', 'Chest', 'Barbell pressing movement for the chest.', NOW(), NOW()),
('Incline Dumbbell Press', 'Chest', 'Incline pressing movement using dumbbells.', NOW(), NOW()),
('Lat Pulldown', 'Back', 'Vertical pulling movement for the back.', NOW(), NOW()),
('Barbell Row', 'Back', 'Horizontal barbell pulling movement.', NOW(), NOW()),
('Back Squat', 'Legs', 'Barbell squat movement.', NOW(), NOW()),
('Leg Press', 'Legs', 'Machine-based leg pressing movement.', NOW(), NOW()),
('Romanian Deadlift', 'Hamstrings', 'Hip hinge movement targeting hamstrings.', NOW(), NOW()),
('Leg Curl', 'Hamstrings', 'Knee flexion exercise for hamstrings.', NOW(), NOW()),
('Shoulder Press', 'Shoulders', 'Overhead pressing movement.', NOW(), NOW()),
('Lateral Raise', 'Shoulders', 'Shoulder isolation exercise.', NOW(), NOW()),
('Bicep Curl', 'Biceps', 'Elbow flexion exercise.', NOW(), NOW()),
('Tricep Pushdown', 'Triceps', 'Cable tricep isolation exercise.', NOW(), NOW());


INSERT INTO workouts
(client_id, trainer_id, workout_name, workout_date, notes, completed,
 created_at, updated_at)
VALUES
(4, 1, 'Push Day', '2026-10-06',
 'Focus on controlled reps and good technique.', TRUE, NOW(), NOW()),

(5, 1, 'Lower Body', '2026-10-06',
 'Keep two reps in reserve on compounds.', TRUE, NOW(), NOW()),

(6, 2, 'Pull Day', '2026-10-06',
 'Focus on back contraction.', FALSE, NOW(), NOW()),

(7, 2, 'Upper Body', '2026-10-07',
 'Moderate volume session.', FALSE, NOW(), NOW()),

(8, 3, 'Leg Day', '2026-10-07',
 'Progress weight where possible.', FALSE, NOW(), NOW());


INSERT INTO workout_exercises
(workout_id, exercise_id, exercise_order, notes, created_at, updated_at)
VALUES
(1, 1, 1, 'Main pressing movement.', NOW(), NOW()),
(1, 2, 2, NULL, NOW(), NOW()),
(1, 10, 3, NULL, NOW(), NOW()),
(1, 12, 4, NULL, NOW(), NOW()),

(2, 5, 1, 'Warm up properly before working sets.', NOW(), NOW()),
(2, 6, 2, NULL, NOW(), NOW()),
(2, 7, 3, NULL, NOW(), NOW()),
(2, 8, 4, NULL, NOW(), NOW()),

(3, 3, 1, NULL, NOW(), NOW()),
(3, 4, 2, NULL, NOW(), NOW()),
(3, 11, 3, NULL, NOW(), NOW()),

(4, 1, 1, NULL, NOW(), NOW()),
(4, 3, 2, NULL, NOW(), NOW()),
(4, 9, 3, NULL, NOW(), NOW()),

(5, 5, 1, NULL, NOW(), NOW()),
(5, 6, 2, NULL, NOW(), NOW()),
(5, 7, 3, NULL, NOW(), NOW());


INSERT INTO exercise_sets
(workout_exercise_id, set_number, reps, weight, rir, completed, notes,
 created_at, updated_at)
VALUES
(1, 1, 10, 70.00, 2, TRUE, NULL, NOW(), NOW()),
(1, 2, 9, 70.00, 2, TRUE, NULL, NOW(), NOW()),
(1, 3, 8, 70.00, 1, TRUE, NULL, NOW(), NOW()),

(2, 1, 10, 26.00, 2, TRUE, NULL, NOW(), NOW()),
(2, 2, 9, 26.00, 1, TRUE, NULL, NOW(), NOW()),
(2, 3, 8, 26.00, 1, TRUE, NULL, NOW(), NOW()),

(3, 1, 15, 8.00, 2, TRUE, NULL, NOW(), NOW()),
(3, 2, 14, 8.00, 2, TRUE, NULL, NOW(), NOW()),
(3, 3, 12, 8.00, 1, TRUE, NULL, NOW(), NOW()),

(4, 1, 12, 30.00, 2, TRUE, NULL, NOW(), NOW()),
(4, 2, 11, 30.00, 1, TRUE, NULL, NOW(), NOW()),
(4, 3, 10, 30.00, 1, TRUE, NULL, NOW(), NOW()),

(5, 1, 8, 80.00, 3, TRUE, NULL, NOW(), NOW()),
(5, 2, 8, 85.00, 2, TRUE, NULL, NOW(), NOW()),
(5, 3, 7, 85.00, 2, TRUE, NULL, NOW(), NOW()),

(6, 1, 12, 140.00, 2, TRUE, NULL, NOW(), NOW()),
(6, 2, 11, 140.00, 2, TRUE, NULL, NOW(), NOW()),
(6, 3, 10, 140.00, 1, TRUE, NULL, NOW(), NOW()),

(7, 1, 10, 60.00, 2, TRUE, NULL, NOW(), NOW()),
(7, 2, 10, 60.00, 2, TRUE, NULL, NOW(), NOW()),
(7, 3, 9, 60.00, 1, TRUE, NULL, NOW(), NOW()),

(8, 1, 12, 35.00, 2, TRUE, NULL, NOW(), NOW()),
(8, 2, 11, 35.00, 2, TRUE, NULL, NOW(), NOW()),
(8, 3, 10, 35.00, 1, TRUE, NULL, NOW(), NOW()),

(9, 1, 12, 60.00, 2, FALSE, NULL, NOW(), NOW()),
(9, 2, 12, 60.00, 2, FALSE, NULL, NOW(), NOW()),
(9, 3, 10, 65.00, 1, FALSE, NULL, NOW(), NOW()),

(10, 1, 10, 60.00, 2, FALSE, NULL, NOW(), NOW()),
(10, 2, 10, 60.00, 2, FALSE, NULL, NOW(), NOW()),
(10, 3, 8, 65.00, 1, FALSE, NULL, NOW(), NOW()),

(11, 1, 12, 15.00, 2, FALSE, NULL, NOW(), NOW()),
(11, 2, 12, 15.00, 2, FALSE, NULL, NOW(), NOW()),
(11, 3, 10, 15.00, 1, FALSE, NULL, NOW(), NOW());


INSERT INTO supplements
(supplement_name, description, created_at, updated_at)
VALUES
('Creatine Monohydrate',
 'Common sports nutrition supplement.', NOW(), NOW()),

('Whey Protein',
 'Protein supplement.', NOW(), NOW()),

('Vitamin D',
 'Vitamin D supplement.', NOW(), NOW()),

('Fish Oil',
 'Omega-3 supplement.', NOW(), NOW()),

('Electrolytes',
 'Electrolyte supplement.', NOW(), NOW());


INSERT INTO supplement_logs
(client_id, supplement_id, taken_at, amount, unit, notes,
 created_at, updated_at)
VALUES
(4, 1, '2026-10-06 08:00:00', 5.00, 'g', NULL, NOW(), NOW()),
(4, 2, '2026-10-06 18:30:00', 30.00, 'g', 'After training.', NOW(), NOW()),
(5, 1, '2026-10-06 09:00:00', 5.00, 'g', NULL, NOW(), NOW()),
(5, 3, '2026-10-06 09:00:00', 1.00, 'tablet', NULL, NOW(), NOW()),
(6, 4, '2026-10-06 08:30:00', 2.00, 'capsules', NULL, NOW(), NOW()),
(7, 1, '2026-10-06 10:00:00', 5.00, 'g', NULL, NOW(), NOW()),
(8, 5, '2026-10-06 16:00:00', 1.00, 'serving', 'Before training.', NOW(), NOW());


INSERT INTO phases
(client_id, phase_name, start_date, end_date, notes, created_at, updated_at)
VALUES
(4, 'Strength Block', '2026-09-01', '2026-10-26',
 'Eight-week strength-focused training block.', NOW(), NOW()),

(5, 'Fat Loss Phase', '2026-09-15', '2026-11-30',
 'Controlled calorie deficit phase.', NOW(), NOW()),

(8, 'Competition Preparation', '2026-08-01', '2026-11-14',
 'Preparation phase leading into competition.', NOW(), NOW());


INSERT INTO cycle_entries
(cycle_id, substance, taken_at, amount, unit, route, notes,
 created_at, updated_at)
VALUES
(1, 'Creatine Monohydrate', '2026-10-06 08:00:00',
 5.00, 'g', 'oral', NULL, NOW(), NOW()),

(2, 'Whey Protein', '2026-10-06 10:00:00',
 30.00, 'g', 'oral', NULL, NOW(), NOW()),

(3, 'Electrolytes', '2026-10-06 15:00:00',
 1.00, 'serving', 'oral', 'Pre-training.', NOW(), NOW());


INSERT INTO competitions
(client_id, competition_name, competition_date, location, category,
 target_weight, placing, notes, created_at, updated_at)
VALUES
(8, 'Northern Strength Classic', '2026-11-14',
 'Belfast', 'Mens Open', 75.00, NULL,
 'Main competition for current preparation phase.', NOW(), NOW()),

(4, 'Winter Powerlifting Meet', '2026-12-05',
 'Belfast', 'Mens 83kg', 83.00, NULL,
 'First powerlifting competition.', NOW(), NOW()),

(5, 'Spring Fitness Competition', '2027-03-20',
 'Lisburn', 'Womens Open', 62.00, NULL,
 'Planned competition for next year.', NOW(), NOW());