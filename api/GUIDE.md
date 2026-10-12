# Secure Portal guide

## Start the portal

### Laragon

1. Start **Apache** in Laragon.
2. Make sure this project is located at `C:\laragon\www\secure-login`.
3. Open [http://localhost/secure-login/](http://localhost/secure-login/) to go to your workspace or sign-in page. Use [http://localhost/secure-login/register.php](http://localhost/secure-login/register.php) to create an account.

### PHP development server

Open PowerShell in the project directory and run:

```powershell
C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe -S 127.0.0.1:8000 -t C:\laragon\www\secure-login C:\laragon\www\secure-login\router.php
```

Keep that PowerShell window open, then visit [http://127.0.0.1:8000/](http://127.0.0.1:8000/). The development server is intended for local testing, not public hosting.

## Create an account

1. Enter a username using 3–32 letters, numbers, dots, underscores, or hyphens.
2. Enter a valid email address.
3. Choose a unique password with at least 12 characters. The strength indicator is guidance; it does not send your password anywhere.
4. Submit the form. Sign in with the username and password you chose.

Administrator access is never selectable on the public registration form. On an empty, new database, the first account is assigned the administrator role and later accounts are standard users. Existing database roles are preserved.

## Sign in

1. Enter the account username and password.
2. If two-factor authentication is enabled, the portal displays your account role and asks you to enter the current six-digit code from your authenticator app.
3. After the code is verified, the portal reads your current role from the database and opens the matching administrator or member workspace. The OTP verifies access to your account; it does not grant or change roles.

If a code does not work, wait for the next one and check that the phone's date and time are set automatically. Codes are accepted for the current 30-second interval, with a one-interval clock-skew allowance in either direction; previously accepted codes are rejected.

## Workspace and roles

Every signed-in member can see their account role, security status, and their own two-factor settings.

- **Administrator:** sees the administrator workspace, account directory, and database backup tool.
- **Standard user:** sees the member workspace. Administrator tools and the account directory are not available.

Role checks are performed on the server. Hiding a link in the interface is not the only access control.

## Set up two-factor authentication

1. Sign in and open **Security** in the top navigation or choose the security card on your workspace.
2. In your authenticator app, choose **Enter a setup key** or **Add manually**. Use the account name and private setup key displayed by the portal. The setup key can be copied or regenerated before enrollment if you lose it.
3. Set the app to **Time-based (TOTP)**, **6 digits**, and a **30-second interval**. The portal supports manual setup; it does not transmit the secret to an external QR-code service.
4. Return to the portal and enter the app's current six-digit code to confirm enrollment.
5. The code used to confirm setup is consumed. At future sign-ins, enter a new, unused current code after your password. A code can only be used once.

Treat the setup key like a password. Do not share it. This sample portal does not currently offer recovery codes or self-service 2FA reset, so keep access to the enrolled authenticator.

## Administrator database backup

1. Sign in using an administrator account.
2. Open **Create database backup** from the administrator workspace.
3. A consistent SQLite snapshot is saved in `C:\laragon\secure-login-backups`, outside the public web directory.
4. Make a protected off-device copy according to your organization's backup and retention policy.

If backup creation fails, check that the PHP process can create and write to that directory, then inspect the PHP error log. Backup files are not downloadable through the portal.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| Browser cannot connect | Start Apache in Laragon, or restart the PHP development server in PowerShell. |
| Database unavailable | Confirm `database.sqlite` exists and is writable by the PHP process; check that PDO SQLite is enabled. |
| Sign-in rejected | Use the account username, check Caps Lock, and verify the password. |
| 2FA code rejected | Wait for a fresh code; check the phone's automatic date/time setting. |
| Backup cannot be created | Confirm the server can create the private backup folder and inspect PHP logs. |
| Page shows an error | Check the PHP error log. The browser intentionally does not display database or server exception details. |

## Deployment notes

This is a local demonstration application, not a production identity provider. Before exposing it to a network, set `APP_ENV=production` and configure a strong, unique `APP_PEPPER` of at least 32 characters in the PHP process environment. Preserve the same pepper for the lifetime of the account database: changing it prevents existing passwords from verifying. You may set `APP_BACKUP_DIR` to an absolute, private directory path. Also serve only over HTTPS, add rate limiting and account recovery procedures, set operational backup retention, and arrange regular security and dependency reviews. Do not expose the SQLite file or backup directory through the web server.
