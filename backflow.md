# Captive Portal System with MikroTik Integration - Backend Flow Documentation

## System Overview

This document explains how the enhanced captive portal system works behind the scenes, integrating custom payment processing with MikroTik router control via API.

## Architecture Components

### 1. **Custom Captive Portal** (Your Existing System)
- **Entry Point**: `index.php` - First page users see
- **Payment Processing**: M-Pesa STK Push integration
- **Database**: MySQL with device tracking and bundle management
- **User Interface**: Custom portal with TailwindCSS

### 2. **MikroTik Router Integration** (New Addition)
- **API Communication**: RouterOS API (port 8728)
- **Traffic Control**: Firewall rules for access control
- **Bandwidth Management**: Queue management for speed limits
- **Session Monitoring**: Real-time traffic statistics

## Detailed Backend Flow

### Phase 1: User Connection & MAC Detection

```
User Device → WiFi Connection → Router → Your Server
```

**Step 1: Initial Connection**
```php
// index.php - Entry point
$mac_address = get_mac_address(); // Uses ARP table to find MAC
$_SESSION['mac_address'] = $mac_address; // Store for later use
```

**Behind the Scenes:**
1. User connects to WiFi
2. Router assigns IP address (DHCP)
3. Your server detects connection via `$_SERVER['REMOTE_ADDR']`
4. Server queries Windows ARP table: `arp -a [IP_ADDRESS]`
5. Extracts MAC address using regex pattern matching
6. Stores MAC in session for payment processing

**MAC Address Detection Logic:**
```php
function get_mac_address() {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $arp_output = shell_exec('arp -a ' . escapeshellarg($ip_address));
    // Parse ARP output to extract MAC address
    // Convert to standard format: XX-XX-XX-XX-XX-XX
}
```

### Phase 2: Access Control Check

**Step 2: Bundle Validation**
```php
$has_access = has_active_bundle($mysqli, $mac_address);
```

**Database Query Logic:**
```sql
SELECT b.data_limit_mb, b.is_unlimited, d.data_used_mb, d.bundle_expiry_time
FROM devices d
JOIN bundles b ON d.bundle_id = b.id
WHERE d.mac_address = ? AND d.bundle_expiry_time > NOW()
```

**Access Control Decision Tree:**
1. **No Bundle Found** → Redirect to `bundles.php`
2. **Bundle Found** → Check constraints:
   - **Time Constraint**: `bundle_expiry_time > NOW()`
   - **Data Constraint**: `data_used_mb < data_limit_mb` (if not unlimited)
3. **All Constraints Met** → Grant access
4. **Any Constraint Failed** → Redirect to purchase

### Phase 3: MikroTik API Integration

**Step 3: Router Control**
```php
if ($has_bundle) {
    $mikrotik = new MikroTikAPI(MIKROTIK_HOST, MIKROTIK_USER, MIKROTIK_PASS);
    $mikrotik->allowUser($mac_address);
} else {
    $mikrotik->blockUser($mac_address);
}
```

**MikroTik API Operations:**

**For Allowed Users:**
```bash
# Create firewall rule to allow traffic
/ip/firewall/filter/add chain=forward src-mac-address=AA:BB:CC:DD:EE:FF action=accept comment="Portal-Allowed-AA:BB:CC:DD:EE:FF"
```

**For Blocked Users:**
```bash
# Create firewall rule to block traffic
/ip/firewall/filter/add chain=forward src-mac-address=AA:BB:CC:DD:EE:FF action=drop comment="Portal-Blocked-AA:BB:CC:DD:EE:FF"
```

### Phase 4: Bundle-Specific Configuration

**Step 4: Bundle Type Handling**

**For Time-Based Unlimited Bundles:**
```php
if ($bundle_details['is_unlimited']) {
    $mikrotik->setTimeBasedAccess($mac_address, $bundle_details['bundle_expiry_time']);
}
```

**MikroTik Operations:**
```bash
# Schedule automatic expiry
/system/script/add name="expiry_AA_BB_CC_DD_EE_FF" source="/ip/firewall/filter/add chain=forward src-mac-address=AA:BB:CC:DD:EE:FF action=drop comment=\"Time-Expired-AA:BB:CC:DD:EE:FF\""

/system/scheduler/add name="expiry_expiry_AA_BB_CC_DD_EE_FF" start-time="23:59:59" start-date="12/31/2024" on-event="expiry_AA_BB_CC_DD_EE_FF"
```

**For Data-Limited Bundles:**
```php
if (!$bundle_details['is_unlimited']) {
    $mikrotik->setDataLimitedAccess($mac_address, $bundle_details);
}
```

**MikroTik Operations:**
```bash
# Set bandwidth limit
/queue/simple/add target=AA:BB:CC:DD:EE:FF max-limit=10M/1M comment="Portal-Limit-AA:BB:CC:DD:EE:FF"

# Set up data monitoring
/ip/firewall/connection/tracking/set enabled=yes

# Schedule periodic data usage check
/system/script/add name="data_check_AA_BB_CC_DD_EE_FF" source=":local mac \"AA:BB:CC:DD:EE:FF\"; :local limit 1024; :local used 0; /ip/firewall/connection/print where src-mac-address=\$mac; :foreach conn in=[/ip/firewall/connection/print where src-mac-address=\$mac] do={:set used (\$used + \$conn->bytes)}; :set used (\$used / 1048576); :if (\$used >= \$limit) do={/ip/firewall/filter/add chain=forward src-mac-address=\$mac action=drop comment=\"Data-Limit-Exceeded-\$mac\"}"

/system/scheduler/add name="data_check_data_check_AA_BB_CC_DD_EE_FF" interval=5m on-event="data_check_AA_BB_CC_DD_EE_FF"
```

### Phase 5: Payment Processing

**Step 5: M-Pesa Integration**
```php
// initiate_payment.php
$response = initiate_stk_push($phone_number, $amount, $account_reference);
```

**Payment Flow:**
1. User selects bundle on `bundles.php`
2. `initiate_payment.php` creates pending transaction
3. M-Pesa STK Push sent to user's phone
4. User enters PIN on phone
5. Safaricom calls `callback.php` with result

### Phase 6: Payment Callback Processing

**Step 6: Callback Handling**
```php
// callback.php - Called by Safaricom
if ($result_code == 0) {
    // Payment successful
    // 1. Update transaction status
    // 2. Activate bundle in database
    // 3. Configure MikroTik router
}
```

**Database Operations:**
```sql
-- Update transaction
UPDATE transactions SET status='completed', mpesa_receipt_number=? WHERE id=?

-- Activate device bundle
INSERT INTO devices (mac_address, bundle_id, data_used_mb, bundle_start_time, bundle_expiry_time) 
VALUES (?, ?, 0.00, ?, ?) 
ON DUPLICATE KEY UPDATE bundle_id=VALUES(bundle_id), data_used_mb=0.00, bundle_start_time=VALUES(bundle_start_time), bundle_expiry_time=VALUES(bundle_expiry_time)
```

**MikroTik Configuration:**
```php
// Allow access immediately
$mikrotik->allowUser($mac_address);

// Configure based on bundle type
if ($bundle['is_unlimited']) {
    $mikrotik->setTimeBasedAccess($mac_address, $expiry_time);
} else {
    $mikrotik->setDataLimitedAccess($mac_address, $bundle);
}
```

## Real-Time Monitoring & Control

### Traffic Monitoring
```php
// Get user traffic statistics
$traffic_bytes = $mikrotik->getUserTraffic($mac_address);
$traffic_mb = $traffic_bytes / 1048576; // Convert to MB
```

### Automatic Expiry Handling

**Time-Based Expiry:**
- MikroTik scheduler automatically blocks access when time expires
- No manual intervention required

**Data-Based Expiry:**
- MikroTik script checks data usage every 5 minutes
- Automatically blocks access when data limit reached

### Session Management
```php
// Force logout by removing all rules
$mikrotik->removeUserRules($mac_address);
$mikrotik->blockUser($mac_address);
```

## Error Handling & Fallbacks

### API Connection Failures
```php
try {
    $mikrotik = new MikroTikAPI(MIKROTIK_HOST, MIKROTIK_USER, MIKROTIK_PASS);
    $mikrotik->allowUser($mac_address);
} catch (Exception $e) {
    error_log('MikroTik API Error: ' . $e->getMessage());
    // Continue with database logic even if API fails
}
```

### Database Fallback
- If MikroTik API fails, system continues with database-only logic
- Users can still access based on database records
- API errors are logged for debugging

## Security Considerations

### MAC Address Validation
- MAC addresses are validated against ARP table
- Prevents MAC address spoofing attacks
- Server IP is excluded from detection

### API Security
- MikroTik API uses username/password authentication
- API connections are encrypted
- Firewall rules are automatically cleaned up

### Session Security
- MAC addresses stored in PHP sessions
- Sessions are validated on each request
- Automatic cleanup of expired sessions

## Performance Optimizations

### Database Queries
- Prepared statements prevent SQL injection
- Indexed queries on MAC addresses
- Efficient JOIN operations

### MikroTik API
- Connection pooling for API calls
- Batch operations where possible
- Error handling prevents API flooding

### Caching
- Bundle details cached in memory
- MAC address lookups cached
- API responses cached when appropriate

## Monitoring & Logging

### System Logs
```php
error_log('MikroTik API Error: ' . $e->getMessage());
error_log('Bundle activation failed for MAC: ' . $mac_address);
```

### Database Logging
- All transactions logged with timestamps
- Device access attempts recorded
- Bundle activations tracked

### MikroTik Logs
- Firewall rule changes logged
- Traffic statistics available
- System scheduler events logged

## Troubleshooting

### Common Issues

**MAC Address Not Detected:**
- Check ARP table: `arp -a`
- Verify network connectivity
- Check firewall rules

**MikroTik API Connection Failed:**
- Verify router IP and credentials
- Check API service is enabled
- Test connection manually

**Bundle Not Activating:**
- Check database constraints
- Verify payment callback
- Check MikroTik firewall rules

### Debug Commands

**Check MikroTik Rules:**
```bash
/ip/firewall/filter/print where src-mac-address=AA:BB:CC:DD:EE:FF
```

**Check Queue Rules:**
```bash
/queue/simple/print where target=AA:BB:CC:DD:EE:FF
```

**Check Scheduler:**
```bash
/system/scheduler/print
```

## Conclusion

This enhanced system provides:
- **Seamless user experience** with custom portal
- **Professional network control** via MikroTik
- **Flexible bundle management** with dual constraints
- **Real-time monitoring** and automatic expiry
- **Robust error handling** and fallback mechanisms

The integration maintains your existing payment system while adding powerful network management capabilities through MikroTik API control.
