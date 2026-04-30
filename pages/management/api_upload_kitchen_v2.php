<?php
session_start();
require_once '../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$title = $_POST['title'] ?? '';
$desc = $_POST['description'] ?? 'No description provided.';
$tagged_date = $_POST['tagged_date'] ?? date('Y-m-d');
$user_id = $_SESSION['user_id'] ?? 1;

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error.']);
    exit;
}

$target_dir = "../../uploads/kitchen/";
if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

$file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
$filename = uniqid('kb_') . '.' . $file_ext;
$target_file = $target_dir . $filename;
$relative_path = "uploads/kitchen/" . $filename;

if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
    $stmt = $conn->prepare("INSERT INTO kitchen_documentation (title, uploaded_by, photo_path, tagged_date, caption) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $title, $user_id, $relative_path, $tagged_date, $desc);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move file.']);
}
?>
