<?php
//Brian Patoilo started 2/11/26 Login logic.

require_once __DIR__ . '/DB.php';

function handleLogin($data) {
    if (empty($data['username']) || empty($data['password'])) {
        return [
            'success' => false,
            'message' => 'Username and password are required'
        ];
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT user_id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $data['username']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($data['password'], $row['password'])) {
                $response = [
                    'success' => true,
                    'message' => 'Login successful',
                    'user_id' => $row['user_id'],
                    'username' => $row['username']
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Incorrect password'
                ];
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'User not found'
            ];
        }

        $stmt->close();
        $db->close();

        return $response;

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}
