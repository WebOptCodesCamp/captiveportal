# MikroTik Captive Portal Troubleshooting Guide

This guide provides the configuration steps to fix issues where users are not automatically redirected for HTTPS sites (like google.com) and the "Sign in to network" notification does not appear on mobile devices.

## The Problem: HTTPS and "Sign in to network" Failures

Your portal correctly redirects plain `http://` traffic. However, it fails for `https://` sites and for the OS connectivity check for two main reasons:

1.  **HTTPS Encryption:** You cannot transparently redirect encrypted HTTPS traffic. When the router tries to intercept the connection, the user's browser sees a certificate error and stops the connection, resulting in a "no internet" error instead of a redirect.
2.  **OS Connectivity Check:** Modern phones check for a captive portal by trying to contact a specific `http` or `https` address (e.g., `connectivitycheck.gstatic.com`). If this check fails due to the HTTPS issue or a DNS problem, the "Sign in to network" notification will not be triggered.

## The Solution: DNS Interception

The most reliable solution is to configure the MikroTik router to intercept all DNS requests from unauthenticated users. We will configure the router to "lie" and tell the user's device that the IP address for *every* domain is the IP address of your captive portal. This forces the device to talk to your portal, which correctly triggers the captive portal notification.

---

## Configuration Steps

Follow these steps exactly in your MikroTik configuration (using WinBox or WebFig).

### Step 1: Enable DNS Server on the Router

First, ensure your router is acting as a DNS server that can answer queries.

1.  Go to **IP > DNS**.
2.  In the DNS Settings window, check the box for **Allow Remote Requests**.
3.  Click **Apply**.

### Step 2: Force Hotspot Users to Use Your Router for DNS

Next, ensure that clients connected to the hotspot are using your router for their DNS lookups.

1.  Go to **IP > Hotspot**.
2.  Click the **Server Profiles** tab.
3.  Double-click on your active hotspot profile to open it.
4.  In the **Login** tab, make sure the **DNS Name** field is empty.
5.  The hotspot network configuration (usually in `IP > DHCP Server > Networks`) should be set to provide the router's own IP as the DNS server. This is the default behavior and usually does not need to be changed.

### Step 3: Create a Universal DNS Redirect (Firewall Rule)

This firewall rule will intercept all DNS requests (on port 53) from any user on the hotspot network and force the request to be handled by the router itself.

1.  Go to **IP > Firewall**.
2.  Click the **NAT** tab.
3.  Click the **+** button to add a new rule.
4.  In the **General** tab:
    *   **Chain:** `dstnat`
    *   **Protocol:** `udp`
    *   **Dst. Port:** `53`
    *   **In. Interface:** Select your main hotspot bridge interface (e.g., `bridge-hotspot`).
5.  Click the **Action** tab:
    *   **Action:** `redirect`
6.  Click **OK** to save the rule. Make sure this rule is positioned above any general masquerade rules if you have a complex setup.

### Step 4: Create a Static DNS Entry to Catch Everything

This is the final and most important step. We will create a static DNS entry that acts as a "catch-all" for every domain requested by an unauthenticated user.

1.  Go to **IP > DNS**.
2.  Click the **Static** button at the bottom of the window.
3.  In the DNS Static window, click the **+** button to add a new entry.
4.  Fill in the fields:
    *   **Name:** Enter a regular expression to match all possible domains: `.*`
    *   **Address:** Enter the IP address of your captive portal web server (the server running this PHP application).
5.  Click **OK** to save the entry.

---

## How It Works

With this configuration in place, the following will happen:

1.  A user connects to the WiFi.
2.  Their phone tries to look up `connectivitycheck.gstatic.com`.
3.  The firewall rule redirects this DNS query to the router's internal DNS server.
4.  The static DNS entry matches the requested name (`.*`) and replies with your portal's IP address.
5.  The phone then tries to contact `connectivitycheck.gstatic.com` but is actually talking to your web server. It receives the portal's HTML page, realizes it is behind a captive portal, and triggers the **"Sign in to network"** notification.
