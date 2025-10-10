# Captive Portal Project

This document provides two complete guides for deploying this captive portal application:
*   **Guide A:** For local testing and development on an Android device using Termux.
*   **Guide B:** For a production environment on a cloud server using DigitalOcean.

---

# Guide A: Local Testing on Android (Termux)

This guide covers the full setup for running the portal on an Android device for testing purposes.

### Guide Structure
*   **Part 1: One-Time Setup (Termux):** Installing the server environment.
*   **Part 2: One-Time Setup (MikroTik):** Configuring your router for the local server.
*   **Part 3: Running the Portal (Daily Use):** Starting and stopping the services.

---

## Part 1: Setting Up the Application in Termux

This section covers the **one-time setup** on your Android device.

#### Step 1: Install Core Packages
Update Termux and install the web server, PHP, database, and SSH server.
```bash
pkg update -y && pkg install -y apache2 php php-apache mariadb openssh
```

#### Step 2: Set Up the MariaDB Database
1.  **Start & Secure:**
    ```bash
    mysqld_safe -u root &
    mysql_secure_installation
    ```
2.  **Create Database & User:** Log in to MariaDB (`mysql -u root -p`) and run:
    ```sql
    CREATE DATABASE captiveportal;
    CREATE USER 'portaluser'@'localhost' IDENTIFIED BY 'your_password';
    GRANT ALL PRIVILEGES ON captiveportal.* TO 'portaluser'@'localhost';
    FLUSH PRIVILEGES; EXIT;
    ```

#### Step 3: (Optional) Move Web Directory for Easy Access
1.  Create a `www` directory in your home folder: `mkdir ~/www`
2.  Edit the Apache config file: `nano ../usr/etc/apache2/httpd.conf`
3.  Change `DocumentRoot` to `"/data/data/com.termux/files/home/www"`
4.  Change the `<Directory "...">` path to `"/data/data/com.termux/files/home/www"`
5.  Restart Apache: `apachectl restart`

#### Step 4: Deploy and Configure the Application
1.  **Place Project Files:** Move all project files into your web directory (`~/www`).
2.  **Import Database Schema:**
    ```bash
    cd ~/www
    mysql -u portaluser -p captiveportal < database.sql
    ```
3.  **Configure `config.php`** with your database credentials.

#### Step 5: Prepare the Cleanup Service
1.  **Install Termux:API:** Get the app from F-Droid, then run: `pkg install termux-api`
2.  **Make Script Executable:** `cd ~/www && chmod +x run_cleanup.sh`

#### Step 6: (Optional) Set Up SSH for Easy File Access
1.  **Set Your Password:** `passwd`
2.  **Start SSH Server:** `sshd` (runs on port 8022)
3.  Connect from a PC with `sftp://<your-username>@<your-phone-ip>:8022`.

---

## Part 2: Configuring the MikroTik for Local Testing

This section covers the **one-time setup** on your MikroTik router to use the local portal on your phone.

#### Step 1: Set a Static IP Address for Your Phone
1.  Connect your phone to the MikroTik WiFi.
2.  Open WinBox or WebFig.
3.  Go to **IP > DHCP Server > Leases**.
4.  Find your phone in the list (identify it by its MAC Address).
5.  Double-click the entry and click **Make Static**.
6.  Note this IP address (e.g., `192.168.88.123`).

#### Step 2: Bypass the Hotspot for Your Phone
1.  Go to **IP > Hotspot > IP Bindings**.
2.  Click **+** to add a new entry.
3.  Set the **IP Address** to your phone's static IP.
4.  Set the **Type** to `bypassed`.
5.  Click **OK**.

#### Step 3: Configure the Hotspot Walled Garden
1.  Go to **IP > Hotspot > Walled Garden**.
2.  Add a new entry for **your phone's IP address** by putting the IP in the **Dst. Host** field.
3.  Add separate entries for each of the following domains in the **Dst. Host** field:
    *   `fonts.googleapis.com`
    *   `fonts.gstatic.com`
    *   `cdn.tailwindcss.com`

#### Step 4: Modify the Hotspot Login Page for Redirection
1.  In WinBox/WebFig, open **Files**.
2.  Find and **backup** `hotspot/login.html`.
3.  Open the file and replace its content with the code below, changing `YOUR_PHONE_STATIC_IP` to your phone's actual static IP.
    ```html
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Connecting...</title>
        <script type="text/javascript">
            window.onload = function() {
                window.location.href = "http://YOUR_PHONE_STATIC_IP:8080/index.php";
            };
        </script>
    </head>
    <body><p>Redirecting...</p></body>
    </html>
    ```
4.  Upload the modified file back to the `hotspot/` directory.

---

## Part 3: Running the Local Portal (Daily Use)

Follow these steps in Termux each time you want to start the portal.

1.  **Start the Database Server:** `mysqld_safe -u root &`
2.  **Start the Web Server:** `apachectl start`
3.  **Start the SSH Server (if needed):** `sshd`
4.  **Start the Cleanup Service:**
    ```bash
    cd ~/www
    termux-wake-lock nohup ./run_cleanup.sh &
    ```

### How to Stop the Services
1.  **Stop Cleanup Service:** Find PID (`ps aux | grep run_cleanup`), `kill <PID>`, then `termux-wake-unlock`.
2.  **Stop SSH Server:** `pkill sshd`
3.  **Stop Web Server:** `apachectl stop`
4.  **Stop Database Server:** `mysqladmin -u root -p shutdown`

---

# Guide B: Production Deployment on DigitalOcean

This guide covers deploying the application to a DigitalOcean Droplet (or any Ubuntu 22.04 server) for a live, production environment.

### Step 1: Create a DigitalOcean Droplet
1.  Sign up for DigitalOcean.
2.  Create a new **Droplet** (Ubuntu 22.04 LTS, basic plan).
3.  Add your **SSH Key** for secure access.
4.  Note the Droplet's **public IP address**.

### Step 2: Initial Server Setup
1.  Login as root: `ssh root@<your_droplet_ip>`
2.  Create a new sudo user: `adduser sammy` and `usermod -aG sudo sammy`.
3.  Setup firewall: `ufw allow OpenSSH`, `ufw allow 'Apache Full'`, `ufw enable`.
4.  Log out and log back in as your new user: `ssh sammy@<your_droplet_ip>`

### Step 3: Install LAMP Stack (Apache, MySQL, PHP)
```bash
sudo apt update
sudo apt install -y apache2 mysql-server php libapache2-mod-php php-mysql git
```

### Step 4: Secure and Prepare MySQL
1.  Secure installation: `sudo mysql_secure_installation`
2.  Create Database & User: Log in to MySQL (`sudo mysql`) and run:
    ```sql
    CREATE DATABASE captiveportal;
    CREATE USER 'portaluser'@'localhost' IDENTIFIED BY 'a_very_strong_password';
    GRANT ALL PRIVILEGES ON captiveportal.* TO 'portaluser'@'localhost';
    FLUSH PRIVILEGES; EXIT;
    ```

### Step 5: Deploy Application Code
1.  Clone your project into the web root: `cd /var/www/html` and `sudo git clone https://github.com/your_username/your_project.git .`
2.  Set permissions: `sudo chown -R www-data:www-data /var/www/html` and `sudo chmod -R 775 /var/www/html`.

### Step 6: Configure the Application
1.  Import Database: `cd /var/www/html && sudo mysql -u portaluser -p captiveportal < database.sql`
2.  Edit `includes/config.php` with your new MySQL password.
3.  **Important:** Edit `initiate_payment.php` and re-enable the production M-Pesa code.

### Step 7: Set Up the Scheduled Cleanup Task (Cron Job)
1.  Open the cron table: `crontab -e`
2.  Add the following line to run the script every 5 minutes:
    ```
    */5 * * * * /usr/bin/php /var/www/html/sync_data_usage.php
    ```

### Step 8: Re-Configure MikroTik for Production
This is a critical security step.

1.  **Update Walled Garden:** In `IP > Hotspot > Walled Garden`, remove your phone's local IP and add the **public IP of your DigitalOcean Droplet**.
2.  **Update Redirect:** In `hotspot/login.html`, change the URL to your Droplet's IP:
    `window.location.href = "http://YOUR_DROPLET_PUBLIC_IP/index.php";`
3.  **SECURE THE MIKROTIK API:**
    *   Go to **IP > Firewall > Filter Rules** and add a new rule.
    *   **Chain:** `input`, **Protocol:** `tcp`, **Dst. Port:** `8728` (or your API port).
    *   **Src. Address:** **Enter your Droplet's public IP address.**
    *   **Action:** `accept`
    *   **IMPORTANT:** Place this rule **before** any general `drop` rules. Only allow access from your Droplet's IP.