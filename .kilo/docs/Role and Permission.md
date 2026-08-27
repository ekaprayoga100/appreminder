# Role & Permission
## App Reminder

## User Roles

| Role | Description | Color |
|---|---|---|
| Admin | Full access — all reminders, users, settings, backup | purple |
| User | Own data only — create/edit/delete own reminders, contacts | blue |

## Permission Matrix

| Action | Admin | User |
|---|---|---|
| Login | ✓ | ✓ |
| Register (create account) | ✓ | ✗ |
| View dashboard | ✓ | ✓ |
| Create reminder | ✓ | ✓ |
| Edit own reminder | ✓ | ✓ |
| Edit others' reminder | ✓ | ✗ |
| Delete own reminder | ✓ | ✓ |
| Delete others' reminder | ✓ | ✗ |
| Send reminder (manual) | ✓ | ✗ |
| View calendar | ✓ | ✓ |
| Manage contacts (email_data) | ✓ | ✓ |
| Import contacts CSV | ✓ | ✓ |
| View own contacts | ✓ | ✓ (own records only) |
| View others' contacts | ✓ | ✗ |
| Manage master data | ✓ | ✗ |
| SMTP Settings | ✓ | ✗ |
| WhatsApp API Settings | ✓ | ✗ |
| Cron Settings & Logs | ✓ | ✗ |
| Cron trigger URL | ✓ (via secret key) | ✗ |
| User Management (list/create/edit/delete) | ✓ | ✗ |
| Change own password | ✓ | ✓ |
| Change others' password | ✓ | ✗ |
| Database backup | ✓ | ✗ |
| Access info.php | ✓ | ✓ |
| Access settings.php | ✓ | ✗ |

## Session & Cookie Configuration

```php
session_set_cookie_params([
    'secure'   => true,       // HTTPS only
    'httponly' => true,       // No JS access
    'samesite' => 'Lax'       // CSRF protection
]);
```

## Auth Checks

```php
// Every protected page calls:
require_once __DIR__ . '/includes/auth.php';
checkLogin(); // redirects to login.php if not logged in

// Admin-gated pages also call:
requireAdmin(); // 403 if role != 'admin'
```

## Permission Enforcement Locations

| File | Permission Check |
|---|---|
| `dashboard.php` | `checkLogin()` + filter by `created_by` (unless admin) |
| `calendar.php` | `checkLogin()` |
| `create_reminder.php` | `checkLogin()` |
| `edit_reminder.php` | `checkLogin()` + ownership check |
| `delete_reminder.php` | `checkLogin()` + ownership check |
| `send_reminder.php` | `checkLogin()` + `requireAdmin()` |
| `data_email.php` | `checkLogin()` |
| `attachment.php` | `checkLogin()` + ownership check |
| `password_change.php` | `checkLogin()` |
| `settings.php` | `checkLogin()` + `requireAdmin()` |
| `info.php` | `checkLogin()` |
| `backup.php` | `checkLogin()` + `requireAdmin()` |
| `cron_trigger.php` | Secret key authentication (no session) |
| `cron_send_reminder.php` | CLI-only (403 on web access) |

## Ownership Logic

```sql
-- For non-admin users, all reminder queries include:
WHERE created_by = :user_id  -- from session

-- For admin users:
-- No created_by filter (see all reminders)
```
