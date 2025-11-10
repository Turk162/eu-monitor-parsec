<?php
// ===================================================================
// AUTHENTICATION SYSTEM - ENGLISH VERSION
// ===================================================================

class Auth {
    private $db;
    
    public function __construct($db_connection) {
        $this->db = $db_connection;
    }
    
    public function login($username, $password) {
        try {
            $sql = "SELECT id, username, email, password, full_name, role, partner_id, is_active 
                    FROM users 
                    WHERE (username = ? OR email = ?) AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful - start session
                if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['partner_id'] = $user['partner_id'];
                $_SESSION['logged_in'] = true;
                
                return ['success' => true, 'user' => $user];
            } else {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error during login'];
        }
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }
    
    public function hasRole($required_roles) {
        if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
        if (!isset($_SESSION['role'])) return false;
        
        if (is_string($required_roles)) {
            $required_roles = [$required_roles];
        }
        
        return in_array($_SESSION['role'], $required_roles);
    }
    
    public function requireRole($required_roles) {
        if (!$this->hasRole($required_roles)) {
            header('HTTP/1.0 403 Forbidden');
            die('Access denied. You do not have the necessary permissions.');
        }
    }
    
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$new_password_hash, $user_id]);
            
            return ['success' => true, 'message' => 'Password updated successfully'];
            
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating password'];
        }
    }
    
    public function updateProfile($user_id, $data) {
        try {
            $sql = "UPDATE users SET full_name = ?, email = ?, partner_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['full_name'],
                $data['email'], 
                $data['partner_id'],
                $user_id
            ]);
            
            // Update session data
            if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
            $_SESSION['full_name'] = $data['full_name'];
            $_SESSION['email'] = $data['email'];
            $_SESSION['partner_id'] = $data['partner_id'];
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
            
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating profile'];
        }
    }
    
    public function createUser($data) {
        try {
            // Check if username or email already exists
            $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['username'], $data['email']]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Create new user
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password, full_name, role, partner_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['username'],
                $data['email'],
                $password_hash,
                $data['full_name'],
                $data['role'],
                $data['partner_id']
            ]);
            
            return ['success' => true, 'message' => 'User created successfully'];
            
        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating user'];
        }
    }
    
    public function deactivateUser($user_id) {
        try {
            $sql = "UPDATE users SET is_active = 0 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            
            return ['success' => true, 'message' => 'User deactivated successfully'];
            
        } catch (PDOException $e) {
            error_log("User deactivation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deactivating user'];
        }
    }
    
    public function reactivateUser($user_id) {
        try {
            $sql = "UPDATE users SET is_active = 1 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            
            return ['success' => true, 'message' => 'User reactivated successfully'];
            
        } catch (PDOException $e) {
            error_log("User reactivation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error reactivating user'];
        }
    }
    
    public function getAllUsers($include_inactive = false) {
        try {
            $sql = "SELECT id, username, email, full_name, role, organization, country, is_active, created_at 
                    FROM users";
            
            if (!$include_inactive) {
                $sql .= " WHERE is_active = 1";
            }
            
            $sql .= " ORDER BY full_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Get users error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUserById($user_id) {
        try {
            $sql = "SELECT id, username, email, full_name, role, organization, country, is_active, created_at 
                    FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    public function resetPassword($user_id, $new_password) {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$password_hash, $user_id]);
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error resetting password'];
        }
    }
    /**
     * Generate and store password reset token
     */
    public function createPasswordResetToken($email) {
        try {
            // Check if user exists and is active
            $sql = "SELECT id, username, full_name, email FROM users WHERE email = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Non rivelare se l'email esiste o no (sicurezza)
                return ['success' => true, 'message' => 'If email exists, reset link has been sent'];
            }
            
            // Generate secure random token
            $token = bin2hex(random_bytes(32)); // 64 caratteri
            
            // Token expires in 1 hour
$expires_at = date('Y-m-d H:i:s', time() + 3600);
            
            // Delete old unused tokens for this user
            $sql = "DELETE FROM password_reset_tokens WHERE user_id = ? AND used = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user['id']]);
            
            // Insert new token
            $sql = "INSERT INTO password_reset_tokens (user_id, email, token, expires_at) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user['id'], $email, $token, $expires_at]);
            
            return [
                'success' => true,
                'message' => 'Reset token created',
                'token' => $token,
                'user' => $user,
                'expires_at' => $expires_at
            ];
            
        } catch (PDOException $e) {
            error_log("Create reset token error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating reset token'];
        }
    }
    
    public function verifyPasswordResetToken($token) {
    try {
        $sql = "SELECT prt.id, prt.user_id, prt.email, prt.token, prt.expires_at, prt.used,
                       u.username, u.full_name, u.email as user_email, u.is_active
                FROM password_reset_tokens prt
                JOIN users u ON prt.user_id = u.id
                WHERE prt.token = ? 
                AND prt.used = 0 
                AND u.is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        // Check expiry in PHP instead of SQL
        if (strtotime($result['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Token has expired'];
        }
        
        return [
            'success' => true,
            'user_id' => $result['user_id'],
            'email' => $result['user_email'],
            'username' => $result['username'],
            'full_name' => $result['full_name']
        ];
        
    } catch (PDOException $e) {
        error_log("Verify reset token error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error verifying token'];
    }
}
    /**
     * Reset password using token
     */
    public function resetPasswordWithToken($token, $new_password) {
        try {
            // Verify token
            $verification = $this->verifyPasswordResetToken($token);
            if (!$verification['success']) {
                return $verification;
            }
            
            $user_id = $verification['user_id'];
            
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$password_hash, $user_id]);
            
            // Mark token as used
            $sql = "UPDATE password_reset_tokens SET used = 1 WHERE token = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$token]);
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (PDOException $e) {
            error_log("Reset password with token error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error resetting password'];
        }
    }
}
?>  