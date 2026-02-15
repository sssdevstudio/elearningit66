<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db_config.php';

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_lessons':
        $lessons = getLessons();
        echo json_encode(['success' => true, 'data' => $lessons]);
        break;
        
    case 'get_lesson':
        $id = $_GET['id'] ?? 0;
        $lesson = getLesson($id);
        echo json_encode(['success' => true, 'data' => $lesson]);
        break;
        
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = handleLoginAPI($data);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}