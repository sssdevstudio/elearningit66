<?php
session_start();

// กำหนดค่าการเชื่อมต่อ MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // เปลี่ยนตามค่า MySQL ของคุณ
define('DB_PASS', ''); // เปลี่ยนตามค่า MySQL ของคุณ
define('DB_NAME', 'game_development_db'); // ชื่อฐานข้อมูล

// ฟังก์ชันเชื่อมต่อฐานข้อมูล MySQL
function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $db;
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// สร้างตารางฐานข้อมูลหากยังไม่มี
function initDB() {
    $db = getDB();
    
    try {
        // สร้างฐานข้อมูลหากยังไม่มี
        $db->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db->exec("USE " . DB_NAME);
    } catch(PDOException $e) {
        // หากมีข้อผิดพลาด ให้ลองเชื่อมต่อใหม่โดยตรง
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $db = new PDO($dsn, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    // ตารางผู้ใช้
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางหมวดหมู่บทเรียน
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางบทเรียน
    $db->exec("CREATE TABLE IF NOT EXISTS lessons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        content LONGTEXT NOT NULL,
        video_url VARCHAR(500),
        video_duration INT DEFAULT 0,
        duration_minutes INT DEFAULT 0,
        difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
        is_published BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_category_id (category_id),
        INDEX idx_created_by (created_by),
        INDEX idx_is_published (is_published),
        INDEX idx_difficulty (difficulty)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางแบบทดสอบ
    $db->exec("CREATE TABLE IF NOT EXISTS quizzes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_id INT NOT NULL,
        question TEXT NOT NULL,
        question_type ENUM('single_choice', 'multiple_choice', 'true_false') DEFAULT 'single_choice',
        option_a TEXT,
        option_b TEXT,
        option_c TEXT,
        option_d TEXT,
        option_e TEXT,
        correct_answer VARCHAR(10) NOT NULL,
        explanation TEXT,
        points INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_lesson_id (lesson_id),
        INDEX idx_question_type (question_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางผลลัพธ์แบบทดสอบ
    $db->exec("CREATE TABLE IF NOT EXISTS quiz_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_id INT NOT NULL,
        lesson_id INT NOT NULL,
        user_answer VARCHAR(10),
        is_correct BOOLEAN DEFAULT FALSE,
        time_spent_seconds INT DEFAULT 0,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_lesson_id (lesson_id),
        INDEX idx_completed_at (completed_at),
        INDEX idx_user_lesson (user_id, lesson_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางคะแนนรวม
    $db->exec("CREATE TABLE IF NOT EXISTS lesson_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lesson_id INT NOT NULL,
        total_questions INT DEFAULT 0,
        correct_answers INT DEFAULT 0,
        total_score INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0.00,
        attempt_count INT DEFAULT 1,
        best_score DECIMAL(5,2) DEFAULT 0.00,
        last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_lesson (user_id, lesson_id),
        INDEX idx_user_id (user_id),
        INDEX idx_lesson_id (lesson_id),
        INDEX idx_percentage (percentage)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางความคืบหน้าผู้ใช้
    $db->exec("CREATE TABLE IF NOT EXISTS user_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lesson_id INT NOT NULL,
        is_completed BOOLEAN DEFAULT FALSE,
        completed_at TIMESTAMP NULL,
        last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_lesson_progress (user_id, lesson_id),
        INDEX idx_user_id (user_id),
        INDEX idx_is_completed (is_completed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // ตารางประวัติการดูวิดีโอ (ใหม่)
    $db->exec("CREATE TABLE IF NOT EXISTS video_watch_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lesson_id INT NOT NULL,
        video_progress INT DEFAULT 0,
        is_completed BOOLEAN DEFAULT FALSE,
        last_watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        INDEX idx_user_lesson (user_id, lesson_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // เพิ่มผู้ใช้เริ่มต้นหากยังไม่มี
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $hashed_password, 'Admin GameDev', 'admin@gamedev.com', 'admin']);
        
        // เพิ่มผู้ใช้ตัวอย่างอื่นๆ
        $stmt->execute(['teacher1', $hashed_password, 'ครูเกมพัฒนา', 'teacher@gamedev.com', 'teacher']);
        $stmt->execute(['student1', $hashed_password, 'นักเรียนเกมเมอร์', 'student@gamedev.com', 'student']);
    }
    
    // เพิ่มหมวดหมู่เริ่มต้น
    $stmt = $db->prepare("SELECT COUNT(*) FROM categories");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $categories = [
            ['Unreal Engine', 'เรียนรู้การสร้างเกมด้วย Unreal Engine 5 สำหรับมือใหม่ถึงระดับกลาง'],
            ['Unity', 'เรียนรู้การสร้างเกม 2D และ 3D ด้วย Unity Game Engine'],
            ['GameMaker Studio', 'เรียนรู้การสร้างเกม 2D ด้วย GameMaker Studio สำหรับมือใหม่']
        ];
        
        $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        foreach ($categories as $category) {
            $stmt->execute($category);
        }
        
        // เพิ่มบทเรียนตัวอย่าง
        addSampleLessons($db);
    }
    
    return $db;
}

// เพิ่มบทเรียนตัวอย่าง
function addSampleLessons($db) {
    // 1. Unreal Engine
    $db->exec("INSERT INTO lessons (category_id, title, description, content, video_url, video_duration, duration_minutes, difficulty, created_by) VALUES 
        (1, 'เริ่มต้นกับ Unreal Engine 5', 'พื้นฐานการใช้งาน Unreal Engine 5 สำหรับมือใหม่', 
        'Unreal Engine 5 เป็นเกมเอนจินที่ทรงพลังสำหรับสร้างเกมระดับ AAA\n\nสิ่งที่คุณจะได้เรียน:\n1. การติดตั้งและตั้งค่า Unreal Engine 5\n2. รู้จักกับ Interface หลัก\n3. การสร้างโปรเจ็กต์ใหม่\n4. การนำเข้าสื่อ (Assets)\n5. พื้นฐานการทำงานกับ Level',
        'https://www.youtube.com/embed/kay1wKXMBi8', 1200, 45, 'beginner', 1)");
    
    $lesson_id = $db->lastInsertId();
    addSampleQuizzes($db, $lesson_id, 'Unreal Engine');
    
    // 2. Unity
    $db->exec("INSERT INTO lessons (category_id, title, description, content, video_url, video_duration, duration_minutes, difficulty, created_by) VALUES 
        (2, 'เริ่มต้นกับ Unity 2022', 'พื้นฐานการใช้งาน Unity สำหรับการสร้างเกม 2D/3D', 
        'Unity เป็นเกมเอนจินที่ได้รับความนิยมสูงสำหรับสร้างเกมทุกประเภท\n\nสิ่งที่คุณจะได้เรียน:\n1. การติดตั้งและตั้งค่า Unity Hub\n2. รู้จักกับ Unity Interface\n3. การสร้างโปรเจ็กต์ใหม่\n4. พื้นฐานการทำงานกับ Scene\n5. การใช้ GameObjects และ Components',
        'https://www.youtube.com/embed/pwZpJzpE2lQ', 1500, 50, 'beginner', 1)");
    
    $lesson_id = $db->lastInsertId();
    addSampleQuizzes($db, $lesson_id, 'Unity');
    
    // 3. GameMaker Studio
    $db->exec("INSERT INTO lessons (category_id, title, description, content, video_url, video_duration, duration_minutes, difficulty, created_by) VALUES 
        (3, 'เริ่มต้นกับ GameMaker Studio 2', 'พื้นฐานการสร้างเกม 2D ด้วย GameMaker Studio', 
        'GameMaker Studio 2 เป็นเครื่องมือที่ยอดเยี่ยมสำหรับการสร้างเกม 2D โดยไม่ต้องเขียนโค้ด\n\nสิ่งที่คุณจะได้เรียน:\n1. การติดตั้งและตั้งค่า GameMaker Studio 2\n2. รู้จักกับ Interface หลัก\n3. การสร้างโปรเจ็กต์ใหม่\n4. พื้นฐานการทำงานกับ Rooms\n5. การสร้าง Objects และ Sprites',
        'https://www.youtube.com/embed/h8LcYQhQ9cU', 1200, 40, 'beginner', 1)");
    
    $lesson_id = $db->lastInsertId();
    addSampleQuizzes($db, $lesson_id, 'GameMaker');
}

// เพิ่มแบบทดสอบตัวอย่าง
function addSampleQuizzes($db, $lesson_id, $subject) {
    if ($subject == 'Unreal Engine') {
        $quizzes = [
            ['Unreal Engine 5 ใช้ภาษาสคริปต์หลักคือภาษาอะไร?', 'single_choice', 'C++ และ Blueprint', 'Python', 'JavaScript', 'C#', 'a', 'Unreal Engine ใช้ C++ สำหรับการเขียนโค้ดและ Blueprint สำหรับ Visual Scripting', 1],
            ['Blueprint ใน Unreal Engine คืออะไร?', 'single_choice', 'ระบบ Visual Scripting', 'โปรแกรมสร้างโมเดล 3D', 'เครื่องมือสร้าง Animation', 'โปรแกรมแก้ไข Texture', 'a', 'Blueprint เป็นระบบ Visual Scripting ที่ช่วยให้สร้างเกมได้โดยไม่ต้องเขียนโค้ด', 1],
            ['เครื่องมือใดใช้สำหรับสร้างภูมิประเทศใน Unreal Engine?', 'single_choice', 'Landscape Tool', 'Terrain Editor', 'World Builder', 'Map Creator', 'a', 'Landscape Tool เป็นเครื่องมือหลักสำหรับสร้างและแก้ไขภูมิประเทศ', 1]
        ];
    } elseif ($subject == 'Unity') {
        $quizzes = [
            ['Unity ใช้ภาษาโปรแกรมหลักคือภาษาอะไร?', 'single_choice', 'C#', 'Java', 'Python', 'C++', 'a', 'Unity ใช้ C# เป็นภาษาโปรแกรมหลักสำหรับการพัฒนาเกม', 1],
            ['Component อะไรที่ควบคุมตำแหน่ง การหมุน และขนาดของ GameObject?', 'single_choice', 'Transform', 'Rigidbody', 'Collider', 'Renderer', 'a', 'Transform Component ควบคุมตำแหน่ง การหมุน และขนาดของ GameObject', 1],
            ['Prefab ใน Unity คืออะไร?', 'single_choice', 'เทมเพลตของ GameObject ที่สามารถนำกลับมาใช้ซ้ำได้', 'ชุดของ Texture', 'ระบบ Animation', 'เครื่องมือสร้างเสียง', 'a', 'Prefab เป็นเทมเพลตของ GameObject ที่สามารถสร้างและใช้ซ้ำได้หลายครั้ง', 1]
        ];
    } elseif ($subject == 'GameMaker') {
        $quizzes = [
            ['ภาษาโปรแกรมที่ใช้ใน GameMaker Studio 2 คืออะไร?', 'single_choice', 'GameMaker Language (GML)', 'Python', 'JavaScript', 'C++', 'a', 'GameMaker ใช้ภาษา GML (GameMaker Language) สำหรับการเขียนโค้ด', 1],
            ['Event ใดใน GameMaker ที่ทำงานทุกเฟรม?', 'single_choice', 'Step Event', 'Create Event', 'Draw Event', 'Alarm Event', 'a', 'Step Event ทำงานทุกเฟรมของเกม เหมาะสำหรับการอัปเดตตรรกะเกม', 1],
            ['Object ใน GameMaker คืออะไร?', 'single_choice', 'เอนทิตีในเกมที่มี Behavior และ Logic', 'ไฟล์ภาพ', 'ไฟล์เสียง', 'ไฟล์ข้อความ', 'a', 'Object เป็นเอนทิตีในเกมที่มี Behavior และ Logic กำหนดโดย Events และ Code', 1]
        ];
    }
    
    if (isset($quizzes)) {
        $stmt = $db->prepare("INSERT INTO quizzes (lesson_id, question, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, points, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($quizzes as $quiz) {
            $stmt->execute([$lesson_id, $quiz[0], $quiz[1], $quiz[2], $quiz[3], $quiz[4], $quiz[5], $quiz[6], $quiz[7], $quiz[8], 1]);
        }
    }
}

// เรียกใช้ฟังก์ชันสร้างฐานข้อมูล
try {
    $db = initDB();
} catch(Exception $e) {
    die("Error initializing database: " . $e->getMessage());
}

// ตรวจสอบการเข้าสู่ระบบ
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['user_role'] : 'guest';
$current_user = $is_logged_in ? $_SESSION['username'] : '';

// ฟังก์ชันจัดการการเข้าสู่ระบบ
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            return "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        }
        
        $db = getDB();
        $db->exec("USE " . DB_NAME);
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            
            // เด้งไปหน้าหลักทันที
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=home");
            exit();
            
            return true;
        } else {
            return "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    }
    return null;
}

// ฟังก์ชันจัดการการลงทะเบียน (เพิ่มฟังก์ชันนี้)
function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'register') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $fullname = $_POST['fullname'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // ตรวจสอบข้อมูล
        if (empty($username) || empty($password) || empty($fullname)) {
            return "กรุณากรอกข้อมูลให้ครบถ้วน";
        }
        
        if (strlen($password) < 6) {
            return "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
        }
        
        if ($password !== $confirm_password) {
            return "รหัสผ่านไม่ตรงกัน";
        }
        
        $db = getDB();
        $db->exec("USE " . DB_NAME);
        
        // ตรวจสอบว่ามีผู้ใช้ชื่อนี้แล้วหรือไม่
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            return "ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น";
        }
        
        // เพิ่มผู้ใช้ใหม่
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, fullname, email) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $hashed_password, $fullname, $email])) {
            $user_id = $db->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['user_role'] = 'student';
            $_SESSION['email'] = $email;
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=home");
            exit();
            
            return true;
        } else {
            return "เกิดข้อผิดพลาดในการลงทะเบียน";
        }
    }
    return null;
}

// ฟังก์ชันจัดการการออกจากระบบ
function handleLogout() {
    if (isset($_GET['action']) && $_GET['action'] == 'logout') {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ฟังก์ชันเพิ่มบทเรียน
function addLesson() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_lesson') {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $content = $_POST['content'] ?? '';
        $category_id = $_POST['category_id'] ?? 0;
        $video_url = $_POST['video_url'] ?? '';
        $duration = $_POST['duration'] ?? 0;
        $difficulty = $_POST['difficulty'] ?? 'beginner';
        $is_published = $_POST['is_published'] ?? 1;
        $created_by = $_SESSION['user_id'] ?? 0;
        
        if (empty($title) || empty($content)) {
            return "กรุณากรอกหัวข้อและเนื้อหาบทเรียน";
        }
        
        $db = getDB();
        $db->exec("USE " . DB_NAME);
        $stmt = $db->prepare("INSERT INTO lessons (title, description, content, category_id, video_url, duration_minutes, difficulty, is_published, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $description, $content, $category_id, $video_url, $duration, $difficulty, $is_published, $created_by])) {
            return true;
        } else {
            return "เกิดข้อผิดพลาดในการเพิ่มบทเรียน";
        }
    }
    return null;
}

// ฟังก์ชันเพิ่มแบบทดสอบ
function addQuiz() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_quiz') {
        $lesson_id = $_POST['lesson_id'] ?? 0;
        $question = $_POST['question'] ?? '';
        $question_type = $_POST['question_type'] ?? 'single_choice';
        $option_a = $_POST['option_a'] ?? '';
        $option_b = $_POST['option_b'] ?? '';
        $option_c = $_POST['option_c'] ?? '';
        $option_d = $_POST['option_d'] ?? '';
        $option_e = $_POST['option_e'] ?? '';
        $correct_answer = $_POST['correct_answer'] ?? '';
        $explanation = $_POST['explanation'] ?? '';
        $points = $_POST['points'] ?? 1;
        $created_by = $_SESSION['user_id'] ?? 0;
        
        if (empty($question) || empty($lesson_id)) {
            return "กรุณากรอกคำถามและเลือกบทเรียน";
        }
        
        $db = getDB();
        $db->exec("USE " . DB_NAME);
        $stmt = $db->prepare("INSERT INTO quizzes (lesson_id, question, question_type, option_a, option_b, option_c, option_d, option_e, correct_answer, explanation, points, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$lesson_id, $question, $question_type, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_answer, $explanation, $points, $created_by])) {
            return true;
        } else {
            return "เกิดข้อผิดพลาดในการเพิ่มแบบทดสอบ";
        }
    }
    return null;
}

// ฟังก์ชันส่งคำตอบแบบทดสอบ
function submitQuiz() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_quiz') {
        $lesson_id = $_POST['lesson_id'] ?? 0;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if (!$user_id) {
            return "กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ";
        }
        
        $db = getDB();
        $db->exec("USE " . DB_NAME);
        
        // ดึงคำถามทั้งหมดของบทเรียนนี้
        $stmt = $db->prepare("SELECT * FROM quizzes WHERE lesson_id = ?");
        $stmt->execute([$lesson_id]);
        $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_score = 0;
        $total_questions = count($quizzes);
        $correct_answers = 0;
        
        foreach ($quizzes as $quiz) {
            $quiz_id = $quiz['id'];
            $user_answer = $_POST["answer_{$quiz_id}"] ?? '';
            
            // ตรวจสอบคำตอบ
            $is_correct = false;
            if ($quiz['question_type'] == 'multiple_choice') {
                $user_answers = is_array($user_answer) ? $user_answer : [$user_answer];
                $correct_answers_array = explode(',', $quiz['correct_answer']);
                sort($user_answers);
                sort($correct_answers_array);
                $is_correct = ($user_answers == $correct_answers_array);
            } else {
                $is_correct = ($user_answer == $quiz['correct_answer']);
            }
            
            if ($is_correct) {
                $total_score += $quiz['points'];
                $correct_answers++;
            }
            
            // บันทึกผลลัพธ์
            $answer_str = is_array($user_answer) ? implode(',', $user_answer) : $user_answer;
            $stmt = $db->prepare("INSERT INTO quiz_results (user_id, quiz_id, lesson_id, user_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $quiz_id, $lesson_id, $answer_str, $is_correct ? 1 : 0]);
        }
        
        // คำนวณเปอร์เซ็นต์
        $percentage = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 2) : 0;
        
        // อัปเดตหรือเพิ่มคะแนนรวม
        $stmt = $db->prepare("SELECT * FROM lesson_scores WHERE user_id = ? AND lesson_id = ?");
        $stmt->execute([$user_id, $lesson_id]);
        $existing_score = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_score) {
            // อัปเดตคะแนน
            $attempt_count = $existing_score['attempt_count'] + 1;
            $best_score = max($existing_score['best_score'], $percentage);
            
            $stmt = $db->prepare("UPDATE lesson_scores SET 
                total_questions = ?, 
                correct_answers = correct_answers + ?, 
                total_score = total_score + ?, 
                percentage = ?, 
                attempt_count = ?, 
                best_score = ?, 
                last_attempt_at = NOW() 
                WHERE user_id = ? AND lesson_id = ?");
            $stmt->execute([$total_questions, $correct_answers, $total_score, $percentage, $attempt_count, $best_score, $user_id, $lesson_id]);
        } else {
            // เพิ่มคะแนนใหม่
            $stmt = $db->prepare("INSERT INTO lesson_scores (user_id, lesson_id, total_questions, correct_answers, total_score, percentage, best_score) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $lesson_id, $total_questions, $correct_answers, $total_score, $percentage, $percentage]);
        }
        
        // อัปเดตความคืบหน้า
        $stmt = $db->prepare("INSERT INTO user_progress (user_id, lesson_id, is_completed, completed_at, last_accessed_at) 
                              VALUES (?, ?, ?, NOW(), NOW()) 
                              ON DUPLICATE KEY UPDATE 
                              last_accessed_at = NOW(), 
                              is_completed = GREATEST(is_completed, ?)");
        $stmt->execute([$user_id, $lesson_id, 1, 1]);
        
        return [
            'total_score' => $total_score,
            'total_questions' => $total_questions,
            'correct_answers' => $correct_answers,
            'percentage' => $percentage
        ];
    }
    return null;
}

// ฟังก์ชันดึงข้อมูล
function getCategories() {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLessons($category_id = null, $search = null, $limit = null, $offset = 0) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    
    $sql = "SELECT l.*, c.name as category_name, u.fullname as creator_name 
            FROM lessons l 
            LEFT JOIN categories c ON l.category_id = c.id 
            LEFT JOIN users u ON l.created_by = u.id 
            WHERE l.is_published = 1";
    
    $params = [];
    
    if ($category_id) {
        $sql .= " AND l.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($search) {
        $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.content LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
    }
    
    $stmt = $db->prepare($sql);
    
    // Bind parameters with proper types
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key + 1, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key + 1, $value, PDO::PARAM_STR);
        }
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLesson($lesson_id) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->prepare("SELECT l.*, c.name as category_name, u.fullname as creator_name 
                         FROM lessons l 
                         LEFT JOIN categories c ON l.category_id = c.id 
                         LEFT JOIN users u ON l.created_by = u.id 
                         WHERE l.id = ?");
    $stmt->execute([$lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getQuizzes($lesson_id) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->prepare("SELECT * FROM quizzes WHERE lesson_id = ? ORDER BY id");
    $stmt->execute([$lesson_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserStats($user_id) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    
    $stats = [];
    
    // จำนวนบทเรียนทั้งหมด
    $stmt = $db->prepare("SELECT COUNT(*) as total_lessons FROM lessons WHERE is_published = 1");
    $stmt->execute();
    $stats['total_lessons'] = $stmt->fetchColumn();
    
    // จำนวนบทเรียนที่เรียนแล้ว
    $stmt = $db->prepare("SELECT COUNT(DISTINCT lesson_id) as lessons_completed FROM user_progress WHERE user_id = ? AND is_completed = 1");
    $stmt->execute([$user_id]);
    $stats['lessons_completed'] = $stmt->fetchColumn();
    
    // จำนวนคำถามทั้งหมดที่ตอบ
    $stmt = $db->prepare("SELECT COUNT(*) as total_attempts FROM quiz_results WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_attempts'] = $stmt->fetchColumn();
    
    // จำนวนคำถามที่ตอบถูก
    $stmt = $db->prepare("SELECT COUNT(*) as correct_attempts FROM quiz_results WHERE user_id = ? AND is_correct = 1");
    $stmt->execute([$user_id]);
    $stats['correct_attempts'] = $stmt->fetchColumn();
    
    // คะแนนเฉลี่ย
    $stmt = $db->prepare("SELECT AVG(percentage) as average_score FROM lesson_scores WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['average_score'] = round($stmt->fetchColumn() ?? 0, 2);
    
    // คะแนนรวม
    $stmt = $db->prepare("SELECT SUM(total_score) as total_score FROM lesson_scores WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_score'] = $stmt->fetchColumn() ?? 0;
    
    return $stats;
}

function getUserProgress($user_id) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->prepare("
        SELECT l.id, l.title, l.difficulty, c.name as category_name,
               up.is_completed, up.completed_at,
               ls.percentage, ls.best_score, ls.attempt_count
        FROM lessons l
        LEFT JOIN categories c ON l.category_id = c.id
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        LEFT JOIN lesson_scores ls ON l.id = ls.lesson_id AND ls.user_id = ?
        WHERE l.is_published = 1
        ORDER BY up.last_accessed_at DESC, l.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopStudents($limit = 10) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.fullname, 
               COUNT(DISTINCT ls.lesson_id) as lessons_completed,
               AVG(ls.percentage) as average_score,
               SUM(ls.total_score) as total_score
        FROM users u
        LEFT JOIN lesson_scores ls ON u.id = ls.user_id
        WHERE u.role = 'student'
        GROUP BY u.id
        HAVING lessons_completed > 0
        ORDER BY total_score DESC, average_score DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ฟังก์ชันใหม่: ดึงบทเรียนสำหรับครู/แอดมิน
function getLessonsForManagement() {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->query("SELECT l.*, c.name as category_name, u.fullname as creator_name 
                       FROM lessons l 
                       LEFT JOIN categories c ON l.category_id = c.id 
                       LEFT JOIN users u ON l.created_by = u.id 
                       ORDER BY l.created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ฟังก์ชันใหม่: ลบบทเรียน
function deleteLesson($lesson_id) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->prepare("DELETE FROM lessons WHERE id = ?");
    return $stmt->execute([$lesson_id]);
}

// ฟังก์ชันใหม่: ลบแบบทดสอบ
function deleteQuiz($quiz_id) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    $stmt = $db->prepare("DELETE FROM quizzes WHERE id = ?");
    return $stmt->execute([$quiz_id]);
}

// ฟังก์ชันใหม่: ดึงแบบทดสอบทั้งหมดสำหรับจัดการ
function getQuizzesForManagement($lesson_id = null) {
    $db = getDB();
    $db->exec("USE " . DB_NAME);
    
    if ($lesson_id) {
        $stmt = $db->prepare("SELECT q.*, l.title as lesson_title FROM quizzes q 
                             JOIN lessons l ON q.lesson_id = l.id 
                             WHERE q.lesson_id = ? 
                             ORDER BY q.id");
        $stmt->execute([$lesson_id]);
    } else {
        $stmt = $db->query("SELECT q.*, l.title as lesson_title FROM quizzes q 
                           JOIN lessons l ON q.lesson_id = l.id 
                           ORDER BY q.created_at DESC");
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// เรียกใช้ฟังก์ชันจัดการต่างๆ
$login_result = handleLogin();
$register_result = handleRegister();
$logout_result = handleLogout();
$add_lesson_result = addLesson();
$add_quiz_result = addQuiz();
$submit_quiz_result = submitQuiz();

// ตั้งค่าหน้ากระดาษ
$page = $_GET['page'] ?? 'home';
$lesson_id = $_GET['id'] ?? 0;
$category_id = $_GET['category'] ?? null;
$search = $_GET['search'] ?? '';

// ตั้งค่าสำหรับการจัดการ
$section = $_GET['section'] ?? 'dashboard';
$edit_id = $_GET['edit_id'] ?? 0;
$delete_id = $_GET['delete_id'] ?? 0;

// จัดการการลบ
if ($delete_id && in_array($user_role, ['teacher', 'admin'])) {
    if (isset($_GET['type']) && $_GET['type'] == 'lesson') {
        deleteLesson($delete_id);
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=manage&section=lessons");
        exit();
    } elseif (isset($_GET['type']) && $_GET['type'] == 'quiz') {
        deleteQuiz($delete_id);
        header("Location: " . $_SERVER['PHP_SELF'] . "?page=manage&section=quizzes");
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>ระบบสื่อการสอนพัฒนาเกม - Game Development Learning</title>
    <script>
        const API_URL = 'https://your-backend-host.com/api.php';
        
        async function fetchLessons() {
            try {
                const response = await fetch(`${API_URL}?action=get_lessons`);
                const data = await response.json();
                displayLessons(data.data);
            } catch(error) {
                console.error('Error:', error);
            }
        }
        
        function displayLessons(lessons) {
            // แสดงผลบทเรียน
        }
    </script>
    
    <!-- เพิ่ม Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- เพิ่ม Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- เพิ่ม Animate.css สำหรับอนิเมชั่น -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        /* CSS Reset และ Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4a6bff;
            --primary-dark: #3a56d4;
            --secondary: #6c63ff;
            --accent: #ff6b6b;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --dark: #2d3436;
            --light: #f8f9fa;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        body {
            font-family: 'Poppins', 'Kanit', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        /* Main Container */
        .container {
            max-width: 1900px;
            margin: 0 auto;
            padding: 0 0px;
            min-height: 100vh;
            position: relative;
        }

        /* Header Styles */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 20px 20px;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 200px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            animation: bounce 2s infinite;
            flex-shrink: 0;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .logo-text p {
            font-size: 0.8rem;
            color: var(--gray);
            margin: 0;
        }

        /* Navigation */
        nav {
            flex: 1;
            min-width: 300px;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        nav ul li a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border-radius: 50px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        nav ul li a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--primary);
            color: var(--primary);
        }

        nav ul li a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        /* Search Box */
        .search-box {
            flex: 1;
            max-width: 500px;
            position: relative;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px;
            padding-left: 50px;
            border: 2px solid var(--light-gray);
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            -webkit-appearance: none;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 200px;
            justify-content: flex-end;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 25px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            -webkit-appearance: none;
            touch-action: manipulation;
        }

        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 107, 255, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #2ecc71);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #e74c3c);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left: 5px solid var(--success);
            color: #155724;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border-left: 5px solid var(--danger);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border-left: 5px solid var(--primary);
            color: #0c5460;
        }

        /* Main Content */
        .main-content {
            display: center;
            grid-template-columns: 1000px 1fr;
            gap: 25px;
            margin-top: 30px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Sidebar */
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .sidebar h3 {
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-list {
            list-style: none;
        }

        .category-list li {
            margin-bottom: 8px;
        }

        .category-list a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .category-list a:hover,
        .category-list a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            transform: translateX(5px);
        }

        /* Content Area */
        .content-area {
            min-height: calc(100vh - 200px);
        }

        /* Card Styles */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            animation: cardAppear 0.6s ease-out;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card h2 {
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(74, 107, 255, 0.3);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }

        /* Lesson Grid */
        .lesson-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .lesson-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .lesson-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .lesson-card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            position: relative;
        }

        .lesson-card-header h3 {
            font-size: 1.2rem;
            margin: 0;
        }

        .lesson-card-body {
            padding: 20px;
        }

        .lesson-meta {
            display: flex;
            justify-content: space-between;
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        /* Video Container */
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 15px;
            margin-bottom: 25px;
            background: #000;
        }

        .video-container iframe,
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .video-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: rgba(255, 255, 255, 0.3);
            z-index: 10;
        }

        .video-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--success), #2ecc71);
            width: 0%;
            transition: width 0.3s ease;
        }

        /* Quiz Styles */
        .quiz-question {
            background: linear-gradient(135deg, #f8f9ff, #eef1ff);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 5px solid var(--primary);
            animation: slideInLeft 0.5s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .quiz-options {
            margin-top: 20px;
        }

        .quiz-option {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            -webkit-tap-highlight-color: transparent;
        }

        .quiz-option:hover {
            border-color: var(--primary);
            transform: translateX(5px);
        }

        .quiz-option.selected {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: var(--primary);
        }

        /* Score Display */
        .score-display {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 20px;
            margin: 30px 0;
            animation: zoomIn 0.8s ease-out;
        }

        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .score-value {
            font-size: 4rem;
            font-weight: 700;
            margin: 20px 0;
            animation: bounceIn 1s ease-out;
        }

        @keyframes bounceIn {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 800px;
        }

        th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        tr:hover {
            background: rgba(74, 107, 255, 0.05);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            -webkit-appearance: none;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.1);
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 10px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab {
            padding: 10px 25px;
            background: transparent;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--gray);
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--primary);
            background: rgba(74, 107, 255, 0.1);
        }

        .tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease-out;
            padding: 15px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 10px;
            background: var(--light-gray);
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        /* Footer */
        footer {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            color: white;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Responsive Design - สำหรับ iPad/Tablet */
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 250px 1fr;
                gap: 20px;
            }
            
            .lesson-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Responsive Design - สำหรับมือถือแนวนอนและแท็บเล็ตขนาดเล็ก */
        @media (max-width: 900px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .logo {
                min-width: auto;
                justify-content: center;
            }
            
            nav {
                min-width: auto;
                width: 100%;
            }
            
            .search-box {
                max-width: 100%;
                min-width: auto;
            }
            
            .user-info {
                min-width: auto;
                justify-content: center;
                width: 100%;
            }
            
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .card {
                padding: 20px;
            }
        }

        /* Responsive Design - สำหรับมือถือแนวตั้ง */
        @media (max-width: 768px) {
            nav ul {
                gap: 5px;
            }
            
            nav ul li a {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            .logo-text h1 {
                font-size: 1.3rem;
            }
            
            .lesson-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-card .value {
                font-size: 2rem;
            }
            
            .btn {
                padding: 8px 20px;
                font-size: 0.9rem;
            }
            
            .tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .tab {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }

        /* Responsive Design - สำหรับมือถือขนาดเล็ก */
        @media (max-width: 480px) {
            .logo {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .logo-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .logo-text h1 {
                font-size: 1.1rem;
            }
            
            .user-info {
                flex-direction: column;
                align-items: center;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .card h2 {
                font-size: 1.3rem;
            }
            
            .video-container {
                border-radius: 10px;
            }
            
            .quiz-question {
                padding: 15px;
            }
            
            .score-display {
                padding: 20px;
            }
            
            .score-value {
                font-size: 3rem;
            }
            
            .modal-content {
                padding: 20px;
            }
        }

        /* สำหรับ iPad Pro และแท็บเล็ตขนาดใหญ่ในแนวตั้ง */
        @media (min-width: 769px) and (max-width: 1024px) and (orientation: portrait) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: block;
            }
            
            .lesson-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Custom Animations */
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .animate-pulse {
            animation: pulse 2s infinite;
        }

        /* ปรับปรุงสำหรับการแสดงผลบน iOS Safari */
        @supports (-webkit-touch-callout: none) {
            .btn, .quiz-option, .tab {
                cursor: pointer;
            }
            
            .search-box input, .form-control {
                font-size: 16px; /* ป้องกันการ zoom อัตโนมัติใน iOS */
            }
        }

        /* ปรับปรุงสำหรับอุปกรณ์ที่มี notch */
        @media (orientation: landscape) and (max-height: 500px) {
            header {
                padding: 10px 0;
            }
            
            .logo-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .main-content {
                margin-top: 20px;
            }
        }

        /* เพิ่ม Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
            padding: 10px;
        }

        @media (max-width: 900px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .sidebar.show-mobile {
                display: block;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1001;
                border-radius: 0;
                overflow-y: auto;
            }
            
            .sidebar .close-mobile-menu {
                display: block;
                position: absolute;
                top: 20px;
                right: 20px;
                background: none;
                border: none;
                font-size: 1.5rem;
                color: var(--dark);
                cursor: pointer;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <div class="header-content">
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="logo">
                    <img src="images/logo.png" width="80" height="80"/>
                    <div class="logo-text">
                        <h1>วิทยาลัยเทคนิคสุราษฏร์ธานี</h1>
                        <p>สื่อการเรียนรู้วิชาการสร้างเกมคอมพิวเตอร์</p>
                    </div>
                </div>
                
                <div class="search-box">
                    <form action="" method="GET">
                        <input type="hidden" name="page" value="lessons">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="ค้นหาบทเรียน..." value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>
                
                <nav>
                    <ul>
                        <li><a href="?page=home" class="<?php echo $page == 'home' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> หน้าหลัก
                        </a></li>
                        <li><a href="?page=lessons" class="<?php echo $page == 'lessons' ? 'active' : ''; ?>">
                            <i class="fas fa-book"></i> บทเรียน
                        </a></li>
                        
                        <?php if($is_logged_in): ?>
                            <li><a href="?page=progress" class="<?php echo $page == 'progress' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line"></i> ความคืบหน้า
                            </a></li>
                            <li><a href="?page=profile" class="<?php echo $page == 'profile' ? 'active' : ''; ?>">
                                <i class="fas fa-user"></i> โปรไฟล์
                            </a></li>
                            
                            <?php if(in_array($user_role, ['teacher', 'admin'])): ?>
                                <li><a href="?page=manage&section=dashboard" class="<?php echo $page == 'manage' ? 'active' : ''; ?>">
                                    <i class="fas fa-cogs"></i> จัดการระบบ
                                </a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="user-info">
                    <?php if($is_logged_in): ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                        </div>
                        <span style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                        <a href="?action=logout" class="btn btn-danger" onclick="return confirm('คุณแน่ใจว่าต้องการออกจากระบบ?')">
                            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                        </a>
                    <?php else: ?>
                        <a href="?page=login" class="btn btn-outline">
                            <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                        </a>
                        <a href="?page=register" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> ลงทะเบียน
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <!-- Alert Messages -->
        <?php if(isset($login_result) && $login_result !== true && $login_result !== null): ?>
            <div class="alert alert-error animate__animated animate__shakeX">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $login_result; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($register_result) && $register_result !== true && $register_result !== null): ?>
            <div class="alert alert-error animate__animated animate__shakeX">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $register_result; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($add_lesson_result) && $add_lesson_result === true): ?>
            <div class="alert alert-success animate__animated animate__slideInDown">
                <i class="fas fa-check-circle"></i> เพิ่มบทเรียนสำเร็จแล้ว!
            </div>
        <?php elseif(isset($add_lesson_result) && $add_lesson_result !== true && $add_lesson_result !== null): ?>
            <div class="alert alert-error animate__animated animate__shakeX">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $add_lesson_result; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($add_quiz_result) && $add_quiz_result === true): ?>
            <div class="alert alert-success animate__animated animate__slideInDown">
                <i class="fas fa-check-circle"></i> เพิ่มแบบทดสอบสำเร็จแล้ว!
            </div>
        <?php elseif(isset($add_quiz_result) && $add_quiz_result !== true && $add_quiz_result !== null): ?>
            <div class="alert alert-error animate__animated animate__shakeX">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $add_quiz_result; ?>
            </div>
        <?php endif; ?>
        
        <div class="main-content">
            <?php if(in_array($page, ['lessons', 'lesson_detail', 'progress'])): ?>
            <div class="sidebar" id="sidebar">
                <button class="close-mobile-menu" onclick="toggleMobileMenu()" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
                <h3><i class="fas fa-list"></i> หมวดหมู่</h3>
                <ul class="category-list">
                    <li><a href="?page=lessons" class="<?php echo !$category_id ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> ทั้งหมด
                    </a></li>
                    <?php
                    $categories = getCategories();
                    foreach($categories as $category): 
                    ?>
                        <li><a href="?page=lessons&category=<?php echo $category['id']; ?>" 
                               class="<?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($category['name']); ?>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if($is_logged_in): ?>
                <h3 style="margin-top: 20px;"><i class="fas fa-chart-pie"></i> สถิติของคุณ</h3>
                <?php
                $stats = getUserStats($_SESSION['user_id']);
                ?>
                <div style="margin-top: 15px;">
                    <div style="margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>บทเรียนที่เรียนแล้ว:</span>
                            <span><strong><?php echo $stats['lessons_completed']; ?>/<?php echo $stats['total_lessons']; ?></strong></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo $stats['total_lessons'] > 0 ? ($stats['lessons_completed'] / $stats['total_lessons'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>คะแนนเฉลี่ย:</span>
                            <span><strong><?php echo $stats['average_score']; ?>%</strong></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress" style="width: <?php echo $stats['average_score']; ?>%"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="content-area">
                <?php
                switch($page) {
                    case 'home':
                        includeHomePage();
                        break;
                    case 'login':
                        includeLoginPage();
                        break;
                    case 'register':
                        includeRegisterPage();
                        break;
                    case 'lessons':
                        includeLessonsPage();
                        break;
                    case 'lesson_detail':
                        includeLessonDetailPage();
                        break;
                    case 'progress':
                        includeProgressPage();
                        break;
                    case 'profile':
                        includeProfilePage();
                        break;
                    case 'manage':
                        includeManagePage();
                        break;
                    default:
                        includeHomePage();
                        break;
                }
                ?>
            </div>
        </div>
        
        <footer>
            <p>ระบบสื่อการสอนพัฒนาเกม &copy; <?php echo date('Y'); ?> - พัฒนาด้วย PHP และ MySQL</p>
        </footer>
    </div>

    <!-- Modal สำหรับยืนยันการลบ -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> ยืนยันการลบ</h3>
            <p>คุณแน่ใจว่าต้องการลบรายการนี้หรือไม่?</p>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="confirmDelete()" class="btn btn-danger">ลบ</button>
                <button onclick="closeModal()" class="btn btn-outline">ยกเลิก</button>
            </div>
        </div>
    </div>

    <script>
        // Select Answer
        function selectAnswer(questionId, option) {
            const quizOption = event.currentTarget;
            const inputs = document.querySelectorAll(`input[name="answer_${questionId}"]`);
            
            // Remove selected class from all options
            document.querySelectorAll(`[data-question="${questionId}"] .quiz-option`).forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            quizOption.classList.add('selected');
            
            // Check the corresponding radio button
            inputs.forEach(input => {
                if (input.value === option) {
                    input.checked = true;
                }
            });
        }

        // Video Progress Tracking
        function trackVideoProgress(videoId, lessonId) {
            const video = document.getElementById(videoId);
            let lastProgress = 0;
            
            video.addEventListener('timeupdate', function() {
                const progress = (video.currentTime / video.duration) * 100;
                
                // Update progress bar
                const progressBar = document.querySelector('.video-progress');
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                }
                
                // Send progress update every 10%
                if (Math.abs(progress - lastProgress) >= 10) {
                    updateVideoProgressToServer(lessonId, Math.round(progress));
                    lastProgress = progress;
                }
            });
            
            video.addEventListener('ended', function() {
                updateVideoProgressToServer(lessonId, 100);
            });
        }

        function updateVideoProgressToServer(lessonId, progress) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_video_progress&lesson_id=${lessonId}&progress=${progress}`
            });
        }

        // Modal Functions
        let deleteUrl = '';
        
        function openDeleteModal(url) {
            deleteUrl = url;
            document.getElementById('confirmModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }
        
        function confirmDelete() {
            if (deleteUrl) {
                window.location.href = deleteUrl;
            }
        }

        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show-mobile');
                document.body.style.overflow = sidebar.classList.contains('show-mobile') ? 'hidden' : 'auto';
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');
            
            if (sidebar && sidebar.classList.contains('show-mobile') && 
                !sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target)) {
                sidebar.classList.remove('show-mobile');
                document.body.style.overflow = 'auto';
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });

            // Initialize tooltips for touch devices
            if ('ontouchstart' in window) {
                document.querySelectorAll('[title]').forEach(el => {
                    el.addEventListener('touchstart', function() {
                        // Add touch feedback
                        this.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            this.style.transform = '';
                        }, 200);
                    });
                });
            }
            
            // Prevent zoom on input focus in iOS
            document.addEventListener('touchstart', function() {}, {passive: true});
        });
        
        // Handle orientation changes
        window.addEventListener('orientationchange', function() {
            // Reset mobile menu on orientation change
            const sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('show-mobile')) {
                sidebar.classList.remove('show-mobile');
                document.body.style.overflow = 'auto';
            }
        });
    </script>
</body>
</html>

<?php
// ============================================
// ฟังก์ชันสำหรับหน้าเพจต่างๆ
// ============================================

function includeHomePage() {
    global $is_logged_in, $user_role, $db;
    ?>
    <div class="card animate__animated animate__fadeIn">
        <h2><i class="fas fa-home"></i> ยินดีต้อนรับสู่ระบบการเรียนรู้พัฒนาเกม</h2>
        
        <?php if($is_logged_in): 
            $stats = getUserStats($_SESSION['user_id']);
            $progress = getUserProgress($_SESSION['user_id']);
        ?>
            <div class="stats-grid">
                <div class="stat-card" onclick="window.location='?page=lessons'">
                    <h3><i class="fas fa-book"></i> บทเรียนทั้งหมด</h3>
                    <div class="value"><?php echo $stats['total_lessons']; ?></div>
                </div>
                
                <div class="stat-card" onclick="window.location='?page=progress'">
                    <h3><i class="fas fa-check-circle"></i> บทเรียนที่เรียนแล้ว</h3>
                    <div class="value"><?php echo $stats['lessons_completed']; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-chart-line"></i> คะแนนเฉลี่ย</h3>
                    <div class="value"><?php echo $stats['average_score']; ?>%</div>
                </div>
                
                <div class="stat-card">
                    <h3><i class="fas fa-star"></i> คะแนนรวม</h3>
                    <div class="value"><?php echo $stats['total_score']; ?></div>
                </div>
            </div>
            
            <!-- บทเรียนล่าสุดที่เรียน -->
            <?php if(!empty($progress)): ?>
            <div style="margin-top: 30px;">
                <h3><i class="fas fa-history"></i> บทเรียนล่าสุดของคุณ</h3>
                <div class="lesson-grid" style="margin-top: 20px;">
                    <?php foreach(array_slice($progress, 0, 3) as $item): ?>
                        <div class="lesson-card">
                            <div class="lesson-card-header">
                                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                <span style="font-size: 0.8rem; opacity: 0.9;">
                                    <i class="fas fa-<?php echo $item['is_completed'] ? 'check-circle' : 'spinner'; ?>"></i>
                                    <?php echo $item['is_completed'] ? 'สำเร็จแล้ว' : 'กำลังเรียน'; ?>
                                </span>
                            </div>
                            <div class="lesson-card-body">
                                <div class="lesson-meta">
                                    <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($item['category_name']); ?></span>
                                    <span><i class="fas fa-chart-bar"></i> <?php echo $item['best_score'] ?? 0; ?>%</span>
                                </div>
                                <a href="?page=lesson_detail&id=<?php echo $item['id']; ?>" class="btn btn-primary" style="width: 100%; margin-top: 15px;">
                                    <i class="fas fa-<?php echo $item['is_completed'] ? 'redo' : 'play'; ?>"></i>
                                    <?php echo $item['is_completed'] ? 'เรียนอีกครั้ง' : 'เรียนต่อ'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- แนะนำบทเรียนใหม่ -->
            <?php
            $recent_lessons = getLessons(null, null, 3);
            if(!empty($recent_lessons)):
            ?>
            <div style="margin-top: 40px;">
                <h3><i class="fas fa-rocket"></i> บทเรียนใหม่แนะนำ</h3>
                <div class="lesson-grid" style="margin-top: 20px;">
                    <?php foreach($recent_lessons as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-card-header">
                                <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                <span style="font-size: 0.8rem; opacity: 0.9;">
                                    <i class="fas fa-clock"></i> <?php echo $lesson['duration_minutes']; ?> นาที
                                </span>
                            </div>
                            <div class="lesson-card-body">
                                <div class="lesson-meta">
                                    <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($lesson['category_name'] ?? 'ไม่มีหมวดหมู่'); ?></span>
                                    <span><i class="fas fa-signal"></i> <?php 
                                        $difficulty_text = [
                                            'beginner' => 'เริ่มต้น',
                                            'intermediate' => 'ปานกลาง',
                                            'advanced' => 'สูง'
                                        ];
                                        echo $difficulty_text[$lesson['difficulty']] ?? $lesson['difficulty'];
                                    ?></span>
                                </div>
                                <p style="color: #555; margin-bottom: 15px; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($lesson['description'] ?? 'ไม่มีคำอธิบาย', 0, 80)); ?>...
                                </p>
                                <a href="?page=lesson_detail&id=<?php echo $lesson['id']; ?>" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-play"></i> เริ่มเรียน
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="?page=lessons" class="btn btn-primary" style="padding: 12px 30px;">
                    <i class="fas fa-book-open"></i> ดูบทเรียนทั้งหมด
                </a>
            </div>
            
        <?php else: ?>
            <!-- สำหรับผู้ใช้ที่ยังไม่ได้ล็อกอิน -->
            <div style="text-align: center; padding: 60px 20px;">
                <div class="animate__animated animate__pulse" style="margin-bottom: 30px;">
                    <div style="font-size: 3rem; color: var(--primary); margin-bottom: 20px;">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h2 style="color: var(--dark); margin-bottom: 15px;">เริ่มต้นการพัฒนาเกมกับเรา</h2>
                    <p style="color: var(--gray); max-width: 600px; margin: 0 auto; line-height: 1.6;">
                        เรียนรู้การพัฒนาเกมจากพื้นฐานสู่ระดับมืออาชีพด้วยบทเรียนคุณภาพสูง
                        และแบบทดสอบที่ช่วยวัดความเข้าใจของคุณ
                    </p>
                </div>
                
                <div class="stats-grid" style="max-width: 800px; margin: 40px auto;">
                    <div class="stat-card">
                        <h3>บทเรียนคุณภาพ</h3>
                        <div class="value">3</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>แบบทดสอบ</h3>
                        <div class="value">3</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>นักเรียน</h3>
                        <div class="value">0</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>ความพึงพอใจ</h3>
                        <div class="value">0%</div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 20px; justify-content: center; margin-top: 40px; flex-wrap: wrap;">
                    <a href="?page=register" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-user-plus"></i> สมัครสมาชิกฟรี
                    </a>
                    <a href="?page=lessons" class="btn btn-outline" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-eye"></i> ดูตัวอย่างบทเรียน
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function includeLoginPage() {
    global $is_logged_in;
    
    if($is_logged_in) {
        header("Location: ?page=home");
        exit();
    }
    ?>
    <div class="card" style="max-width: 500px; margin: 0 auto; animation: slideInUp 0.8s ease-out;">
        <h2><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</h2>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       placeholder="กรอกชื่อผู้ใช้ของคุณ">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> รหัสผ่าน</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="form-control" required 
                           placeholder="กรอกรหัสผ่านของคุณ">
                    <button type="button" onclick="togglePassword()" 
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); 
                                   background: none; border: none; color: var(--gray); cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 10px;">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
            <p style="color: var(--gray); margin-bottom: 15px;">ยังไม่มีบัญชีผู้ใช้?</p>
            <a href="?page=register" class="btn btn-outline" style="width: 100%;">
                <i class="fas fa-user-plus"></i> สมัครสมาชิกใหม่
            </a>
        </div>
        
        <script>
            function togglePassword() {
                const passwordInput = document.getElementById('password');
                const eyeIcon = event.currentTarget.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                }
            }
            
            // เพิ่ม animation เมื่อ submit
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังเข้าสู่ระบบ...';
                button.disabled = true;
            });
        </script>
    </div>
    <?php
}

function includeRegisterPage() {
    global $is_logged_in;
    
    if($is_logged_in) {
        header("Location: ?page=home");
        exit();
    }
    ?>
    <div class="card" style="max-width: 500px; margin: 0 auto; animation: slideInUp 0.8s ease-out;">
        <h2><i class="fas fa-user-plus"></i> สมัครสมาชิกใหม่</h2>
        
        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="action" value="register">
            
            <div class="form-group">
                <label for="fullname"><i class="fas fa-id-card"></i> ชื่อ-สกุล</label>
                <input type="text" id="fullname" name="fullname" class="form-control" required 
                       placeholder="กรอกชื่อ-สกุลจริงของคุณ">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> อีเมล</label>
                <input type="email" id="email" name="email" class="form-control" 
                       placeholder="example@gmail.com">
            </div>
            
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       placeholder="กรอกชื่อผู้ใช้ (ภาษาอังกฤษ)">
                <small style="color: var(--gray);">ใช้ภาษาอังกฤษเท่านั้น</small>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="form-control" required 
                       placeholder="รหัสผ่านอย่างน้อย 6 ตัวอักษร">
                <small style="color: var(--gray);">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> ยืนยันรหัสผ่าน</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required 
                       placeholder="กรอกรหัสผ่านอีกครั้ง">
            </div>
            
            <div class="form-group" style="margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">
                    <i class="fas fa-user-plus"></i> สมัครสมาชิก
                </button>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
            <p style="color: var(--gray); margin-bottom: 15px;">มีบัญชีผู้ใช้แล้ว?</p>
            <a href="?page=login" class="btn btn-outline" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
            </a>
        </div>
        
        <script>
            // Validate password match
            document.getElementById('registerForm').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('รหัสผ่านไม่ตรงกัน กรุณากรอกใหม่');
                    return false;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                    return false;
                }
                
                const button = this.querySelector('button[type="submit"]');
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสมัครสมาชิก...';
                button.disabled = true;
            });
        </script>
    </div>
    <?php
}

function includeLessonsPage() {
    global $category_id, $search;
    
    $lessons = getLessons($category_id, $search);
    ?>
    
    <div class="card animate__animated animate__fadeIn">
        <h2><i class="fas fa-book"></i> บทเรียนทั้งหมด</h2>
        
        <?php if(!empty($search)): ?>
            <div class="alert alert-info animate__animated animate__slideInDown">
                <i class="fas fa-search"></i> ผลการค้นหา: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                <a href="?page=lessons" style="float: right; color: var(--primary);">ล้างการค้นหา</a>
            </div>
        <?php endif; ?>
        
        <?php if(empty($lessons)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3 style="color: var(--gray);"><?php echo !empty($search) ? 'ไม่พบบทเรียนที่ค้นหา' : 'ยังไม่มีบทเรียนในระบบ'; ?></h3>
                <?php if(!empty($search)): ?>
                    <a href="?page=lessons" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-book"></i> ดูบทเรียนทั้งหมด
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="lesson-grid">
                <?php foreach($lessons as $lesson): ?>
                    <div class="lesson-card animate__animated animate__fadeInUp">
                        <div class="lesson-card-header">
                            <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                            <?php if(!empty($lesson['video_url'])): ?>
                                <span style="font-size: 0.8rem; opacity: 0.9;">
                                    <i class="fas fa-video"></i> มีวิดีโอประกอบ
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="lesson-card-body">
                            <div class="lesson-meta">
                                <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($lesson['category_name'] ?? 'ไม่มีหมวดหมู่'); ?></span>
                                <span><i class="far fa-clock"></i> <?php echo $lesson['duration_minutes']; ?> นาที</span>
                            </div>
                            <p style="color: #555; margin-bottom: 15px; font-size: 0.9rem; line-height: 1.5;">
                                <?php echo htmlspecialchars(substr($lesson['description'] ?? 'ไม่มีคำอธิบาย', 0, 120)); ?>...
                            </p>
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <a href="?page=lesson_detail&id=<?php echo $lesson['id']; ?>" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-play"></i> เริ่มเรียน
                                </a>
                                <?php if(!empty($lesson['video_url'])): ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function includeLessonDetailPage() {
    global $lesson_id, $is_logged_in, $submit_quiz_result, $user_role, $db;
    
    if(!$lesson_id) {
        echo "<div class='card'><div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> ไม่พบบทเรียนที่ต้องการ</div></div>";
        return;
    }
    
    $lesson = getLesson($lesson_id);
    if(!$lesson) {
        echo "<div class='card'><div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> ไม่พบบทเรียนที่ต้องการ</div></div>";
        return;
    }
    
    $quizzes = getQuizzes($lesson_id);
    $can_take_quiz = true;
    ?>
    
    <div class="card animate__animated animate__fadeIn">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
            <h2 style="flex: 1; min-width: 300px;">
                <i class="fas fa-book-open"></i> <?php echo htmlspecialchars($lesson['title']); ?>
            </h2>
            
            <?php if(in_array($user_role, ['teacher', 'admin'])): ?>
                <div style="display: flex; gap: 10px;">
                    <a href="?page=manage&section=lessons&action=edit&id=<?php echo $lesson_id; ?>" class="btn btn-outline">
                        <i class="fas fa-edit"></i> แก้ไข
                    </a>
                    <button onclick="openDeleteModal('?page=manage&type=lesson&delete_id=<?php echo $lesson_id; ?>')" 
                            class="btn btn-danger">
                        <i class="fas fa-trash"></i> ลบ
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="background: linear-gradient(135deg, #f8f9ff, #eef1ff); padding: 20px; border-radius: 15px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 200px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-folder" style="color: var(--primary);"></i>
                        <span><strong>หมวดหมู่:</strong> <?php echo htmlspecialchars($lesson['category_name'] ?? 'ไม่มีหมวดหมู่'); ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="far fa-clock" style="color: var(--primary);"></i>
                        <span><strong>ระยะเวลา:</strong> <?php echo $lesson['duration_minutes']; ?> นาที</span>
                    </div>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <i class="fas fa-signal" style="color: var(--primary);"></i>
                        <span><strong>ระดับความยาก:</strong> 
                            <?php 
                            $difficulty_text = [
                                'beginner' => 'เริ่มต้น',
                                'intermediate' => 'ปานกลาง',
                                'advanced' => 'สูง'
                            ];
                            echo $difficulty_text[$lesson['difficulty']] ?? $lesson['difficulty'];
                            ?>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user" style="color: var(--primary);"></i>
                        <span><strong>ผู้สร้าง:</strong> <?php echo htmlspecialchars($lesson['creator_name'] ?? 'ไม่ระบุ'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if(!empty($lesson['description'])): ?>
            <div style="margin-bottom: 25px;">
                <h3><i class="fas fa-info-circle"></i> คำอธิบายบทเรียน</h3>
                <p style="color: #555; line-height: 1.6; margin-top: 10px;">
                    <?php echo nl2br(htmlspecialchars($lesson['description'])); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($lesson['video_url'])): ?>
            <div style="margin-bottom: 25px;">
                <h3><i class="fas fa-video"></i> วิดีโอการสอน</h3>
                
                <div class="video-container">
                    <?php 
                    $video_url = $lesson['video_url'];
                    if(strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                        $video_id = '';
                        if(preg_match('/youtube\.com\/watch\?v=([^&]+)/', $video_url, $matches)) {
                            $video_id = $matches[1];
                        } elseif(preg_match('/youtu\.be\/([^&]+)/', $video_url, $matches)) {
                            $video_id = $matches[1];
                        }
                        if($video_id) {
                            echo '<iframe id="lessonVideo" src="https://www.youtube.com/embed/'.$video_id.'?enablejsapi=1" 
                                    allowfullscreen frameborder="0"></iframe>';
                        } else {
                            echo '<p style="color: white; text-align: center; padding: 40px;">ไม่สามารถเล่นวิดีโอได้</p>';
                        }
                    } else {
                        echo '<video id="lessonVideo" src="'.$video_url.'" controls style="width:100%;height:100%;"></video>';
                    }
                    ?>
                    <div class="video-progress-bar">
                        <div class="video-progress"></div>
                    </div>
                </div>
                
                <script>
                    // Track video progress
                    <?php if($is_logged_in): ?>
                    document.addEventListener('DOMContentLoaded', function() {
                        const video = document.getElementById('lessonVideo');
                        if (video) {
                            trackVideoProgress('lessonVideo', <?php echo $lesson_id; ?>);
                        }
                    });
                    <?php endif; ?>
                </script>
                
                <?php if(!$is_logged_in): ?>
                    <div class="alert alert-info" style="margin-top: 15px;">
                        <i class="fas fa-info-circle"></i> กรุณาเข้าสู่ระบบเพื่อบันทึกความคืบหน้าและทำแบบทดสอบ
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 25px;">
            <h3><i class="fas fa-file-alt"></i> เนื้อหา</h3>
            <div style="background: #f8f9fa; padding: 25px; border-radius: 15px; margin-top: 15px; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
            </div>
        </div>
        
        <?php if(!empty($quizzes)): ?>
            <div id="quiz-section" style="margin-top: 30px;">
                <h3><i class="fas fa-question-circle"></i> แบบทดสอบหลังเรียน</h3>
                
                <?php if($submit_quiz_result && is_array($submit_quiz_result)): ?>
                    <div class="score-display animate__animated animate__zoomIn">
                        <div style="font-size: 1.5rem; margin-bottom: 10px;">
                            <i class="fas fa-trophy"></i> ผลการทดสอบ
                        </div>
                        <div class="score-value"><?php echo $submit_quiz_result['percentage']; ?>%</div>
                        <div style="font-size: 1.2rem; margin-bottom: 30px;">
                            คุณตอบถูก <?php echo $submit_quiz_result['correct_answers']; ?> จาก <?php echo $submit_quiz_result['total_questions']; ?> ข้อ
                        </div>
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <a href="?page=lesson_detail&id=<?php echo $lesson_id; ?>" class="btn btn-outline">
                                <i class="fas fa-redo"></i> ทำอีกครั้ง
                            </a>
                            <a href="?page=lessons" class="btn btn-primary">
                                <i class="fas fa-book"></i> เรียนบทเรียนอื่น
                            </a>
                        </div>
                    </div>
                    
                    <!-- แสดงคำตอบที่ถูกต้อง -->
                    <div style="margin-top: 30px;">
                        <h4><i class="fas fa-lightbulb"></i> เฉลยคำตอบ</h4>
                        <?php
                        foreach($quizzes as $index => $quiz):
                            // ดึงคำตอบของผู้ใช้
                            $user_answer = '';
                            $is_correct_user = false;
                            if($is_logged_in) {
                                $stmt = $db->prepare("SELECT user_answer, is_correct FROM quiz_results 
                                                      WHERE user_id = ? AND quiz_id = ? AND lesson_id = ? 
                                                      ORDER BY completed_at DESC LIMIT 1");
                                $stmt->execute([$_SESSION['user_id'], $quiz['id'], $lesson_id]);
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if($result) {
                                    $user_answer = $result['user_answer'];
                                    $is_correct_user = $result['is_correct'];
                                }
                            }
                        ?>
                            <div class="quiz-question" style="margin-top: 15px;">
                                <p style="font-weight: 600; margin-bottom: 15px;">
                                    <?php echo ($index + 1).'. '.htmlspecialchars($quiz['question']); ?>
                                </p>
                                
                                <div class="quiz-options">
                                    <?php 
                                    $options = [
                                        'a' => $quiz['option_a'],
                                        'b' => $quiz['option_b'],
                                        'c' => $quiz['option_c'],
                                        'd' => $quiz['option_d'],
                                        'e' => $quiz['option_e']
                                    ];
                                    
                                    foreach($options as $key => $value):
                                        if(!empty($value)):
                                            $is_correct = ($quiz['correct_answer'] == $key);
                                            $is_user_answer = ($user_answer == $key);
                                    ?>
                                        <div class="quiz-option <?php echo $is_correct ? 'selected' : ''; ?>" 
                                             style="<?php echo $is_correct ? 'background: linear-gradient(135deg, #4caf50, #2ecc71); color: white;' : ''; 
                                                          echo $is_user_answer && !$is_correct ? 'border: 2px solid var(--danger);' : ''; ?>">
                                            <strong><?php echo strtoupper($key); ?>.</strong> 
                                            <?php echo htmlspecialchars($value); ?>
                                            
                                            <?php if($is_correct): ?>
                                                <span style="margin-left: auto;">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                            <?php elseif($is_user_answer && !$is_correct): ?>
                                                <span style="margin-left: auto; color: var(--danger);">
                                                    <i class="fas fa-times-circle"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                                
                                <?php if(!empty($quiz['explanation'])): ?>
                                    <div style="margin-top: 15px; padding: 10px; background: rgba(74, 107, 255, 0.1); border-radius: 8px;">
                                        <strong><i class="fas fa-info-circle"></i> คำอธิบาย:</strong>
                                        <?php echo htmlspecialchars($quiz['explanation']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php if($is_logged_in): ?>
                        <div class="alert alert-success animate__animated animate__slideInUp" style="margin-bottom: 25px;">
                            <i class="fas fa-check-circle"></i> คุณสามารถทำแบบทดสอบได้ทันที
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info animate__animated animate__slideInUp" style="margin-bottom: 25px;">
                            <i class="fas fa-info-circle"></i> กรุณาเข้าสู่ระบบเพื่อทำแบบทดสอบ
                        </div>
                    <?php endif; ?>
                    
                    <?php if($is_logged_in): ?>
                        <form method="POST" action="" id="quizForm">
                            <input type="hidden" name="action" value="submit_quiz">
                            <input type="hidden" name="lesson_id" value="<?php echo $lesson_id; ?>">
                            
                            <?php foreach($quizzes as $index => $quiz): ?>
                                <div class="quiz-question" data-question="<?php echo $quiz['id']; ?>">
                                    <p style="font-weight: 600; margin-bottom: 15px;">
                                        <?php echo ($index + 1).'. '.htmlspecialchars($quiz['question']); ?>
                                        <?php if($quiz['points'] > 1): ?>
                                            <span style="float: right; color: var(--primary); font-size: 0.9rem;">
                                                <?php echo $quiz['points']; ?> คะแนน
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="quiz-options">
                                        <?php if(!empty($quiz['option_a'])): ?>
                                        <div class="quiz-option" onclick="selectAnswer(<?php echo $quiz['id']; ?>, 'a')">
                                            <input type="radio" name="answer_<?php echo $quiz['id']; ?>" value="a" 
                                                   style="display: none;" <?php echo $quiz['question_type'] == 'single_choice' ? 'required' : ''; ?>>
                                            <strong>ก.</strong> <?php echo htmlspecialchars($quiz['option_a']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($quiz['option_b'])): ?>
                                        <div class="quiz-option" onclick="selectAnswer(<?php echo $quiz['id']; ?>, 'b')">
                                            <input type="radio" name="answer_<?php echo $quiz['id']; ?>" value="b" 
                                                   style="display: none;">
                                            <strong>ข.</strong> <?php echo htmlspecialchars($quiz['option_b']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($quiz['option_c'])): ?>
                                        <div class="quiz-option" onclick="selectAnswer(<?php echo $quiz['id']; ?>, 'c')">
                                            <input type="radio" name="answer_<?php echo $quiz['id']; ?>" value="c" 
                                                   style="display: none;">
                                            <strong>ค.</strong> <?php echo htmlspecialchars($quiz['option_c']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($quiz['option_d'])): ?>
                                        <div class="quiz-option" onclick="selectAnswer(<?php echo $quiz['id']; ?>, 'd')">
                                            <input type="radio" name="answer_<?php echo $quiz['id']; ?>" value="d" 
                                                   style="display: none;">
                                            <strong>ง.</strong> <?php echo htmlspecialchars($quiz['option_d']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($quiz['option_e'])): ?>
                                        <div class="quiz-option" onclick="selectAnswer(<?php echo $quiz['id']; ?>, 'e')">
                                            <input type="radio" name="answer_<?php echo $quiz['id']; ?>" value="e" 
                                                   style="display: none;">
                                            <strong>จ.</strong> <?php echo htmlspecialchars($quiz['option_e']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 1.1rem;">
                                    <i class="fas fa-paper-plane"></i> ส่งคำตอบทั้งหมด
                                </button>
                            </div>
                        </form>
                        
                        <script>
                            // Form validation
                            document.getElementById('quizForm').addEventListener('submit', function(e) {
                                const questions = <?php echo count($quizzes); ?>;
                                let answered = 0;
                                
                                const inputs = document.querySelectorAll(`input[name^="answer_"]`);
                                inputs.forEach(input => {
                                    if(input.checked) answered++;
                                });
                                
                                if(answered < questions) {
                                    e.preventDefault();
                                    alert('กรุณาตอบคำถามให้ครบทุกข้อก่อนส่ง');
                                    return false;
                                }
                                
                                const button = this.querySelector('button[type="submit"]');
                                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังตรวจคำตอบ...';
                                button.disabled = true;
                            });
                        </script>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px;">
                            <div style="font-size: 3rem; color: var(--primary); margin-bottom: 15px;">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <h3>กรุณาเข้าสู่ระบบเพื่อทำแบบทดสอบ</h3>
                            <div style="margin-top: 20px;">
                                <a href="?page=login" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function includeProgressPage() {
    global $is_logged_in, $db;
    
    if(!$is_logged_in) {
        echo '<div class="card"><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> กรุณาเข้าสู่ระบบเพื่อดูความคืบหน้า</div></div>';
        return;
    }
    
    $progress = getUserProgress($_SESSION['user_id']);
    $stats = getUserStats($_SESSION['user_id']);
    ?>
    
    <div class="card animate__animated animate__fadeIn">
        <h2><i class="fas fa-chart-line"></i> ความคืบหน้าของฉัน</h2>
        
        <div class="stats-grid" style="margin-bottom: 30px;">
            <div class="stat-card" onclick="window.location='?page=lessons'">
                <h3><i class="fas fa-chart-bar"></i> คะแนนเฉลี่ย</h3>
                <div class="value"><?php echo $stats['average_score']; ?>%</div>
            </div>
            
            <div class="stat-card" onclick="window.location='?page=lessons'">
                <h3><i class="fas fa-check-circle"></i> บทเรียนสำเร็จ</h3>
                <div class="value"><?php echo $stats['lessons_completed']; ?></div>
            </div>
            
            <div class="stat-card" onclick="window.location='?page=lessons'">
                <h3><i class="fas fa-question-circle"></i> คำถามทั้งหมด</h3>
                <div class="value"><?php echo $stats['total_attempts']; ?></div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-star"></i> คะแนนรวม</h3>
                <div class="value"><?php echo $stats['total_score']; ?></div>
            </div>
        </div>
        
        <h3><i class="fas fa-history"></i> ประวัติการเรียนรู้</h3>
        
        <?php if(empty($progress)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3 style="color: var(--gray);">ยังไม่มีประวัติการเรียนรู้</h3>
                <p style="color: var(--gray); margin: 15px 0;">เริ่มต้นการเรียนรู้กับบทเรียนแรกของคุณ</p>
                <a href="?page=lessons" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-play"></i> เริ่มเรียนตอนนี้
                </a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>บทเรียน</th>
                            <th>หมวดหมู่</th>
                            <th>ระดับความยาก</th>
                            <th>สถานะ</th>
                            <th>คะแนนสูงสุด</th>
                            <th>จำนวนครั้งที่เรียน</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($progress as $item): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <?php 
                                    $difficulty_text = [
                                        'beginner' => '<span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">เริ่มต้น</span>',
                                        'intermediate' => '<span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">ปานกลาง</span>',
                                        'advanced' => '<span style="background: #f44336; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">สูง</span>'
                                    ];
                                    echo $difficulty_text[$item['difficulty']] ?? $item['difficulty'];
                                    ?>
                                </td>
                                <td>
                                    <?php if($item['is_completed']): ?>
                                        <span style="color: var(--success);">
                                            <i class="fas fa-check-circle"></i> สำเร็จแล้ว
                                        </span>
                                    <?php elseif($item['percentage'] > 0): ?>
                                        <span style="color: var(--warning);">
                                            <i class="fas fa-spinner fa-spin"></i> กำลังเรียน (<?php echo $item['percentage']; ?>%)
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--gray);">
                                            <i class="fas fa-clock"></i> ยังไม่ได้เริ่ม
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-weight: 600; color: var(--primary);">
                                            <?php echo $item['best_score'] ?? 0; ?>%
                                        </span>
                                        <div class="progress-bar" style="flex: 1; max-width: 100px;">
                                            <div class="progress" style="width: <?php echo $item['best_score'] ?? 0; ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $item['attempt_count'] ?? 0; ?> ครั้ง</td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="?page=lesson_detail&id=<?php echo $item['id']; ?>" class="btn btn-outline" style="padding: 5px 10px;">
                                            <i class="fas fa-play"></i>
                                        </a>
                                        <?php if($item['is_completed']): ?>
                                            <span class="btn btn-success" style="padding: 5px 10px; cursor: default;">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="?page=lessons" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> เรียนบทเรียนเพิ่มเติม
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function includeProfilePage() {
    global $is_logged_in, $db;
    
    if(!$is_logged_in) {
        echo '<div class="card"><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> กรุณาเข้าสู่ระบบเพื่อดูโปรไฟล์</div></div>';
        return;
    }
    
    $stats = getUserStats($_SESSION['user_id']);
    $progress = getUserProgress($_SESSION['user_id']);
    $completed_lessons = array_filter($progress, function($item) {
        return $item['is_completed'];
    });
    ?>
    
    <div class="card animate__animated animate__fadeIn">
        <h2><i class="fas fa-user"></i> โปรไฟล์ของฉัน</h2>
        
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px; margin-top: 20px;">
            <div>
                <h3><i class="fas fa-id-card"></i> ข้อมูลส่วนตัว</h3>
                <div style="background: linear-gradient(135deg, #f8f9ff, #eef1ff); padding: 25px; border-radius: 15px; margin-top: 15px;">
                    <div style="text-align: center; margin-bottom: 25px;">
                        <div class="user-avatar" style="width: 100px; height: 100px; font-size: 2.5rem; margin: 0 auto 15px;">
                            <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                        </div>
                        <h3 style="color: var(--dark); margin: 0;"><?php echo htmlspecialchars($_SESSION['fullname']); ?></h3>
                        <p style="color: var(--gray); margin: 5px 0 0;">
                            <?php 
                            $role_text = [
                                'student' => 'นักเรียน',
                                'teacher' => 'ครูผู้สอน',
                                'admin' => 'ผู้ดูแลระบบ'
                            ];
                            echo $role_text[$_SESSION['user_role']] ?? $_SESSION['user_role'];
                            ?>
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <i class="fas fa-user" style="color: var(--primary); width: 20px;"></i>
                            <div style="flex: 1;">
                                <div style="font-size: 0.9rem; color: var(--gray);">ชื่อผู้ใช้</div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <i class="fas fa-envelope" style="color: var(--primary); width: 20px;"></i>
                            <div style="flex: 1;">
                                <div style="font-size: 0.9rem; color: var(--gray);">อีเมล</div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'ไม่ได้ระบุ'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 25px;">
                        <button class="btn btn-outline" style="width: 100%;" onclick="alert('ฟังก์ชันกำลังพัฒนา...')">
                            <i class="fas fa-edit"></i> แก้ไขโปรไฟล์
                        </button>
                    </div>
                </div>
            </div>
            
            <div>
                <h3><i class="fas fa-chart-pie"></i> สถิติการเรียนรู้</h3>
                <div class="stats-grid" style="margin-top: 15px;">
                    <div class="stat-card">
                        <h3><i class="fas fa-book"></i> บทเรียนทั้งหมด</h3>
                        <div class="value"><?php echo $stats['total_lessons']; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-check-circle"></i> บทเรียนสำเร็จ</h3>
                        <div class="value"><?php echo count($completed_lessons); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-chart-line"></i> คะแนนเฉลี่ย</h3>
                        <div class="value"><?php echo $stats['average_score']; ?>%</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-star"></i> คำถามตอบถูก</h3>
                        <div class="value"><?php echo $stats['correct_attempts']; ?></div>
                    </div>
                </div>
                
                <!-- Progress Charts -->
                <div style="margin-top: 30px;">
                    <h4><i class="fas fa-trophy"></i> ความสำเร็จของคุณ</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 15px;">
                        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>ความคืบหน้าเรียน</span>
                                <span style="font-weight: 600;"><?php echo $stats['total_lessons'] > 0 ? round(($stats['lessons_completed'] / $stats['total_lessons']) * 100) : 0; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $stats['total_lessons'] > 0 ? ($stats['lessons_completed'] / $stats['total_lessons'] * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                        
                        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>อัตราความถูกต้อง</span>
                                <span style="font-weight: 600;"><?php echo $stats['total_attempts'] > 0 ? round(($stats['correct_attempts'] / $stats['total_attempts']) * 100) : 0; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $stats['total_attempts'] > 0 ? ($stats['correct_attempts'] / $stats['total_attempts'] * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recently Completed Lessons -->
                <?php if(!empty($completed_lessons)): ?>
                <div style="margin-top: 30px;">
                    <h4><i class="fas fa-history"></i> บทเรียนที่สำเร็จล่าสุด</h4>
                    <div style="margin-top: 15px;">
                        <?php foreach(array_slice($completed_lessons, 0, 5) as $item): ?>
                            <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 10px; 
                                      border-left: 4px solid var(--success); display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div style="font-size: 0.9rem; color: var(--gray);">
                                        <?php echo htmlspecialchars($item['category_name']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600; color: var(--success);"><?php echo $item['best_score']; ?>%</div>
                                    <div style="font-size: 0.8rem; color: var(--gray);">
                                        <?php echo $item['attempt_count']; ?> ครั้ง
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-gray);">
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="?page=lessons" class="btn btn-primary">
                    <i class="fas fa-book"></i> ศึกษาบทเรียนเพิ่มเติม
                </a>
                <a href="?page=progress" class="btn btn-outline">
                    <i class="fas fa-chart-line"></i> ดูสถิติทั้งหมด
                </a>
                <button class="btn btn-outline" onclick="alert('ฟังก์ชันกำลังพัฒนา...')">
                    <i class="fas fa-download"></i> ดาวน์โหลดใบรับรอง
                </button>
            </div>
        </div>
    </div>
    <?php
}

function includeManagePage() {
    global $is_logged_in, $user_role, $db;
    
    if(!$is_logged_in || !in_array($user_role, ['teacher', 'admin'])) {
        echo '<div class="card"><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div></div>';
        return;
    }
    
    $section = $_GET['section'] ?? 'dashboard';
    $action = $_GET['action'] ?? '';
    $categories = getCategories();
    $lessons = getLessonsForManagement();
    $quizzes = getQuizzesForManagement();
    
    // Statistics for dashboard
    $total_lessons = count($lessons);
    $total_quizzes = count($quizzes);
    
    // Get total students
    $stmt = $db->query("SELECT COUNT(*) as total_students FROM users WHERE role = 'student'");
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];
    
    // Get total teachers
    $stmt = $db->query("SELECT COUNT(*) as total_teachers FROM users WHERE role = 'teacher'");
    $total_teachers = $stmt->fetch(PDO::FETCH_ASSOC)['total_teachers'];
    ?>
    
    <div class="card animate__animated animate__fadeIn">
        <h2><i class="fas fa-cogs"></i> จัดการระบบ</h2>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab <?php echo $section == 'dashboard' ? 'active' : ''; ?>" 
                    onclick="window.location='?page=manage&section=dashboard'">
                <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
            </button>
            <button class="tab <?php echo $section == 'lessons' ? 'active' : ''; ?>" 
                    onclick="window.location='?page=manage&section=lessons'">
                <i class="fas fa-book"></i> จัดการบทเรียน
            </button>
            <button class="tab <?php echo $section == 'quizzes' ? 'active' : ''; ?>" 
                    onclick="window.location='?page=manage&section=quizzes'">
                <i class="fas fa-question-circle"></i> จัดการแบบทดสอบ
            </button>
            <button class="tab <?php echo $section == 'users' ? 'active' : ''; ?>" 
                    onclick="window.location='?page=manage&section=users'">
                <i class="fas fa-users"></i> จัดการผู้ใช้
            </button>
            <button class="tab <?php echo $section == 'categories' ? 'active' : ''; ?>" 
                    onclick="window.location='?page=manage&section=categories'">
                <i class="fas fa-folder"></i> จัดการหมวดหมู่
            </button>
        </div>
        
        <!-- Dashboard -->
        <?php if($section == 'dashboard'): ?>
            <div style="text-align: center; padding: 20px 0;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 30px;">
                    <div class="user-avatar" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                    </div>
                    <div style="text-align: left;">
                        <h3 style="margin: 0;"><?php echo htmlspecialchars($_SESSION['fullname']); ?></h3>
                        <p style="color: var(--gray); margin: 5px 0 0;">
                            <?php echo $user_role == 'admin' ? 'ผู้ดูแลระบบ' : 'ครูผู้สอน'; ?>
                        </p>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card" onclick="window.location='?page=manage&section=lessons'">
                        <h3><i class="fas fa-book"></i> บทเรียนทั้งหมด</h3>
                        <div class="value"><?php echo $total_lessons; ?></div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location='?page=manage&section=quizzes'">
                        <h3><i class="fas fa-question-circle"></i> แบบทดสอบทั้งหมด</h3>
                        <div class="value"><?php echo $total_quizzes; ?></div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location='?page=manage&section=users'">
                        <h3><i class="fas fa-user-graduate"></i> นักเรียนทั้งหมด</h3>
                        <div class="value"><?php echo $total_students; ?></div>
                    </div>
                    
                    <div class="stat-card" onclick="window.location='?page=manage&section=users'">
                        <h3><i class="fas fa-chalkboard-teacher"></i> ครูผู้สอน</h3>
                        <div class="value"><?php echo $total_teachers; ?></div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div style="margin-top: 40px;">
                    <h3><i class="fas fa-bolt"></i> ดำเนินการด่วน</h3>
                    <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
                        <a href="?page=manage&section=lessons&action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> เพิ่มบทเรียนใหม่
                        </a>
                        <a href="?page=manage&section=quizzes&action=add" class="btn btn-success">
                            <i class="fas fa-plus"></i> เพิ่มแบบทดสอบ
                        </a>
                        <a href="?page=manage&section=users" class="btn btn-outline">
                            <i class="fas fa-user-plus"></i> จัดการผู้ใช้
                        </a>
                        <a href="?page=manage&section=categories" class="btn btn-outline">
                            <i class="fas fa-folder-plus"></i> จัดการหมวดหมู่
                        </a>
                    </div>
                </div>
            </div>
            
        <!-- Manage Lessons -->
        <?php elseif($section == 'lessons'): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3><i class="fas fa-book"></i> จัดการบทเรียน</h3>
                <a href="?page=manage&section=lessons&action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> เพิ่มบทเรียนใหม่
                </a>
            </div>
            
            <?php if($action == 'add'): ?>
                <!-- Add Lesson Form -->
                <div style="background: #f8f9fa; padding: 25px; border-radius: 15px;">
                    <h4><i class="fas fa-plus-circle"></i> เพิ่มบทเรียนใหม่</h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_lesson">
                        
                        <div class="form-group">
                            <label for="title">หัวข้อบทเรียน *</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">หมวดหมู่</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <?php foreach($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">คำอธิบาย</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">เนื้อหา *</label>
                            <textarea id="content" name="content" class="form-control" rows="10" required></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="video_url">URL วิดีโอ (YouTube)</label>
                                <input type="text" id="video_url" name="video_url" class="form-control" 
                                       placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">ระยะเวลา (นาที)</label>
                                <input type="number" id="duration" name="duration" class="form-control" min="0" value="30">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="difficulty">ระดับความยาก</label>
                                <select id="difficulty" name="difficulty" class="form-control">
                                    <option value="beginner">เริ่มต้น</option>
                                    <option value="intermediate">ปานกลาง</option>
                                    <option value="advanced">สูง</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="is_published">สถานะ</label>
                                <select id="is_published" name="is_published" class="form-control">
                                    <option value="1">เผยแพร่</option>
                                    <option value="0">แบบร่าง</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกบทเรียน
                            </button>
                            <a href="?page=manage&section=lessons" class="btn btn-outline">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php elseif($action == 'edit' && isset($_GET['id'])): ?>
                <!-- Edit Lesson Form -->
                <?php
                $edit_lesson_id = $_GET['id'];
                $stmt = $db->prepare("SELECT * FROM lessons WHERE id = ?");
                $stmt->execute([$edit_lesson_id]);
                $edit_lesson = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($edit_lesson):
                ?>
                <div style="background: #f8f9fa; padding: 25px; border-radius: 15px;">
                    <h4><i class="fas fa-edit"></i> แก้ไขบทเรียน</h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_lesson">
                        <input type="hidden" name="lesson_id" value="<?php echo $edit_lesson_id; ?>">
                        
                        <div class="form-group">
                            <label for="title">หัวข้อบทเรียน *</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_lesson['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">หมวดหมู่</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <?php foreach($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $edit_lesson['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">คำอธิบาย</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_lesson['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="content">เนื้อหา *</label>
                            <textarea id="content" name="content" class="form-control" rows="10" required><?php echo htmlspecialchars($edit_lesson['content']); ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="video_url">URL วิดีโอ (YouTube)</label>
                                <input type="text" id="video_url" name="video_url" class="form-control" 
                                       value="<?php echo htmlspecialchars($edit_lesson['video_url']); ?>"
                                       placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">ระยะเวลา (นาที)</label>
                                <input type="number" id="duration" name="duration" class="form-control" 
                                       min="0" value="<?php echo $edit_lesson['duration_minutes']; ?>">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="difficulty">ระดับความยาก</label>
                                <select id="difficulty" name="difficulty" class="form-control">
                                    <option value="beginner" <?php echo $edit_lesson['difficulty'] == 'beginner' ? 'selected' : ''; ?>>เริ่มต้น</option>
                                    <option value="intermediate" <?php echo $edit_lesson['difficulty'] == 'intermediate' ? 'selected' : ''; ?>>ปานกลาง</option>
                                    <option value="advanced" <?php echo $edit_lesson['difficulty'] == 'advanced' ? 'selected' : ''; ?>>สูง</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="is_published">สถานะ</label>
                                <select id="is_published" name="is_published" class="form-control">
                                    <option value="1" <?php echo $edit_lesson['is_published'] ? 'selected' : ''; ?>>เผยแพร่</option>
                                    <option value="0" <?php echo !$edit_lesson['is_published'] ? 'selected' : ''; ?>>แบบร่าง</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกการแก้ไข
                            </button>
                            <a href="?page=manage&section=lessons" class="btn btn-outline">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Lessons List -->
                <?php if(empty($lessons)): ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 style="color: var(--gray);">ยังไม่มีบทเรียนในระบบ</h3>
                        <a href="?page=manage&section=lessons&action=add" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> เพิ่มบทเรียนแรก
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>หัวข้อ</th>
                                    <th>หมวดหมู่</th>
                                    <th>สถานะ</th>
                                    <th>ระดับความยาก</th>
                                    <th>สร้างเมื่อ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($lessons as $lesson): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($lesson['title']); ?></td>
                                        <td><?php echo htmlspecialchars($lesson['category_name'] ?? 'ไม่มี'); ?></td>
                                        <td>
                                            <?php if($lesson['is_published']): ?>
                                                <span style="background: var(--success); color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8rem;">
                                                    <i class="fas fa-check"></i> เผยแพร่
                                                </span>
                                            <?php else: ?>
                                                <span style="background: var(--warning); color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8rem;">
                                                    <i class="fas fa-pencil-alt"></i> แบบร่าง
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $difficulty_text = [
                                                'beginner' => '<span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">เริ่มต้น</span>',
                                                'intermediate' => '<span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">ปานกลาง</span>',
                                                'advanced' => '<span style="background: #f44336; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">สูง</span>'
                                            ];
                                            echo $difficulty_text[$lesson['difficulty']] ?? $lesson['difficulty'];
                                            ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($lesson['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <a href="?page=lesson_detail&id=<?php echo $lesson['id']; ?>" class="btn btn-outline" style="padding: 5px 10px;">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?page=manage&section=lessons&action=edit&id=<?php echo $lesson['id']; ?>" class="btn btn-outline" style="padding: 5px 10px;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="openDeleteModal('?page=manage&type=lesson&delete_id=<?php echo $lesson['id']; ?>')" 
                                                        class="btn btn-danger" style="padding: 5px 10px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
        <!-- Manage Quizzes -->
        <?php elseif($section == 'quizzes'): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3><i class="fas fa-question-circle"></i> จัดการแบบทดสอบ</h3>
                <a href="?page=manage&section=quizzes&action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> เพิ่มแบบทดสอบใหม่
                </a>
            </div>
            
            <?php if($action == 'add'): ?>
                <!-- Add Quiz Form -->
                <div style="background: #f8f9fa; padding: 25px; border-radius: 15px;">
                    <h4><i class="fas fa-plus-circle"></i> เพิ่มแบบทดสอบใหม่</h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_quiz">
                        
                        <div class="form-group">
                            <label for="lesson_id">เลือกบทเรียน *</label>
                            <select id="lesson_id" name="lesson_id" class="form-control" required>
                                <option value="">-- เลือกบทเรียน --</option>
                                <?php foreach($lessons as $lesson): ?>
                                    <option value="<?php echo $lesson['id']; ?>"><?php echo htmlspecialchars($lesson['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="question">คำถาม *</label>
                            <textarea id="question" name="question" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="question_type">ประเภทคำถาม</label>
                                <select id="question_type" name="question_type" class="form-control">
                                    <option value="single_choice">ตัวเลือกเดียว</option>
                                    <option value="multiple_choice">หลายตัวเลือก</option>
                                    <option value="true_false">ถูก/ผิด</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="points">คะแนน</label>
                                <input type="number" id="points" name="points" class="form-control" min="1" value="1">
                            </div>
                        </div>
                        
                        <h5><i class="fas fa-list"></i> ตัวเลือกคำตอบ</h5>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="option_a">ตัวเลือก A</label>
                                <input type="text" id="option_a" name="option_a" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="option_b">ตัวเลือก B</label>
                                <input type="text" id="option_b" name="option_b" class="form-control">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="option_c">ตัวเลือก C</label>
                                <input type="text" id="option_c" name="option_c" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="option_d">ตัวเลือก D</label>
                                <input type="text" id="option_d" name="option_d" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="option_e">ตัวเลือก E (ถ้ามี)</label>
                            <input type="text" id="option_e" name="option_e" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="correct_answer">คำตอบที่ถูกต้อง *</label>
                            <input type="text" id="correct_answer" name="correct_answer" class="form-control" required
                                   placeholder="ตัวอย่าง: a สำหรับตัวเลือก A หรือ a,b สำหรับหลายตัวเลือก">
                        </div>
                        
                        <div class="form-group">
                            <label for="explanation">คำอธิบายคำตอบ</label>
                            <textarea id="explanation" name="explanation" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 25px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> บันทึกแบบทดสอบ
                            </button>
                            <a href="?page=manage&section=quizzes" class="btn btn-outline">
                                <i class="fas fa-times"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <!-- Quizzes List -->
                <?php if(empty($quizzes)): ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h3 style="color: var(--gray);">ยังไม่มีแบบทดสอบในระบบ</h3>
                        <a href="?page=manage&section=quizzes&action=add" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> เพิ่มแบบทดสอบแรก
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>คำถาม</th>
                                    <th>บทเรียน</th>
                                    <th>ประเภท</th>
                                    <th>คะแนน</th>
                                    <th>คำตอบที่ถูกต้อง</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($quizzes as $quiz): ?>
                                    <tr>
                                        <td style="font-weight: 600; max-width: 300px;">
                                            <?php echo htmlspecialchars(substr($quiz['question'], 0, 50)); ?>...
                                        </td>
                                        <td><?php echo htmlspecialchars($quiz['lesson_title']); ?></td>
                                        <td>
                                            <?php 
                                            $type_text = [
                                                'single_choice' => '<span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">ตัวเลือกเดียว</span>',
                                                'multiple_choice' => '<span style="background: #2196f3; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">หลายตัวเลือก</span>',
                                                'true_false' => '<span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 5px; font-size: 0.8rem;">ถูก/ผิด</span>'
                                            ];
                                            echo $type_text[$quiz['question_type']] ?? $quiz['question_type'];
                                            ?>
                                        </td>
                                        <td><?php echo $quiz['points']; ?></td>
                                        <td><?php echo htmlspecialchars($quiz['correct_answer']); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 5px;">
                                                <button onclick="openDeleteModal('?page=manage&type=quiz&delete_id=<?php echo $quiz['id']; ?>')" 
                                                        class="btn btn-danger" style="padding: 5px 10px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
        <!-- Manage Users -->
        <?php elseif($section == 'users'): ?>
            <?php 
            // Get all users
            $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3><i class="fas fa-users"></i> จัดการผู้ใช้</h3>
                <button class="btn btn-primary" onclick="alert('ฟังก์ชันกำลังพัฒนา...')">
                    <i class="fas fa-plus"></i> เพิ่มผู้ใช้ใหม่
                </button>
            </div>
            
            <?php if(empty($users)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="color: var(--gray);">ยังไม่มีผู้ใช้ในระบบ</h3>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อผู้ใช้</th>
                                <th>ชื่อ-สกุล</th>
                                <th>อีเมล</th>
                                <th>สิทธิ์</th>
                                <th>สมัครเมื่อ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php 
                                        $role_text = [
                                            'student' => '<span style="background: #4caf50; color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8rem;">นักเรียน</span>',
                                            'teacher' => '<span style="background: #2196f3; color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8rem;">ครูผู้สอน</span>',
                                            'admin' => '<span style="background: #f44336; color: white; padding: 3px 10px; border-radius: 5px; font-size: 0.8rem;">ผู้ดูแลระบบ</span>'
                                        ];
                                        echo $role_text[$user['role']] ?? $user['role'];
                                        ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button class="btn btn-outline" style="padding: 5px 10px;" 
                                                    onclick="alert('ฟังก์ชันกำลังพัฒนา...')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="openDeleteModal('?page=manage&type=user&delete_id=<?php echo $user['id']; ?>')" 
                                                        class="btn btn-danger" style="padding: 5px 10px;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
        <!-- Manage Categories -->
        <?php elseif($section == 'categories'): ?>
            <?php 
            // Get all categories
            $stmt = $db->query("SELECT * FROM categories ORDER BY name");
            $all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3><i class="fas fa-folder"></i> จัดการหมวดหมู่</h3>
                <button class="btn btn-primary" onclick="showAddCategoryModal()">
                    <i class="fas fa-plus"></i> เพิ่มหมวดหมู่ใหม่
                </button>
            </div>
            
            <?php if(empty($all_categories)): ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <div style="font-size: 4rem; color: var(--light-gray); margin-bottom: 20px;">
                        <i class="fas fa-folder"></i>
                    </div>
                    <h3 style="color: var(--gray);">ยังไม่มีหมวดหมู่ในระบบ</h3>
                </div>
            <?php else: ?>
                <div class="lesson-grid">
                    <?php foreach($all_categories as $category): ?>
                        <div class="lesson-card">
                            <div class="lesson-card-header">
                                <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            </div>
                            <div class="lesson-card-body">
                                <p style="color: #555; margin-bottom: 15px; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($category['description'] ?? 'ไม่มีคำอธิบาย'); ?>
                                </p>
                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button class="btn btn-outline" style="flex: 1;" 
                                            onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['description'] ?? ''); ?>')">
                                        <i class="fas fa-edit"></i> แก้ไข
                                    </button>
                                    <button onclick="openDeleteModal('?page=manage&type=category&delete_id=<?php echo $category['id']; ?>')" 
                                            class="btn btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Category Modal -->
            <div id="categoryModal" class="modal">
                <div class="modal-content">
                    <h3><i class="fas fa-folder-plus"></i> <span id="modalTitle">เพิ่มหมวดหมู่ใหม่</span></h3>
                    
                    <form id="categoryForm" method="POST" action="">
                        <input type="hidden" id="categoryAction" name="action" value="add_category">
                        <input type="hidden" id="categoryId" name="category_id" value="">
                        
                        <div class="form-group">
                            <label for="categoryName">ชื่อหมวดหมู่ *</label>
                            <input type="text" id="categoryName" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="categoryDescription">คำอธิบาย</label>
                            <textarea id="categoryDescription" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                            <button type="button" onclick="closeCategoryModal()" class="btn btn-outline">ยกเลิก</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function showAddCategoryModal() {
                    document.getElementById('modalTitle').textContent = 'เพิ่มหมวดหมู่ใหม่';
                    document.getElementById('categoryAction').value = 'add_category';
                    document.getElementById('categoryId').value = '';
                    document.getElementById('categoryName').value = '';
                    document.getElementById('categoryDescription').value = '';
                    document.getElementById('categoryModal').classList.add('show');
                }
                
                function editCategory(id, name, description) {
                    document.getElementById('modalTitle').textContent = 'แก้ไขหมวดหมู่';
                    document.getElementById('categoryAction').value = 'update_category';
                    document.getElementById('categoryId').value = id;
                    document.getElementById('categoryName').value = name;
                    document.getElementById('categoryDescription').value = description;
                    document.getElementById('categoryModal').classList.add('show');
                }
                
                function closeCategoryModal() {
                    document.getElementById('categoryModal').classList.remove('show');
                }
                
                // Handle category form submission
                document.getElementById('categoryForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if(response.ok) {
                            location.reload();
                        }
                    });
                });
            </script>
            
        <?php endif; ?>
    </div>
    <?php
}