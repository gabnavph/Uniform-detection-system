<?php
// Success Alert Helper Functions

/**
 * Set a success alert message
 * @param string $message Alert message
 * @param string $type Alert type (success, error, warning, info)
 */
function set_alert($message, $type = 'success') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

/**
 * Get and clear the alert message
 * @return array|null Alert data or null if no alert
 */
function get_alert() {
    if (!isset($_SESSION['alert'])) {
        return null;
    }
    
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
    
    // Auto-expire alerts older than 30 seconds
    if (time() - $alert['timestamp'] > 30) {
        return null;
    }
    
    return $alert;
}

/**
 * Generate JavaScript for SweetAlert2 from session alert
 * Echoes the JavaScript directly to output
 */
function render_alert_script() {
    $alert = get_alert();
    if (!$alert) {
        return;
    }
    
    $icon = $alert['type'];
    $title = ucfirst($alert['type']);
    $message = htmlspecialchars($alert['message'], ENT_QUOTES);
    
    // Map alert types to SweetAlert2 icons
    $iconMap = [
        'success' => 'success',
        'error' => 'error',
        'warning' => 'warning',
        'info' => 'info'
    ];
    
    $icon = $iconMap[$alert['type']] ?? 'info';
    
    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '$icon',
            title: '$title',
            text: '$message',
            timer: " . ($alert['type'] === 'success' ? '2000' : '3000') . ",
            showConfirmButton: " . ($alert['type'] === 'error' ? 'true' : 'false') . ",
            toast: true,
            position: 'top-end',
            timerProgressBar: true
        });
    });
    </script>";
}

/**
 * Set success alert for CRUD operations
 */
function set_success_alert($operation, $entity, $name = '') {
    $operations = [
        'created' => 'successfully created',
        'updated' => 'successfully updated', 
        'archived' => 'successfully archived',
        'restored' => 'successfully restored',
        'deleted' => 'permanently deleted',
        'disabled' => 'successfully disabled',
        'enabled' => 'successfully enabled',
        'imported' => 'successfully imported',
        'graduated' => 'successfully graduated and archived',
        'advanced' => 'successfully advanced to next year level'
    ];
    
    $action = $operations[$operation] ?? $operation;
    $message = ucfirst($entity) . ($name ? " '$name'" : '') . " $action.";
    
    set_alert($message, 'success');
}

/**
 * Set error alert for CRUD operations
 */
function set_error_alert($operation, $entity, $error = '') {
    $message = "Failed to $operation $entity." . ($error ? " $error" : '');
    set_alert($message, 'error');
}
?>