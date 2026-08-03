# Transport Monitoring — Web Admin

A web-based control panel for a public-transport (cooperative bus) tracking system.
Cooperative staff use it to manage their buses and routes, watch vehicles move on a
live map, review passenger reports, and set schedules. It shares one online database
(Supabase) with the companion **Android app**, where drivers share their location and
passengers track buses.

The system is **multi-tenant**: one platform can host many transport cooperatives, and
each one sees only its own data.

> **This guide is written for non-technical users.** Follow it top to bottom and you'll
> have the admin panel running on your own computer. You do **not** need to be a
> programmer.

---

## What it can do (feature overview)

- **Two kinds of admin**
  - **Org admin** — runs one cooperative's daily operations.
  - **Super admin** — runs the whole platform: creates cooperatives and their admins.
- **Live map** of buses currently on a trip, colour-coded by how fresh each position is.
- **Dashboard** with at-a-glance numbers for the cooperative.
- **Manage** vehicles, drivers, terminals, and routes (including drawing a route on a map).
- **Schedules** and driver/vehicle **assignments**.
- **Trip history** with a map of where each bus went.
- **Passenger reports** with photo/video evidence and an option for **anonymous** reports.
- **Branding** — each cooperative sets its own name, **logo**, and colours (which also
  re-skin the mobile app), pins its region on a map, and sets how long tracking data is kept.
- **Onboarding wizard** to add a new cooperative in one step.
- **My Account** to change your name and password.

---

## Do I need XAMPP? (Short answer: no, not really)

This panel does **not** use a web server like Apache, and it does **not** use MySQL.
The only requirement is **PHP version 8.2 or newer, with PostgreSQL support turned on**.

XAMPP is simply the easiest way to install PHP on Windows, so the steps below use it —
**but you will never start Apache or MySQL.** If you already have PHP 8.2+ with the
`pdo_pgsql` extension, you can skip installing XAMPP.

---

# Part 1 — Install the tools (one-time)

You need two things: **PHP** and **Composer**. Do this once.

### 1a. Install PHP (using XAMPP, just for the PHP part)

1. Go to **https://www.apachefriends.org/** and download **XAMPP for Windows**.
2. Run the installer and click **Next** through it (the defaults are fine). It installs
   to `C:\xampp`. You can **untick** "Apache" and "MySQL" on the components screen if you
   like — we don't use them — but leaving them on is harmless as long as you never start them.
3. When it finishes, you can **close XAMPP without starting anything.**

**Now turn on PostgreSQL support in PHP:**

4. Open the folder `C:\xampp\php`.
5. Right-click the file named **`php.ini`** → **Open with** → **Notepad**.
6. Press **Ctrl+F**, search for `;extension=pgsql`. You'll find two nearby lines:
   ```
   ;extension=pgsql
   ;extension=pdo_pgsql
   ```
7. **Delete the semicolon (`;`) at the start of each** so they read:
   ```
   extension=pgsql
   extension=pdo_pgsql
   ```
8. **Save** the file (Ctrl+S) and close Notepad.

**Now make Windows able to find PHP from any folder:**

9. Press the **Start** button, type **environment variables**, and open
   **"Edit the system environment variables."**
10. Click the **Environment Variables…** button.
11. In the lower box ("System variables"), click the row named **Path**, then **Edit…**.
12. Click **New**, type `C:\xampp\php`, then click **OK** on every window to close them.

**Check it worked:**

13. Press **Start**, type **PowerShell**, and open it (a blue/black text window).
14. Type this and press Enter:
    ```powershell
    php -v
    ```
    ✅ You should see something like `PHP 8.2.x`. If it says "not recognized," redo steps 9–12
    and open a **new** PowerShell window.
15. Also check PostgreSQL support:
    ```powershell
    php -m
    ```
    ✅ In the list you should see **pgsql** and **pdo_pgsql**. If not, redo steps 4–8.

### 1b. Install Composer (PHP's package installer)

1. Go to **https://getcomposer.org/download/** and download **Composer-Setup.exe**.
2. Run it. When it asks for the **PHP command line**, point it to:
   ```
   C:\xampp\php\php.exe
   ```
3. Click **Next** through the rest (defaults are fine) and **Finish**.
4. Open a **new** PowerShell window and check:
   ```powershell
   composer -V
   ```
   ✅ You should see a Composer version number.

> **On a Mac?** Install [Homebrew](https://brew.sh), then run `brew install php composer`
> (Mac PHP already includes PostgreSQL support). Then continue at Part 2.

---

# Part 2 — Get the project files

You have two options — **Option A is the simplest.**

### Option A — Download as a ZIP (no extra tools)

1. Open **https://github.com/SEPrimo19/Transport-Monitoring** in your browser (sign in to
   GitHub; you must have been given access).
2. Click the green **`< > Code`** button → **Download ZIP**.
3. Find the downloaded ZIP, **right-click → Extract All** to a simple location such as
   `C:\Transport-Monitoring`.

### Option B — Clone with Git (if you know Git)

```powershell
git clone https://github.com/SEPrimo19/Transport-Monitoring.git
```

Either way, you now have a **project folder**. Remember where it is.

---

# Part 3 — Set it up

### 3a. Open a terminal *inside* the project folder

1. Open the project folder in File Explorer (e.g. `C:\Transport-Monitoring`).
2. Click the **address bar** at the top, type `powershell`, and press **Enter**.
   ✅ A PowerShell window opens already pointing at the project folder.

### 3b. Install the project's dependencies

```powershell
composer install
```

This downloads the code libraries the panel needs. It takes a minute or two.
✅ It finishes without red error text.

### 3c. Create your settings file

The panel needs some secret connection settings that are **not** included in the download
(for security). **Ask your team lead** for them — they'll send you the values.

1. Make a copy of the example settings file:
   ```powershell
   copy .env.example .env
   ```
2. Open the new **`.env`** file (right-click → Open with → Notepad) and fill in the values
   your team lead gave you:
   ```
   SUPABASE_URL=...
   SUPABASE_ANON_KEY=...
   SUPABASE_JWT_SECRET=...

   DB_CONNECTION=pgsql
   DB_HOST=...
   DB_PORT=5432
   DB_DATABASE=postgres
   DB_USERNAME=...
   DB_PASSWORD=...
   DB_SSLMODE=require
   ```
   Leave everything else as it is. **Save** the file.

### 3d. Generate a security key

```powershell
php artisan key:generate
```
✅ You'll see "Application key set successfully."

> ⚠️ **Do NOT run `php artisan migrate`.** The database already exists online and is
> shared — this app only reads and writes existing data.

---

# Part 4 — Run it

```powershell
php artisan serve
```

✅ You'll see: `Server running on [http://127.0.0.1:8000]`.

1. Open your web browser and go to **http://127.0.0.1:8000**.
2. Sign in with the **admin email and password your team lead created for you.**
   (Passenger/driver accounts from the mobile app cannot sign in here — the panel is
   admin-only.)

**To stop the server**, go back to the PowerShell window and press **Ctrl+C**.
**To start it again later**, repeat step 3a (open a terminal in the folder) then
`php artisan serve`.

---

## The mobile app

The driver/passenger app is a separate **Android APK** your team lead will share. Install
it on an Android phone; it connects to the same system. You can sign up as a passenger
right in the app.

## If something goes wrong

- **`php` or `composer` "is not recognized"** — the tool isn't on your PATH. Redo Part 1
  (PHP: steps 9–12) and always open a **new** PowerShell window afterward.
- **`could not find driver` when you open a page** — PostgreSQL support isn't on. Redo
  Part 1, steps 4–8, then restart the server.
- **"This account is not permitted in the admin console"** — you signed in with a
  passenger/driver account. Ask your team lead for an **admin** account.
- **The live map is empty** — that's normal when no bus is currently on a trip; it only
  shows active trips.
- **Port 8000 is busy** — run `php artisan serve --port=8080` and use
  `http://127.0.0.1:8080`.
- **Login page won't load at all** — make sure `php artisan serve` is still running in the
  PowerShell window, and that you filled in the `.env` values correctly.

## Found a bug? Please report it

Note these five things and send them to the team lead (a screenshot helps a lot):
1. The page or web address where it happened.
2. Your role (org admin / super admin).
3. What you did (the steps).
4. What you expected to happen.
5. What actually happened.
