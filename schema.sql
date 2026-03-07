-- ============================================================
-- RescueNet Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS rescuenet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rescuenet_db;

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(80)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','responder','rescuer','management') NOT NULL DEFAULT 'rescuer',
    rescuer_id    INT          NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- PATIENT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patient (
    patient_id      INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150) NOT NULL,
    age             TINYINT      UNSIGNED,
    gender          ENUM('male','female','other'),
    blood_type      VARCHAR(5),
    allergies       TEXT,
    medical_history TEXT,
    contact_number  VARCHAR(30),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- INCIDENT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incident (
    incident_id   INT AUTO_INCREMENT PRIMARY KEY,
    patient_id    INT  NOT NULL,
    rescuer_id    INT,
    incident_type VARCHAR(100),
    severity      ENUM('low','medium','high','critical') DEFAULT 'medium',
    status        ENUM('transferred','ongoing','completed') DEFAULT 'transferred',
    location      VARCHAR(255),
    start_time    DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time      DATETIME NULL,
    outcome       VARCHAR(50) NULL,
    notes         TEXT,
    close_notes   TEXT,
    FOREIGN KEY (patient_id) REFERENCES patient(patient_id)
);

-- ------------------------------------------------------------
-- VITAL STATISTICS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vitalstat (
    vital_id         INT AUTO_INCREMENT PRIMARY KEY,
    incident_id      INT NOT NULL,
    heart_rate       SMALLINT,
    blood_pressure   VARCHAR(15),
    spo2             DECIMAL(4,1),
    temperature      DECIMAL(4,1),
    respiratory_rate TINYINT,
    gcs_score        TINYINT,
    notes            TEXT,
    recorded_by      VARCHAR(100),
    recorded_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incident(incident_id)
);

-- ------------------------------------------------------------
-- DEVICE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device (
    device_id   INT AUTO_INCREMENT PRIMARY KEY,
    device_name VARCHAR(120) NOT NULL,
    device_type VARCHAR(80),
    serial_number VARCHAR(60),
    status      ENUM('available','assigned','maintenance') DEFAULT 'available'
);

-- ------------------------------------------------------------
-- DEVICE LOG
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device_log (
    log_id              INT AUTO_INCREMENT PRIMARY KEY,
    device_id           INT NOT NULL,
    incident_id         INT NOT NULL,
    date_issued         DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_returned       DATETIME NULL,
    return_requested_at DATETIME NULL,
    return_status       ENUM('none','pending','confirmed') DEFAULT 'none',
    return_condition    VARCHAR(30) DEFAULT 'good',
    return_notes        TEXT,
    FOREIGN KEY (device_id)   REFERENCES device(device_id),
    FOREIGN KEY (incident_id) REFERENCES incident(incident_id)
);

-- ============================================================
-- SAMPLE DATA
-- ============================================================

INSERT INTO users (username, password_hash, role, rescuer_id) VALUES
('rescuer1', '$2y$12$examplehashedpassword1234567890abcdef', 'rescuer', 1);

INSERT INTO patient (full_name, age, gender, blood_type, allergies, medical_history, contact_number) VALUES
('Maria Santos',   34, 'female', 'O+',  'Penicillin',    'Hypertension',   '09171234567'),
('Juan dela Cruz', 52, 'male',   'A+',  'None',          'Diabetes Type 2','09181234567'),
('Ana Reyes',      28, 'female', 'B+',  'Sulfa drugs',   'Asthma',         '09191234567'),
('Carlos Mendoza', 67, 'male',   'AB-', 'Aspirin',       'Heart disease',  '09201234567');

INSERT INTO incident (patient_id, rescuer_id, incident_type, severity, status, location, start_time) VALUES
(1, 1, 'Cardiac Arrest',       'critical', 'transferred', 'Brgy. Poblacion, Main St.',    NOW() - INTERVAL 2 HOUR),
(2, 1, 'Diabetic Emergency',   'high',     'ongoing',     'Purok 3, Rizal Ave.',           NOW() - INTERVAL 5 HOUR),
(3, 1, 'Respiratory Distress', 'medium',   'completed',   'Brgy. San Jose, Mabini St.',   NOW() - INTERVAL 1 DAY),
(4, 1, 'Trauma — MVA',         'high',     'completed',   'National Highway Km 12',        NOW() - INTERVAL 3 DAY);

INSERT INTO vitalstat (incident_id, heart_rate, blood_pressure, spo2, temperature, respiratory_rate, gcs_score, notes, recorded_by) VALUES
(2, 112, '145/90', 96.0, 37.2, 20, 15, 'Patient conscious, diaphoretic', 'rescuer:rescuer1'),
(2,  98, '138/85', 97.5, 37.0, 18, 15, 'Improving after glucose admin',  'rescuer:rescuer1'),
(3,  88, '120/78', 94.0, 38.1, 24, 14, 'Mild respiratory distress',      'rescuer:rescuer1'),
(4, 105, '100/60', 95.0, 36.9, 22, 12, 'Trauma patient, GCS declining',  'rescuer:rescuer1');

INSERT INTO device (device_name, device_type, serial_number, status) VALUES
('Pulse Oximeter A',   'Vital Monitor', 'SN-PO-001', 'assigned'),
('AED Unit 1',         'Defibrillator', 'SN-AED-001','assigned'),
('Stretcher Unit 2',   'Transport',     'SN-ST-002', 'assigned');

INSERT INTO device_log (device_id, incident_id, date_issued) VALUES
(1, 2, NOW() - INTERVAL 5 HOUR),
(2, 1, NOW() - INTERVAL 2 HOUR),
(3, 2, NOW() - INTERVAL 5 HOUR);
