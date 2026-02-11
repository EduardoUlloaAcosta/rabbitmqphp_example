<?php
/**
Brian Patoilo 2/11/26 register started. May need to be revisited
 *
 * Expected message format:
 * {
 *     "type": "register",
 *     "username": "john",
 *     "password": "secret123",
 *     "email": "johnemail.com
 * }
 * May need to leave this here so if there are errors it's
 */

require_once __DIR__ . '/DB.php';

function handleRegister($data) {
    if (empty($data['username']) || empty($data['password']) || empty($data['email'])) {
        return [
            'success' => false,
            'message' => 'enter the stuff dude'
        ];
    }

    try {
        $db = getDB();

        //check username
        $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $data['username']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $checkStmt->close();
            $db->close();
            return [
                'success' => false,
                'message' => 'Username exists'
            ];
        }
        $checkStmt->close();

        // check email
        $checkEmail = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $data['email']);
        $checkEmail->execute();
        $emailResult = $checkEmail->get_result();
        if ($emailResult->num_rows > 0) {
            $checkEmail->close();
            $db->close();
            return [
                'success' => false,
                'message' => 'Email taken'
            ];
        }
        $checkEmail->close();

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        //add the user to the db
        $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data['username'], $hashedPassword, $data['email']);
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $stmt->insert_id
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Registration failed: ' . $stmt->error
            ];
        }

        $stmt->close();
        $db->close();
        return $response;

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Database no gud: ' . $e->getMessage()
        ];
    }
}
