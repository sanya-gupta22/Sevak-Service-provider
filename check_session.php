<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['valid' => false]);
} else {
    echo json_encode(['valid' => true]);
}
?>