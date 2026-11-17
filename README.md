# RecordDesk — PHP + JSON CRUD

A complete small learning project with separate HTML, CSS, JavaScript, and PHP. No database, Composer, Node.js, or internet connection is needed to run it.

## 1. Run with XAMPP on Windows

1. Install XAMPP with PHP 8.1 or newer.
2. Extract the ZIP. Copy the `simple-crud` folder into `C:\xampp\htdocs\`.
3. Confirm this file exists: `C:\xampp\htdocs\simple-crud\public\index.html`.
4. Open the XAMPP Control Panel and start **Apache**. MySQL is not required.
5. Visit **http://localhost/simple-crud/public/index.html** in your browser. If Apache uses another port, include it, for example `http://localhost:8080/simple-crud/public/index.html`.
6. Sign in using one of the demo accounts below.

You may edit the entire folder in VS Code. Open the page through Apache; VS Code Live Server and opening the HTML file directly cannot execute PHP.

Alternative with PHP on your PATH: open a terminal inside `simple-crud`, run `php -S localhost:8000 -t public`, then visit http://localhost:8000. Serving only `public` also keeps the data outside the web root.

## 2. Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@example.com | Admin123! |
| User | user@example.com | User123! |

Accounts are created automatically on the first API request. Passwords are stored as hashes. Sign in as admin, open **Manage users**, select **Edit**, and change the demo passwords before using real information. There is no public registration; an admin creates accounts.

## 3. Permissions

| Feature | Admin | User |
| --- | --- | --- |
| Sign in / sign out | Yes | Yes |
| Create records | Yes | Yes |
| Read, edit, delete records | All records | Own records only |
| Search visible records | Yes | Yes |
| Create, read, edit, delete accounts | Yes | No |
| Change roles / reset user passwords | Yes | No |

Admins cannot delete or demote their own account. Deleting another account also deletes that account's records, after confirmation. Editing a record as admin preserves the original owner.

## 4. Folder structure

```text
simple-crud/
  README.md                Setup, permissions, and explanation
  index.php                Redirects the project root to public/index.html
  public/                  Browser-facing files
    index.html             Login, dashboard, tables, forms
    api.php                PHP authentication and CRUD endpoints
    assets/
      app.js               Fetch requests, events, rendering, search
      style.css            Responsive visual styling
  app/
    store.php              JSON initialization, reading, atomic writing
    .htaccess              Blocks direct Apache access
  data/
    .htaccess              Blocks direct Apache access
    store.json             Generated on first run; users and records
    store.lock             Generated automatically; coordinates writes
```

`store.json` and `store.lock` are deliberately generated at runtime, so the ZIP starts with fresh demo accounts and no test data.

## 5. How the files work together

1. The browser loads `index.html`, `style.css`, and `app.js`.
2. JavaScript calls `api.php?action=session` to get the current user and a CSRF token.
3. The login form sends JSON to PHP using `fetch()`.
4. PHP verifies the password, creates a session, and returns the user's role.
5. JavaScript displays the dashboard and loads records.
6. Creating, editing, and deleting send POST requests to PHP. PHP checks the session, role, ownership, token, and values before changing JSON.
7. PHP holds a file lock across each read/update/write and saves via a temporary file and rename to avoid partial writes.

The application data is held in JSON; authentication sessions use PHP's normal server-side session storage. JavaScript never receives password hashes. UI text uses `textContent` to prevent saved HTML from executing.

## 6. API reference

All endpoints are relative to `public/api.php?action=...`. POST requests require the session cookie, a JSON body, and the `X-CSRF-Token` header obtained from `session` or `login`.

| Action | Method | Purpose |
| --- | --- | --- |
| session | GET | Current user and CSRF token |
| login | POST | Email and password authentication |
| logout | POST | End session |
| records | GET | Visible records |
| save_record | POST | Create with empty id, update with existing id |
| delete_record | POST | Delete by id |
| users | GET | Admin account list |
| save_user | POST | Admin create/update account; blank edit password keeps existing hash |
| delete_user | POST | Admin delete account and its records |

A record contains `id`, `title`, `description`, `status`, `owner_id`, and `updated_at`. Status is Pending, In progress, or Completed. Title is required (up to 120 bytes), description is required (up to 2,000 bytes). User names are required (up to 120 bytes), emails must be valid and unique, and new passwords must contain at least 8 characters (up to 200 bytes).

## 7. Quick practice

1. Sign in as user and create a record titled **Website project**.
2. Change its status to **In progress**; refresh and confirm it remains saved.
3. Sign out and sign in as admin; confirm the same record is visible.
4. Create another user account, sign in as that user, and confirm the first user's record is not visible.
5. Create, edit, search, and delete a record with the new user.
6. Sign in as admin and edit or delete the test account.

## 8. Troubleshooting and scope

- **PHP source appears or login does not work:** use the Apache localhost URL, not Live Server or a file URL.
- **Storage error:** ensure the Apache/PHP process can create and replace files in `data`. Check Apache's error log. Do not manually edit JSON while the app is running.
- **Invalid JSON data:** inspect `data/store.json` for manual editing errors. Do not delete it unless you intend to reset all accounts and records. Back it up first.
- **Missing page:** verify folder nesting after extraction and the Apache port.
- **Forbidden for app or data URLs:** expected; those directories are private.

For a hosted installation, configure the document root to `public` and use HTTPS. The included `.htaccess` files protect the other directories on Apache when overrides are enabled; other servers need equivalent rules. This is a small single-server classroom project. File locking serializes access, and each request reads the entire JSON file. For production or heavy traffic, add login rate limiting, auditing, recovery, and backups, and migrate to a database.
