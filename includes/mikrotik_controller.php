<?php
// ----------------------------------------------------------------
// --- MikroTik Controller for Captive Portal ---
// ----------------------------------------------------------------

require_once 'config.php';
require_once 'routeros_api.php';

class MikroTikController {
    private $api;
    private $host;
    private $username;
    private $password;
    private $port;
    private $connected = false;
    
    public function __construct() {
        $this->host = MIKROTIK_HOST;
        $this->username = MIKROTIK_USERNAME;
        $this->password = MIKROTIK_PASSWORD;
        $this->port = MIKROTIK_PORT ?? 8728;
        $this->api = new RouterosAPI();
    }
    
    /**
     * Connect to MikroTik
     */
    private function connect() {
        if (!$this->connected) {
            $this->connected = $this->api->connect($this->host, $this->username, $this->password, $this->port);
            if (!$this->connected) {
                error_log("Failed to connect to MikroTik at {$this->host}");
            }
        }
        return $this->connected;
    }
    
    /**
     * Disconnect from MikroTik
     */
    private function disconnect() {
        if ($this->connected) {
            $this->api->disconnect();
            $this->connected = false;
        }
    }
    
    /**
     * Allow MAC address with bundle-specific configuration
     */
    public function allowMAC($mac_address, $bundle_data = null) {
        if (!$this->connect()) {
            return false;
        }
        
        try {
            // Remove any existing bindings first for clean setup
            $this->removeExistingBindings($mac_address);
            
            // Create appropriate binding based on bundle type
            if ($bundle_data && isset($bundle_data['is_unlimited']) && $bundle_data['is_unlimited']) {
                $this->createUnlimitedBinding($mac_address, $bundle_data);
            } else {
                $this->createLimitedBinding($mac_address, $bundle_data);
            }
            
            // Set bandwidth limits based on bundle configuration
            $this->configureBandwidthLimits($mac_address, $bundle_data);
            
            // Log the activation with details
            $this->logBundleActivation($mac_address, $bundle_data);
            
            $this->disconnect();
            return true;
            
        } catch (Exception $e) {
            error_log("MikroTik allowMAC error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }
    
    /**
     * Create unlimited bundle binding
     */
    private function createUnlimitedBinding($mac_address, $bundle_data) {
        $expiry_time = $bundle_data['bundle_expiry_time'] ?? 
                      date('Y-m-d H:i', strtotime('+' . ($bundle_data['duration_minutes'] ?? 1440) . ' minutes'));
        
        $comment = sprintf(
            "UNLIMITED - %s - Expires: %s - Activated: %s",
            $bundle_data['name'] ?? 'Unknown Bundle',
            date('Y-m-d H:i', strtotime($expiry_time)),
            date('Y-m-d H:i:s')
        );
        
        $this->api->comm("/ip/hotspot/ip-binding/add", array(
            "mac-address" => $mac_address,
            "type" => "bypassed",
            "comment" => $comment
        ));
        
        error_log("MikroTik: Unlimited bundle activated for MAC {$mac_address}");
    }
    
    /**
     * Create limited bundle binding
     */
    private function createLimitedBinding($mac_address, $bundle_data) {
        $data_limit_mb = $bundle_data['data_limit_mb'] ?? 0;
        $expiry_time = $bundle_data['bundle_expiry_time'] ?? 
                      date('Y-m-d H:i', strtotime('+' . ($bundle_data['duration_minutes'] ?? 1440) . ' minutes'));
        
        $comment = sprintf(
            "LIMITED - %s - %dMB - Expires: %s - Activated: %s",
            $bundle_data['name'] ?? 'Unknown Bundle',
            $data_limit_mb,
            date('Y-m-d H:i', strtotime($expiry_time)),
            date('Y-m-d H:i:s')
        );
        
        $this->api->comm("/ip/hotspot/ip-binding/add", array(
            "mac-address" => $mac_address,
            "type" => "bypassed",
            "comment" => $comment
        ));
        
        error_log("MikroTik: Limited bundle ({$data_limit_mb}MB) activated for MAC {$mac_address}");
    }
    
    /**
     * Configure bandwidth limits based on bundle type
     */
    private function configureBandwidthLimits($mac_address, $bundle_data) {
        if (!defined('MIKROTIK_BANDWIDTH_LIMITING') || !MIKROTIK_BANDWIDTH_LIMITING) {
            return;
        }
        
        // Determine speed limits based on bundle type
        if ($bundle_data && isset($bundle_data['is_unlimited']) && $bundle_data['is_unlimited']) {
            // Unlimited bundles get premium speeds
            $download_limit = $bundle_data['download_limit_kbps'] ?? (DEFAULT_DOWNLOAD_LIMIT * 2);
            $upload_limit = $bundle_data['upload_limit_kbps'] ?? (DEFAULT_UPLOAD_LIMIT * 2);
        } else {
            // Limited bundles get standard speeds
            $download_limit = $bundle_data['download_limit_kbps'] ?? DEFAULT_DOWNLOAD_LIMIT;
            $upload_limit = $bundle_data['upload_limit_kbps'] ?? DEFAULT_UPLOAD_LIMIT;
        }
        
        $this->createBandwidthQueue($mac_address, $download_limit, $upload_limit, $bundle_data);
    }
    
    /**
     * Create bandwidth queue with bundle-specific settings
     */
    private function createBandwidthQueue($mac_address, $download_kbps, $upload_kbps, $bundle_data) {
        // --- Find the user's IP address from their MAC address ---
        $active_users = $this->api->comm("/ip/dhcp-server/lease/print", array(
            "?mac-address" => $mac_address
        ));
        
        if (empty($active_users)) {
            error_log("MikroTik: Could not find active user for MAC {$mac_address} to apply bandwidth limit.");
            return; // Cannot create queue without an IP
        }
        
        $target_ip = $active_users[0]['address'];
        
        $queue_name = "captive_" . str_replace([':', '-'], '_', $mac_address);
        
        // Remove existing queue first
        $this->removeBandwidthLimit($mac_address);
        
        $is_unlimited = ($bundle_data && isset($bundle_data['is_unlimited']) && $bundle_data['is_unlimited']);
        $queue_comment = sprintf(
            "%s - %s - %s/%s kbps - %s",
            $is_unlimited ? 'UNLIMITED' : 'LIMITED',
            $bundle_data['name'] ?? 'Unknown',
            $upload_kbps,
            $download_kbps,
            date('Y-m-d H:i:s')
        );
        
        try {
            $this->api->comm("/queue/simple/add", array(
                "name" => $queue_name,
                "target" => $target_ip, // --- CORRECT: Use the found IP address ---
                "max-limit" => $upload_kbps . "k/" . $download_kbps . "k",
                "comment" => $queue_comment,
                "priority" => $is_unlimited ? "1/1" : "8/8" // Higher priority for unlimited
            ));
            
            error_log("MikroTik: Bandwidth queue created for {$mac_address} ({$target_ip}) - {$upload_kbps}k/{$download_kbps}k");
        } catch (Exception $e) {
            error_log("MikroTik: Failed to create bandwidth queue: " . $e->getMessage());
        }
    }
    
    /**
     * Remove existing bindings for clean setup
     */
    private function removeExistingBindings($mac_address) {
        try {
            $existing = $this->api->comm("/ip/hotspot/ip-binding/print", array(
                "?mac-address" => $mac_address
            ));
            
            foreach ($existing as $binding) {
                $this->api->comm("/ip/hotspot/ip-binding/remove", array(
                    ".id" => $binding['.id']
                ));
            }
        } catch (Exception $e) {
            error_log("MikroTik: Error removing existing bindings: " . $e->getMessage());
        }
    }
    
    /**
     * Log bundle activation details
     */
    private function logBundleActivation($mac_address, $bundle_data) {
        if (!$bundle_data) return;
        
        $is_unlimited = isset($bundle_data['is_unlimited']) && $bundle_data['is_unlimited'];
        $bundle_type = $is_unlimited ? 'UNLIMITED' : ($bundle_data['data_limit_mb'] . 'MB LIMITED');
        
        $log_entry = sprintf(
            "%s - MAC: %s - Bundle: %s (%s) - Type: %s - Duration: %d mins - Expires: %s\n",
            date('Y-m-d H:i:s'),
            $mac_address,
            $bundle_data['name'] ?? 'Unknown',
            $bundle_type,
            $is_unlimited ? 'UNLIMITED' : 'LIMITED',
            $bundle_data['duration_minutes'] ?? 0,
            $bundle_data['bundle_expiry_time'] ?? 'Unknown'
        );
        
        file_put_contents('mikrotik_activations.log', $log_entry, FILE_APPEND);
    }

    /**
     * Block MAC address from accessing internet
     */
    public function blockMAC($mac_address) {
        if (!$this->connect()) {
            return false;
        }
        
        try {
            // Find existing bindings for this MAC
            $bindings = $this->api->comm("/ip/hotspot/ip-binding/print", array(
                "?mac-address" => $mac_address
            ));
            
            // Remove all bindings for this MAC
            foreach ($bindings as $binding) {
                $this->api->comm("/ip/hotspot/ip-binding/remove", array(
                    ".id" => $binding['.id']
                ));
            }
            
            // Also remove any queue rules for this MAC
            $this->removeBandwidthLimit($mac_address);
            
            // Log the blocking action
            $log_entry = sprintf(
                "%s - BLOCKED - MAC: %s - Access revoked\n",
                date('Y-m-d H:i:s'),
                $mac_address
            );
            file_put_contents('mikrotik_activations.log', $log_entry, FILE_APPEND);
            
            $this->disconnect();
            error_log("MikroTik: Blocked access for MAC {$mac_address}");
            return true;
            
        } catch (Exception $e) {
            error_log("MikroTik blockMAC error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Set bandwidth limit for MAC address (legacy method for backward compatibility)
     */
    public function setBandwidthLimit($mac_address, $limit_kbps) {
        return $this->createBandwidthQueue($mac_address, $limit_kbps, $limit_kbps, array('name' => 'Legacy Limit'));
    }

    /**
     * Remove bandwidth limit for MAC address
     */
    public function removeBandwidthLimit($mac_address) {
        if (!$this->connected && !$this->connect()) {
            return false;
        }
        
        try {
            $queue_name = "captive_" . str_replace([':', '-'], '_', $mac_address);
            
            $queues = $this->api->comm("/queue/simple/print", array(
                "?name" => $queue_name
            ));
            
            foreach ($queues as $queue) {
                $this->api->comm("/queue/simple/remove", array(
                    ".id" => $queue['.id']
                ));
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("MikroTik removeBandwidthLimit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get detailed bundle status from MikroTik
     */
    public function getBundleStatus($mac_address) {
        if (!$this->connect()) {
            return null;
        }
        
        try {
            // Get IP binding info
            $bindings = $this->api->comm("/ip/hotspot/ip-binding/print", array(
                "?mac-address" => $mac_address
            ));
            
            // Get queue info
            $queue_name = "captive_" . str_replace([':', '-'], '_', $mac_address);
            $queues = $this->api->comm("/queue/simple/print", array(
                "?name" => $queue_name
            ));
            
            $this->disconnect();
            
            return array(
                'binding' => !empty($bindings) ? $bindings[0] : null,
                'queue' => !empty($queues) ? $queues[0] : null,
                'has_access' => !empty($bindings),
                'bandwidth_limited' => !empty($queues),
                'bundle_type' => $this->parseBundleTypeFromComment($bindings[0]['comment'] ?? ''),
                'expiry_time' => $this->parseExpiryFromComment($bindings[0]['comment'] ?? '')
            );
            
        } catch (Exception $e) {
            error_log("MikroTik getBundleStatus error: " . $e->getMessage());
            $this->disconnect();
            return null;
        }
    }
    
    /**
     * Parse bundle type from comment
     */
    private function parseBundleTypeFromComment($comment) {
        if (strpos($comment, 'UNLIMITED') !== false) {
            return 'unlimited';
        } elseif (strpos($comment, 'LIMITED') !== false) {
            return 'limited';
        }
        return 'unknown';
    }
    
    /**
     * Parse expiry time from comment
     */
    private function parseExpiryFromComment($comment) {
        if (preg_match('/Expires: ([0-9\-: ]+)/', $comment, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get active hotspot users
     */
    public function getActiveUsers() {
        if (!$this->connect()) {
            return array();
        }
        
        try {
            $active_users = $this->api->comm("/ip/hotspot/active/print");
            $this->disconnect();
            return $active_users;
            
        } catch (Exception $e) {
            error_log("MikroTik getActiveUsers error: " . $e->getMessage());
            $this->disconnect();
            return array();
        }
    }
    
    /**
     * Get IP bindings with enhanced information
     */
    public function getIPBindings() {
        if (!$this->connect()) {
            return array();
        }
        
        try {
            $bindings = $this->api->comm("/ip/hotspot/ip-binding/print");
            
            // Enhance bindings with parsed information
            foreach ($bindings as &$binding) {
                $binding['bundle_type'] = $this->parseBundleTypeFromComment($binding['comment'] ?? '');
                $binding['expiry_time'] = $this->parseExpiryFromComment($binding['comment'] ?? '');
                $binding['is_captive_portal'] = strpos($binding['comment'] ?? '', 'CaptivePortal') !== false ||
                                              strpos($binding['comment'] ?? '', 'UNLIMITED') !== false ||
                                              strpos($binding['comment'] ?? '', 'LIMITED') !== false;
            }
            
            $this->disconnect();
            return $bindings;
            
        } catch (Exception $e) {
            error_log("MikroTik getIPBindings error: " . $e->getMessage());
            $this->disconnect();
            return array();
        }
    }
    
    /**
     * Clean up expired bindings
     */
    public function cleanupExpiredBindings() {
        if (!$this->connect()) {
            return false;
        }
        
        try {
            $bindings = $this->api->comm("/ip/hotspot/ip-binding/print");
            $cleaned_count = 0;
            
            foreach ($bindings as $binding) {
                $comment = $binding['comment'] ?? '';
                
                // Only process our captive portal bindings
                if (strpos($comment, 'CaptivePortal') === false && 
                    strpos($comment, 'UNLIMITED') === false && 
                    strpos($comment, 'LIMITED') === false) {
                    continue;
                }
                
                $expiry_time = $this->parseExpiryFromComment($comment);
                if ($expiry_time && strtotime($expiry_time) < time()) {
                    // Binding has expired, remove it
                    $this->api->comm("/ip/hotspot/ip-binding/remove", array(
                        ".id" => $binding['.id']
                    ));
                    
                    // Also remove queue if exists
                    $mac_address = $binding['mac-address'] ?? '';
                    if ($mac_address) {
                        $this->removeBandwidthLimit($mac_address);
                    }
                    
                    $cleaned_count++;
                    error_log("MikroTik: Cleaned expired binding for MAC {$mac_address}");
                }
            }
            
            $this->disconnect();
            error_log("MikroTik: Cleanup completed - {$cleaned_count} expired bindings removed");
            return $cleaned_count;
            
        } catch (Exception $e) {
            error_log("MikroTik cleanupExpiredBindings error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Generate comment for IP binding (enhanced)
     */
    private function generateComment($bundle_data) {
        if ($bundle_data) {
            $is_unlimited = isset($bundle_data['is_unlimited']) && $bundle_data['is_unlimited'];
            $bundle_name = $bundle_data['name'] ?? 'Unknown Bundle';
            
            if ($is_unlimited) {
                return "CaptivePortal - UNLIMITED - {$bundle_name} - " . date('Y-m-d H:i:s');
            } else {
                $data_limit = $bundle_data['data_limit_mb'] ?? 0;
                return "CaptivePortal - LIMITED - {$bundle_name} - {$data_limit}MB - " . date('Y-m-d H:i:s');
            }
        }
        return "CaptivePortal - " . date('Y-m-d H:i:s');
    }
    
    /**
     * Get all simple queues with their stats, filtered by our naming convention.
     */
    public function getQueuesWithStats() {
        if (!$this->connect()) {
            return array();
        }
        
        try {
            // Fetch all simple queues with the 'captive_' prefix and their stats in a single call.
            $queues = $this->api->comm("/queue/simple/print", array(
                "?name" => "captive_",
                "stats" => ""
            ));
            
            $this->disconnect();
            return $queues;
            
        } catch (Exception $e) {
            error_log("MikroTik getQueuesWithStats error: " . $e->getMessage());
            $this->disconnect();
            return array();
        }
    }

    /**
     * Test MikroTik connection with enhanced diagnostics
     */
    public function testConnection() {
        if ($this->connect()) {
            try {
                $identity = $this->api->comm("/system/identity/print");
                $version = $this->api->comm("/system/resource/print");
                $this->disconnect();
                return array(
                    'success' => true, 
                    'identity' => $identity,
                    'version' => $version[1]['version'] ?? 'Unknown',
                    'platform' => $version[1]['platform'] ?? 'Unknown',
                    'uptime' => $version[1]['uptime'] ?? 'Unknown'
                );
            } catch (Exception $e) {
                $this->disconnect();
                return array('success' => false, 'error' => $e->getMessage());
            }
        }
        return array('success' => false, 'error' => 'Connection failed - Check host, username, password, and port');
    }

    /**
     * Get MAC address for a given IP from the hotspot active list.
     */
    public function getMacForIp($ip_address) {
        if (!$this->connect()) {
            return null;
        }
        
        try {
            $active_user = $this->api->comm("/ip/dhcp-server/lease/print", array(
                "?address" => $ip_address
            ));
          
         
            
            $this->disconnect();
            
            if (!empty($active_user) && isset($active_user[1]['mac-address'])) {
                return $active_user[1]['mac-address'];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("MikroTik getMacForIp error: " . $e->getMessage());
            $this->disconnect();
            return null;
        }
    }
}