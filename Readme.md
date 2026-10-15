# NMS Accounting System - Windows Setup

## ⚠️ System Requirements
Before running the installer, ensure you have one of the following local PHP/MySQL servers installed on your computer:
* **XAMPP** (Recommended path: `C:\xampp`) OR
* **Laragon** (Recommended path: `C:\laragon`)

## Installation Instructions
1. Download and run **`NMS_Setup.exe`**.
2. During the installation wizard, select your active local server (**XAMPP** or **Laragon**).
3. The installer will automatically copy your `nms` website folder into your server's public directory (`C:\xampp\htdocs\nms` or `C:\laragon\www\nms`).
4. A desktop shortcut named **"NMS Accounting System"** will be created.
5. Make sure Apache and MySQL are running in your XAMPP/Laragon control panel, and double-click the desktop shortcut to open the login page (`http://localhost/nms/login.php`).