# Captive Portal System for Windows (PHP, MySQL, TailwindCSS)

This project is a complete captive portal system designed to run on a local Windows PC using XAMPP. It's built for a Wi-Fi business in Kenya, integrating with Safaricom's M-Pesa Daraja API for payments.

## Features
- **Captive Portal Logic**: Redirects unauthorized Wi-Fi users to a login page.
- **MAC Address Detection**: Captures device MAC addresses by querying the Windows ARP table.
- **User Authentication**: Simple login/registration system using a phone number.
- **Data Bundles**: Users can purchase data bundles.
- **M-Pesa Integration**: Securely handles payments via M-Pesa STK Push.
- **Admin Dashboard**: View connected devices, data usage, and total earnings.
- **Responsive UI**: Styled with TailwindCSS for a clean interface on any device.

---

## 1. Prerequisites

- **Windows PC**: A dedicated PC that will act as the server.
- **XAMPP**: A free and easy-to-install Apache distribution containing MariaDB (MySQL), PHP, and Perl. [Download XAMPP](https://www.apachefriends.org/index.html).
- **Wi-Fi Router**: A router that you have administrative access to, which supports changing the Gateway and DNS settings.
- **Safaricom Developer Account**: To get M-Pesa Daraja API credentials. [Safaricom Developers](https://developer.safaricom.co.ke/).
- **Ngrok (for testing)**: A tool to expose your local server to the internet, which is necessary for testing the M-Pesa callback. [Download Ngrok](https://ngrok.com/download).

---

## 2. Deployment Instructions

### Step 2.1: Install and Configure XAMPP

1.  **Install XAMPP**: Download and install XAMPP to a location like `C:\xampp`.
2.  **Start Services**: Open the **XAMPP Control Panel** and start the **Apache** and **MySQL** modules.
3.  **Place Project Files**: Copy all the project files (`index.php`, `admin/`, `includes/`, etc.) into the `htdocs` directory inside your XAMPP installation folder (e.g., `C:\xampp\htdocs\captiveportal`).

### Step 2.2: Set Up the Database

1.  **Open phpMyAdmin**: In your browser, navigate to `http://localhost/phpmyadmin`.
2.  **Create Database**:
    - Click on the **Databases** tab.
    - Enter `captive_portal` in the "Create database" field and click **Create**.
3.  **Import SQL Schema**:
    - Select the `captive_portal` database from the left-hand menu.
    - Click on the **Import** tab.
    - Click "Choose File" and select the `database.sql` file from this project.
    - Scroll down and click **Go**. This will create all the necessary tables and insert the default data bundles and admin user.

### Step 2.3: Configure the Application

1.  **Open `includes/config.php`**: Open the file `C:\xampp\htdocs\captiveportal\includes\config.php` in a text editor.
2.  **Database Credentials**: The default XAMPP settings are usually correct (`root` user with no password). If you have set a password, update `DB_PASS`.
    ```php
    define('DB_HOST', '127.0.0.1');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // Your MySQL password
    define('DB_NAME', 'captive_portal');
    ```
3.  **M-Pesa Credentials**: Fill in the M-Pesa constants with the credentials you obtained from the Safaricom Developer Portal.
    ```php
    define('MPESA_CONSUMER_KEY', 'YOUR_CONSUMER_KEY');
    define('MPESA_CONSUMER_SECRET', 'YOUR_CONSUMER_SECRET');
    define('MPESA_PASSKEY', 'YOUR_PASSKEY');
    define('MPESA_SHORTCODE', 'YOUR_BUSINESS_SHORTCODE');
    ```
4.  **Server IP**: Set the static IP address that you will assign to your Windows PC (see next section).
    ```php
    define('SERVER_IP', '192.168.1.100'); // Example IP
    ```

---

## 3. Network Configuration

This is the most critical part of the setup. The goal is to force all network traffic from Wi-Fi clients through your PC.

### Step 3.1: Set a Static IP on the Windows PC

1.  Go to **Control Panel > Network and Internet > Network and Sharing Center**.
2.  Click **Change adapter settings**.
3.  Right-click your network adapter (e.g., "Ethernet" or "Wi-Fi") and select **Properties**.
4.  Select **Internet Protocol Version 4 (TCP/IPv4)** and click **Properties**.
5.  Select "Use the following IP address".
    - **IP address**: `192.168.1.100` (This should be outside your router's DHCP range but on the same subnet. This is the IP you set in `config.php`).
    - **Subnet mask**: `255.255.255.0` (Usually this value).
    - **Default gateway**: `192.168.1.1` (This is your router's IP address).
6.  Set the **Preferred DNS server** to your router's IP (`192.168.1.1`) or a public DNS like `8.8.8.8`.
7.  Click **OK** to save.

### Step 3.2: Configure the Wi-Fi Router

1.  **Log in to your router's admin panel** (usually `192.168.1.1`).
2.  **Find the DHCP Server settings**.
3.  Change the **Default Gateway** (or "Router") setting to your PC's static IP address (`192.168.1.100`).
4.  Change the **Primary DNS Server** setting to your PC's static IP address (`192.168.1.100`).
5.  **Save and reboot the router**.

Now, when any device connects to the Wi-Fi, the router will tell it that your PC is the gateway to the internet. All traffic will be directed to your XAMPP server, and `index.php` will act as the entry point.

---

## 4. Testing

### Step 4.1: Testing M-Pesa with Ngrok

Safaricom's API needs a public URL to send the payment confirmation to your `callback.php`.

1.  **Run Ngrok**: Open a command prompt and run the following command to expose your local server's port 80.
    ```sh
    ngrok http 80
    ```
2.  **Get the URL**: Ngrok will give you a public `https` URL (e.g., `https://abcd-efgh.ngrok.io`).
3.  **Update `config.php`**: Change the `MPESA_CALLBACK_URL` to this Ngrok URL.
    ```php
    define('MPESA_CALLBACK_URL', 'https://abcd-efgh.ngrok.io/captiveportal/callback.php');
    ```
4.  **Register URL on Safaricom**: You may need to register this URL on the Safaricom Developer Portal for your app.

### Step 4.2: Final Test

1.  Connect a new device (like a smartphone) to your Wi-Fi network.
2.  The device should automatically be redirected to the login page (`http://192.168.1.100/captiveportal/`).
3.  Register a new account, purchase a bundle, and complete the M-Pesa payment on your phone.
4.  After a successful payment, the `Access Granted` page should appear.

### Admin Access
-   To access the admin panel, navigate to `http://192.168.1.100/captiveportal/admin/`.
-   Default credentials are `admin` / `password`.
