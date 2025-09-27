# Captive Portal System - Deployment Guide

## Prerequisites
- MikroTik RouterOS VM running
- XAMPP installed and running
- Your captive portal project files
- Winbox downloaded and installed

## Step 1: Connect to MikroTik Using Winbox

1. **Open Winbox**
2. **Click "Neighbors" tab**
3. **Find your MikroTik VM** in the list
4. **Double-click to connect**
5. **Login**: Username `admin`, Password (leave empty)

## Step 2: Configure IP Addresses

### 2.1 Add WAN IP
1. **Go to: IP → Addresses**
2. **Click "+" button**
3. **Enter:**
   - Address: `192.168.1.100/24`
   - Interface: `ether1`
4. **Click OK**

### 2.2 Add LAN IP
1. **Click "+" button again**
2. **Enter:**
   - Address: `192.168.100.1/24`
   - Interface: `ether2`
4. **Click OK**

### 2.3 Add Default Route
1. **Go to: IP → Routes**
2. **Click "+" button**
3. **Enter:**
   - Dst. Address: `0.0.0.0/0`
   - Gateway: `192.168.1.1`
4. **Click OK**

## Step 3: Configure DHCP Server

### 3.1 Create IP Pool
1. **Go to: IP → Pool**
2. **Click "+" button**
3. **Enter:**
   - Name: `portal-pool`
   - Addresses: `192.168.100.2-192.168.100.254`
4. **Click OK**

### 3.2 Configure DHCP Server
1. **Go to: IP → DHCP Server**
2. **Click "+" button**
3. **Enter:**
   - Interface: `ether2`
   - Address Pool: `portal-pool`
4. **Click OK**

### 3.3 Configure DHCP Network
1. **Go to: IP → DHCP Server → Networks**
2. **Click "+" button**
3. **Enter:**
   - Address: `192.168.100.0/24`
   - Gateway: `192.168.100.1`
   - DNS Server: `192.168.100.1`
4. **Click OK**

## Step 4: Configure DNS

1. **Go to: IP → DNS**
2. **Click "Settings" button**
3. **Enter:**
   - Servers: `8.8.8.8,8.8.4.4`
   - Check "Allow Remote Requests"
4. **Click OK**

## Step 5: Configure Firewall Rules

### 5.1 Allow Established Connections
1. **Go to: IP → Firewall → Filter Rules**
2. **Click "+" button**
3. **Enter:**
   - Chain: `forward`
   - Connection State: `established,related`
   - Action: `accept`
4. **Click OK**

### 5.2 Allow DNS
1. **Click "+" button**
2. **Enter:**
   - Chain: `forward`
   - Protocol: `udp`
   - Dst. Port: `53`
   - Action: `accept`
4. **Click OK**

### 5.3 Allow HTTP to Your Server
1. **Click "+" button**
2. **Enter:**
   - Chain: `forward`
   - Dst. Address: `192.168.100.1`
   - Dst. Port: `80`
   - Action: `accept`
4. **Click OK**

### 5.4 Block All Other Traffic
1. **Click "+" button**
2. **Enter:**
   - Chain: `forward`
   - Action: `drop`
4. **Click OK**

## Step 6: Configure NAT

### 6.1 Enable NAT
1. **Go to: IP → Firewall → NAT**
2. **Click "+" button**
3. **Enter:**
   - Chain: `srcnat`
   - Out. Interface: `ether1`
   - Action: `masquerade`
4. **Click OK**

### 6.2 Redirect HTTP to Your Server
1. **Click "+" button**
2. **Enter:**
   - Chain: `dstnat`
   - Protocol: `tcp`
   - Dst. Port: `80`
   - Action: `dst-nat`
   - To Addresses: `192.168.100.1`
   - To Ports: `80`
4. **Click OK**

## Step 7: Test the Setup

### 7.1 Test MikroTik Connectivity
1. **In Winbox: Tools → Ping**
2. **Ping your laptop**: `192.168.1.1`
3. **Ping internet**: `8.8.8.8`

### 7.2 Test Captive Portal
1. **Connect a device** to MikroTik network
2. **Device should get IP**: `192.168.100.x`
3. **Try to browse internet** - should redirect to your portal

## Troubleshooting

### If MikroTik Can't Reach Internet
1. **Check routes**: IP → Routes
2. **Verify gateway IP**

### If Clients Can't Get IP
1. **Check DHCP**: IP → DHCP Server
2. **Verify DHCP is enabled**

### If Portal Not Redirecting
1. **Check NAT rules**: IP → Firewall → NAT
2. **Verify redirect rules exist**

## Network Flow
```
Internet (USB Tethering) → Your Laptop (192.168.1.x) → MikroTik VM (192.168.100.1) → Client Devices (192.168.100.2-254) → Your XAMPP Server (192.168.100.1:80)
```

This corrected version removes all the assumed MikroTik API integration and focuses only on the actual network configuration that can be verified and tested.

## What This Setup Does

1. **MikroTik VM** acts as a router/gateway
2. **Clients connect** to MikroTik and get IP addresses
3. **All HTTP traffic** gets redirected to your XAMPP server
4. **Your portal** handles the authentication and payment
5. **Manual access control** - you would need to manually configure MikroTik rules for each user

## Note on API Integration

The MikroTik API integration would require:
- Installing a RouterOS API library in your PHP project
- Writing code to communicate with MikroTik
- Implementing the actual API calls for user management

This deployment guide only covers the basic network setup. The API integration would be a separate development task.
