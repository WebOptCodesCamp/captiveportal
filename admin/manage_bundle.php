<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// --- Security Check ---
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

try {
    if ($action === 'add' || $action === 'edit') {
        // --- VALIDATION ---
        $bundle_id = filter_input(INPUT_POST, 'bundle_id', FILTER_VALIDATE_INT);
        $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
        $data_limit_mb = filter_input(INPUT_POST, 'data_limit_mb', FILTER_VALIDATE_INT);
        $price_kes = filter_input(INPUT_POST, 'price_kes', FILTER_VALIDATE_FLOAT);
        $duration_minutes = filter_input(INPUT_POST, 'duration_minutes', FILTER_VALIDATE_INT);
        $is_unlimited = isset($_POST['is_unlimited']) ? 1 : 0;
        $speed_tier_key = trim(filter_input(INPUT_POST, 'speed_tier', FILTER_SANITIZE_STRING));

        // Get speeds from tier
        $speed_tier_info = SPEED_TIERS[$speed_tier_key] ?? null;
        if (!$speed_tier_info) {
            throw new Exception('Invalid speed tier selected.');
        }
        $download_limit_kbps = $speed_tier_info['download_kbps'];
        $upload_limit_kbps = $speed_tier_info['upload_kbps'];

        if ($is_unlimited) {
            $data_limit_mb = 0; // Set to 0 for unlimited bundles
        }

        if (empty($name) || ($is_unlimited == 0 && $data_limit_mb === false) || $price_kes === false || $duration_minutes === false) {
            throw new Exception('Invalid form data. Please check all fields.');
        }
        if (($is_unlimited == 0 && $data_limit_mb <= 0) || $price_kes < 0 || $duration_minutes <= 0) {
            throw new Exception('Numeric values must be positive.');
        }

        if ($action === 'add') {
            // --- ADD BUNDLE ---
            $stmt = $mysqli->prepare(
                "INSERT INTO bundles (name, data_limit_mb, price_kes, duration_minutes, is_unlimited, download_limit_kbps, upload_limit_kbps) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) throw new Exception($mysqli->error);
            
            $stmt->bind_param('sidiidd', $name, $data_limit_mb, $price_kes, $duration_minutes, $is_unlimited, $download_limit_kbps, $upload_limit_kbps);
            
            if ($stmt->execute()) {
                $_SESSION['feedback'] = ['type' => 'success', 'message' => 'Bundle "' . htmlspecialchars($name) . '" added successfully!'];
            } else {
                throw new Exception('Failed to add the new bundle.');
            }
        } else { // action === 'edit'
            // --- EDIT BUNDLE ---
            if (empty($bundle_id)) {
                throw new Exception('Invalid bundle ID for editing.');
            }
            $stmt = $mysqli->prepare(
                "UPDATE bundles SET name = ?, data_limit_mb = ?, price_kes = ?, duration_minutes = ?, is_unlimited = ?, download_limit_kbps = ?, upload_limit_kbps = ? WHERE id = ?"
            );
            if (!$stmt) throw new Exception($mysqli->error);

            $stmt->bind_param('sidiiddi', $name, $data_limit_mb, $price_kes, $duration_minutes, $is_unlimited, $download_limit_kbps, $upload_limit_kbps, $bundle_id);

            if ($stmt->execute()) {
                $_SESSION['feedback'] = ['type' => 'success', 'message' => 'Bundle "' . htmlspecialchars($name) . '" updated successfully!'];
            } else {
                throw new Exception('Failed to update the bundle.');
            }
        }
    } elseif ($action === 'delete') {
        // --- DELETE BUNDLE ---
        $bundle_id = filter_input(INPUT_POST, 'bundle_id', FILTER_VALIDATE_INT);
        if (empty($bundle_id)) {
            throw new Exception('Invalid bundle ID for deletion.');
        }

        // Check for dependencies before deleting
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM devices WHERE bundle_id = ?");
        $check_stmt->bind_param('i', $bundle_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_row();
        if ($result[0] > 0) {
            throw new Exception('Cannot delete bundle. It is currently assigned to ' . $result[0] . ' device(s).');
        }

        $stmt = $mysqli->prepare("DELETE FROM bundles WHERE id = ?");
        if (!$stmt) throw new Exception($mysqli->error);

        $stmt->bind_param('i', $bundle_id);

        if ($stmt->execute()) {
            $_SESSION['feedback'] = ['type' => 'success', 'message' => 'Bundle deleted successfully!'];
        } else {
            throw new Exception('Failed to delete the bundle.');
        }
    } else {
        throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    // --- ERROR HANDLING ---
    $_SESSION['feedback'] = ['type' => 'error', 'message' => $e->getMessage()];
}

// --- REDIRECT ---
header('Location: dashboard.php');
exit();
?>
