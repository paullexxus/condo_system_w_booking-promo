# BookIT — Context Diagram

This document explains the system context for the BookIT web application and how the main actors and external services interact with it.

## Actors

- Renter (web browser): Browse units, register/login, reserve units, make payments, upload profile picture.
- Manager (web browser): Manage units, view bookings, accept/reject reservations.
- Admin (web browser): Manage branches, admin users, system settings.

## System (BookIT Web App)

- PHP application running on WAMP (Windows).
- Core modules seen in the codebase:
  - Auth & Sessions (`includes/auth.php`) — user login/register, sessions.
  - Reservations/Units/Branches modules (`modules/*`) — booking logic and management.
  - Payments (`modules/payments.php`) — processes payments via external gateway.
  - Notifications/Email — sends transactional emails via SMTP.
- Persistent storage: MySQL database (see `config/db.php`).
- Local file storage for uploads under `uploads/` (profile pictures, etc.).

## External Systems / Integrations

- MySQL database (local, `condo_rental_reservation_db`) — user, reservations, payments, branches, etc.
- SMTP Email (Gmail SMTP) — used for sending registration confirmations, receipts, notifications (see `config/email.php`).
- Google OAuth — optional OAuth sign-in (see `config/OAuth.php`).
- Payment gateway — third-party payment processor invoked by the Payments module.
- File storage — local filesystem under `uploads/` for profile pictures.

## High-level Data Flows

- Users (Renter/Manager/Admin) <-> Browser <-> BookIT Web App (HTTP/HTTPS)
- App <-> MySQL DB: read/write for users, reservations, payments, branches
- App -> SMTP: send emails (registration, receipts, reminders)
- App -> Google OAuth: perform OAuth flow for sign-in
- App -> Payment Gateway: process payments (credit card/bank transfer)
- App -> File Storage: store uploaded profile pictures under `uploads/profile_pictures`

## Security & Trust Boundaries

- The database and file storage reside within the same server environment (WAMP). Consider moving file storage to a dedicated storage service and DB to a managed instance for production.
- Credentials in `config/email.php` and `config/OAuth.php` should be kept out of version control and stored in environment variables or a secrets manager.
- Use HTTPS in production and ensure SMTP credentials use app-specific passwords or secured secrets.

## Assumptions

- The application runs behind HTTP(S) on the WAMP server.
- Payment gateway credentials/API client are configured elsewhere (not shown in `modules/payments.php`).
- OAuth credentials shown are placeholders; handle them securely.

## How to render the PlantUML diagram

1. Install PlantUML (or use the VS Code PlantUML extension).
2. From PowerShell in the repo root, run (requires Java and plantuml.jar):

```powershell
# from repo root
java -jar path\to\plantuml.jar docs\context_diagram.puml
```

That will generate `docs/context_diagram.png` beside the `.puml` file.

Alternatively, install the PlantUML VS Code extension and open `docs/context_diagram.puml` to preview.

### Portrait layout

I updated the PlantUML source to use a portrait (top-to-bottom) layout so the diagram is taller than it is wide and reads naturally from actors at the top down to external services at the bottom. Open `docs/context_diagram.puml` in the PlantUML preview or render to PNG/SVG to see the portrait layout.

If you want a different size or higher DPI, use:

```powershell
# increase dpi and render to svg
java -jar path\\to\\plantuml.jar -dpi 200 -tsvg docs\\context_diagram.puml
```

You can also tweak the `top to bottom direction` or skin parameters directly in the `.puml` file.

## ASCII fallback (quick view)

[Renter] ---> [BookIT Web App] <--- [Manager]
                        |
                        v
                     [MySQL DB]
                        |
         [SMTP Email]  [Google OAuth]  [Payment Gateway]
                        |
                    [File Storage]

## Next steps / Suggestions

- Move sensitive config values into environment variables.
- Add a network-level diagram if you want separate servers (web, DB, file store).
- If you want, I can also generate an SVG/PNG here and commit it to `docs/`.
