BookIT — Renter / Host Flows and Host Verification (Anti‑Scam) Reference

Purpose
- Reference document mapping the desired real booking flows (renter & host), host registration + verification, and recommended anti-scam measures. Use this as the single-source-of-truth for implementing and auditing BookIT behavior.

1) Renter (Guest) Flow — Detailed (A → G) and mapping to code

Below is the guest flow you supplied (A–G). For each step I list the current implementation and any gaps.

A. Search → Browse → Filter
- What the guest does:
  - Type a location (e.g. Tagaytay), choose dates, see available units.
  - Filter by price, amenities, dates, "entire place / shared room", ratings.
- Implemented in BookIT:
  - Files: `public/browse_units.php`, `includes/functions.php::getAllUnits()`, `renter/reserve_unit.php`, `assets/js/renter/reserve_unit.js`.
  - The system supports branch/location selection and date-based availability (`getAvailableUnits()` in `includes/functions.php`). `getAllUnits()` accepts filters in code but the public UI currently exposes branch/unit_type and basic search. JavaScript helps with date inputs and min/max handling.
- Gaps:
  - A unified "destination" free-text search (city/place) and rating filter UI are not fully implemented.
  - Amenity filters (Smart TV, pool, entire apartment) are available in code via `getBranchAmenities()` but need UI wiring on `browse_units.php` and `reserve_unit.php` to support multi-filter queries.

B. Check Listing Details
- What the guest sees:
  - Full description, house rules, amenities, reviews, host profile, exact cancellation policy, total price with cleaning & service fees.
- Implemented in BookIT:
  - Files: `public/browse_units.php` (unit cards and modal), `renter/reserve_unit.php` (booking form & details), `includes/functions.php::getBranchAmenities()`, `renter/submit_review.php` (review submissions).
- Gaps:
  - Line-item breakdown for `cleaning_fee` and `service_fee` is not currently stored/displayed (no `cleaning_fee`/`service_fee` columns found). I recommended DB fields in the document and can add them if you want.

C. Set Dates + Check Availability
- What the guest does:
  - Selects check-in/check-out dates; availability is real-time — blocked dates cannot be selected.
- Implemented in BookIT:
  - Files: `renter/reserve_unit.php`, `includes/functions.php::getAvailableUnits()`, frontend date min/max handled in `assets/js/renter/reserve_unit.js`.
  - `getAvailableUnits()` excludes units with overlapping reservations in statuses like `confirmed` and `checked_in`.
- Gaps:
  - Calendar UI (date-picker showing blocked dates on listing page) is limited — current pages use basic date inputs and server-side checks. We can add a calendar widget and endpoint that returns blocked ranges.

D. Reservation Type (Instant vs Request)
- What the guest chooses:
  - Instant Booking: auto-confirm at booking time.
  - Request to Book: host must approve (24h window typical).
- Implemented in BookIT:
  - Files: `renter/reserve_unit.php`, `includes/functions.php::createReservation()`, `host/reservations.php`, `modules/process_reservation.php`.
  - `createReservation()` creates `awaiting_approval` (request path). Instant attempts to auto-approve and redirect to checkout.
- Gaps:
  - Business rules for 24-hour auto-expiry of requests and host auto-decline are not fully automated (could add background job to auto-expire requests after 24h).

E. Payment
- What the guest does:
  - Pays with debit/credit, GCash, PayPal (BookIT holds funds until check-in).
- Implemented in BookIT:
  - Files: `renter/checkout.php`, `renter/payment.php`, `renter/payment_gateway.php`, `renter/payment_success.php`, `includes/functions.php::processPayment()`, `includes/api/payment/process_payment.php`, `config/paymongo.php`.
  - Supported payment methods in UI: `gcash`, `paymaya`, `bank_transfer`, `credit_card` (integration hooks present; some flows simulate gateway responses). Session-based `$_SESSION['pending_payment']` is used to manage payment state.
- Gaps / notes:
  - PayPal integration is not explicitly present; we can add it if required. The code supports adding gateway integrations.
  - The "BookIT holds funds until check-in" behavior: payouts are released 24h after check-in by `scripts/release_payouts.php`. That effectively implements a hold period but platform-fee accounting may be incomplete (I suggested adding `platform_fee` to `payments`).

F. Confirmation + Messaging
- After confirmation the guest should receive itinerary, be able to message host, and receive check-in instructions.
- Implemented in BookIT:
  - Files: `includes/email_integration.php` (email templates), `includes/functions.php::sendNotification()` (DB notifications), `renter/payment_success.php` sends confirmation notifications.
  - Messaging UI is not a full real-time chat, but hosts and renters can be notified; there are notification modules (`modules/notifications.php`).
- Gaps:
  - A dedicated guest-host messaging inbox (chat) may be limited; current system uses notifications and email. We can add a messaging module if needed.

G. Stay + Check-out
- What happens:
  - Guest checks in (methods: meet host, smart lock, keybox), host can mark checked-in; during stay guest can open a "Resolution Center" for issues; after check-out both leave reviews.
- Implemented in BookIT:
  - Files: `host/reservations.php`, `modules/process_reservation.php`, `modules/api/update_status.php`, `renter/submit_review.php`.
  - Hosts can change reservation status (approve, confirm, checkin, checkout). Reviews allowed within 14 days after check-out.
- Gaps:
  - Resolution Center (dispute UI) is not a fully separate module; dispute handling and refunds need admin workflows (some pieces like notifications and payment records exist and can be extended).

Summary — quick mapping
- Search & Filters: `public/browse_units.php`, `includes/functions.php::getAllUnits()`, JS helper `assets/js/renter/reserve_unit.js`
- Listing details: `public/browse_units.php`, `renter/reserve_unit.php` (modals)
- Availability & dates: `renter/reserve_unit.php`, `includes/functions.php::getAvailableUnits()`
- Booking types: `renter/reserve_unit.php`, `includes/functions.php::createReservation()`
- Checkout & Payment: `renter/checkout.php`, `renter/payment.php`, `renter/payment_gateway.php`, `renter/payment_success.php`, `includes/functions.php::processPayment()`
- Confirmation & Messaging: `includes/email_integration.php`, notifications in `includes/functions.php`
- Check-in / Stay / Check-out: `host/reservations.php`, `modules/process_reservation.php`, `modules/api/update_status.php`
- Payouts hold/release: `scripts/release_payouts.php`
- Reviews: `renter/submit_review.php` and listing review logic
If you want, I can now:
- Add `cleaning_fee` and `service_fee` fields to the DB and show them in listing + checkout (I can patch schema and UI files). 
- Add destination free-text search and amenity filters on `browse_units.php` (wire filters to `getAllUnits()`).
- Add a calendar widget that shows blocked dates on listing pages.
- Implement an automated 24-hour expiry for pending booking requests.
Tell me which of those you'd like me to implement next (I can start with DB + UI for cleaning/service fees or the filters/search improvements). 
2) Host Registration + Verification Flow (recommended and how to integrate)

Goal: Ensure hosts are legitimate before they can list units and receive payouts.

a) Registration
- Endpoint: existing public `register.php` (or `create_admin.php` variant for manager). New host registration should capture:
  - Full name, email, phone, password, role = 'host'
  - Optional: business name, business tax ID, payout method details (bank account / GCash / PayPal), branch info
- Immediately set `verification_status = 'pending'` for new host accounts.

b) Verification steps (multi-layer recommended)
- Step 1: Email verification (already common). Ensure `is_active` only after email confirmed or set a flag `email_verified`.
- Step 2: Phone verification (OTP SMS) — implement OTP via SMS provider (e.g., Twilio, MessageBird) and store `phone_verified`.
- Step 3: Government ID upload + basic OCR check — allow uploads (jpg/png/pdf) and extract name & ID number via OCR provider (or manual review queue). Store paths in `verification_documents` and `verification_notes`.
- Step 4: Liveness check (selfie + face-match vs ID) — use third-party KYC (Jumio, Onfido, Veriff) for automated checks; store `verification_score` and `verification_report`.
- Step 5: Bank account verification (small deposit micro-verification) — required before payouts (or at least verify payout destination).
- Step 6: Manual review & fraud analyst approval for suspicious cases. Admin UI to review documents and set `verification_status = 'verified' | 'rejected' | 'needs_info'`.

c) When host can list / receive bookings
- Allow creating unit listings in a restricted state (e.g., `is_active = 0`) until host is verified. Or allow listings but disable instant booking and payouts until `verified`.

Files to add/update
- New include: `includes/HostVerification.php` (helper functions: queueVerification($host_id), getVerificationStatus($host_id), acceptVerification, rejectVerification)
- Admin UI: `admin/verify_hosts.php` / `host_verification.php` for manual review.
- Update registration flow: in `register.php` or controller, set `verification_status` and queue any required checks.

Suggested DB changes (migration SQL examples)
- Add host verification fields to `users` table:
  ALTER TABLE users
    ADD COLUMN verification_status ENUM('unverified','pending','needs_info','verified','rejected') DEFAULT 'unverified',
    ADD COLUMN verification_documents JSON DEFAULT NULL,
    ADD COLUMN verification_score FLOAT DEFAULT NULL,
    ADD COLUMN verification_notes TEXT DEFAULT NULL,
    ADD COLUMN email_verified TINYINT(1) DEFAULT 0,
    ADD COLUMN phone_verified TINYINT(1) DEFAULT 0;

- Add payout/account fields (if not present):
  ALTER TABLE users ADD COLUMN payout_method VARCHAR(50) DEFAULT NULL, ADD COLUMN payout_details JSON DEFAULT NULL;

- Add fees fields (related request earlier):
  ALTER TABLE units ADD COLUMN cleaning_fee DECIMAL(10,2) DEFAULT 0, ADD COLUMN service_fee DECIMAL(10,2) DEFAULT 0;

- Add payment fee accounting:
  ALTER TABLE payments ADD COLUMN platform_fee DECIMAL(10,2) DEFAULT 0, ADD COLUMN payout_released TINYINT(1) DEFAULT 0;

3) Anti-scam Checks — Practical Options & Where to Integrate

(1) Identity verification (KYC)
- Why: prevent fake hosts and stolen identity listings.
- How: require government ID upload + perform OCR and optional automated face-match.
- Integrate: at registration or before first listing/payout. Use `includes/HostVerification.php` and `admin/verify_hosts.php` for manual overrides.

(2) Phone verification (OTP)
- Why: reduces fake accounts and increases contactability.
- How: SMS OTP during registration (store `phone_verified` true), fallback to voice OTP.
- Integrate: `register.php` flow and `includes/functions.php` helper to send/verify OTP.

(3) Email verification
- Why: basic hygiene; already present but ensure enforced for hosts.
- How: verification link + token.

(4) Document checks & liveness
- Why: high assurance for hosts who will receive payouts.
- How: third-party KYC providers (Onfido/Veriff/Jumio). These return a verification score and report.
- Integrate: send host to KYC flow or upload files for API submission. Store `verification_report`.

(5) Reputation / behavior signals
- Why: ongoing trust monitoring.
- Signals to compute: account age, previous cancellations, dispute counts, number of listings, reviews average, chargeback rate.
- Integrate: add `verification_score` and `fraud_score` rollups in `users` and update periodically.

(6) Manual review queue
- Why: automated checks catch many cases but manual review is needed for edge cases.
- How: admin dashboard shows pending verifications with uploaded docs and quick actions to Accept/Reject/Request more info.

(7) Payment controls
- Why: limit scammer ability to drain funds.
- Controls: hold payouts for unverified hosts, require micro-deposit verification, allow payout only after `verification_status = 'verified'` and at least one successful hosted stay.
- Integrate: `scripts/release_payouts.php` should only release when host `verification_status='verified'` and possibly after anti-fraud checks.

(8) Rate-limits & device/fingerprint checks
- Why: prevent mass fake registrations.
- How: throttle registrations per IP, require CAPTCHA on registration, log device/browser fingerprints (simple fingerprinting). Use `ImageFingerprinting.php` or other existing utilities where present.

(9) Listing validation and content review
- Why: prevent scams that list nonexistent properties.
- How: require proof (property deed, host ownership docs) for high-value listings, or require in-person verification for high-risk branches.

(10) Dispute and refund workflow
- Why: important when scam or damage claims arise.
- How: create admin workflows to freeze payouts, investigate, and refund guests when necessary.

4) Implementation Checklist (practical incremental plan)

Phase A — Low-effort, high-impact (1–2 days)
- [ ] Enforce email & phone verification for hosts (OTP). Update registration flow. (Files: `register.php`, `includes/functions.php`)
- [ ] Add `verification_status` and basic columns to `users` (DB migration). Mark new hosts as `pending`.
- [ ] Prevent payout release to hosts with `verification_status != 'verified'` (update `scripts/release_payouts.php`).

Phase B — Medium effort / important (1–2 weeks)
- [ ] ID upload + admin manual review UI (`admin/verify_hosts.php`). Store documents under `uploads/host_docs/`.
- [ ] Add `cleaning_fee` and `service_fee` fields to `units` and show breakdown in `public/browse_units.php`, `renter/reserve_unit.php`, `renter/checkout.php`.
- [ ] Add platform fee accounting in `payments` (`platform_fee`) and show breakdown in checkout.

Phase C — Higher effort / stronger assurance (2–4+ weeks)
- [ ] Integrate a KYC provider (Onfido/Veriff/Jumio) for automated ID + liveness checks. Write `includes/kyc_integration.php`.
- [ ] Implement micro-deposit verification for bank payouts.
- [ ] Reputation/fraud scoring engine (job to compute metrics and flag suspicious accounts).

Phase D — Monitoring & Ops
- [ ] Add fraud monitoring alerts (email/slack) when high-risk signals trigger (sudden many listings, many chargebacks, suspicious IPs).
- [ ] Instrument admin dashboards to review suspicious activity quickly.

5) Example code snippets / SQL (quick copy-paste)

-- Add fields to users (verification)
ALTER TABLE users
  ADD COLUMN verification_status ENUM('unverified','pending','needs_info','verified','rejected') DEFAULT 'unverified',
  ADD COLUMN verification_documents JSON DEFAULT NULL,
  ADD COLUMN verification_score FLOAT DEFAULT NULL,
  ADD COLUMN verification_notes TEXT DEFAULT NULL,
  ADD COLUMN email_verified TINYINT(1) DEFAULT 0,
  ADD COLUMN phone_verified TINYINT(1) DEFAULT 0;

-- Add fees to units
ALTER TABLE units
  ADD COLUMN cleaning_fee DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN service_fee DECIMAL(10,2) DEFAULT 0;

-- Payment accounting
ALTER TABLE payments
  ADD COLUMN platform_fee DECIMAL(10,2) DEFAULT 0,
  ADD COLUMN payout_released TINYINT(1) DEFAULT 0;

6) Where to hook verification in code (suggested integration points)
- Registration: `register.php` → set `verification_status = 'pending'`, create OTP verification step.
- Host Dashboard: `host/unit_management.php` → if `verification_status != 'verified'` show banner "Complete verification to receive payouts / enable instant booking".
- Payout script: `scripts/release_payouts.php` → check host `verification_status` as precondition.
- Listing activation: when host adds a unit, allow `is_active = 0` until verification; or allow active but block `instant_booking` and payouts.

7) Next steps I can implement for you (pick one)
- Implement DB migrations and code changes to add `cleaning_fee` + `service_fee` and show breakdown in UI (I can patch `includes/functions.php`, `public/browse_units.php`, `renter/reserve_unit.php`, `renter/checkout.php`).
- Add `verification_status` column and implement phone OTP verification on host registration (I can add helper + small UI + sample OTP provider hooks; we can mock SMS provider locally).
- Scaffold admin host verification UI (`admin/verify_hosts.php`) and server-side endpoints to Accept/Reject verification.
- Create a lightweight pre-arrival scheduler script that sends check-in instructions 24h before check-in using `includes/email_integration.php`.

Tell me which of the Next steps you want me to start with and I will update the todo list and begin implementing it.

---
Notes
- This doc uses BookIT terminology, not Airbnb — confirmed.
- I kept implementation suggestions conservative and incremental so we can merge safely into the existing codebase.

