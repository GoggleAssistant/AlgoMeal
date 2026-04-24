<?php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Restriction name cannot be empty.']);
    exit;
}

// Check if exists
$stmt = $conn->prepare("SELECT restriction_id FROM dietary_restrictions WHERE restriction_name = ?");
$stmt->bind_param("s", $name);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode(['success' => true, 'restriction_id' => $row['restriction_id'], 'message' => 'Already exists.']);
    exit;
}

// Insert
$stmt = $conn->prepare("INSERT INTO dietary_restrictions (restriction_name) VALUES (?)");
$stmt->bind_param("s", $name);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'restriction_id' => $conn->insert_id, 'message' => 'Added successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
