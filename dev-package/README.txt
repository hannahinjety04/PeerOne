Swopblock Development Tools
===========================

This package installs a LOCAL copy of:
  - Central marketplace (SQLite) on http://localhost:8081
  - Client node on http://localhost:8080

Requirements
------------
- Windows 10/11
- Internet only if PHP is not already installed (installer downloads
  official PHP from windows.php.net and verifies SHA-256)

No XAMPP and no MySQL are required for this development package.

Install
-------
1. Unzip this download.
2. Double-click install-dev.bat
3. When finished, open the folder:
     %USERPROFILE%\SwopblockDev

Start
-----
1. Double-click start-central.bat
2. Double-click start-client.bat (separate window)

Admin login (local central)
---------------------------
URL:      http://localhost:8081/admin/login.php
Email:    owner@central.local
Password: changeme123

Notes
-----
- Production cloud central still uses MySQL.
- This package is for developers only.
- Customer nodes use the normal "Install Your Node" download.
