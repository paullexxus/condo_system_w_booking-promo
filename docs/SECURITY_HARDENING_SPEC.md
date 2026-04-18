# SECURITY_HARDENING_SPEC.md
Version: 1.0.0
Date: April 18, 2026
Phase: 5 (Final Refinement)

## 1. Overview
This document specifies the defense-grade security architecture of the Condo Booking System. The system implements a layered defense-in-depth model across the database, session, and API layers.

## 2. Architecture Layers

### 2.1 Database Layer (Phase 1)
- **Immutable Audit Logs**: Enforced via MySQL triggers (`BEFORE UPDATE`, `BEFORE DELETE`).
- **Structured Tracking**: Log entries include `from_status` and `to_status` for state transition analytics.

### 2.2 Core Security Utilities (Phase 2 & 4 Upgrade)
- **Payload-Aware Idempotency**:
  - Uses SHA-256 hashing of request bodies for `POST`, `PUT`, and `DELETE`.
  - Prevents "Key-Replay Tampering" where a key is reused with different data.
- **State-Transition Throttling**:
  - Rate limiting logic only logs "Lockout" events in the system log when the threshold is first breached.
  - API returns `cooldown_remaining` seconds to the client.
- **Magic Byte Validation**: Uploads are validated against hex file signatures (JPG, PNG, PDF).

### 2.3 Session & Authentication (Phase 3 Upgrade)
- **Absolute Inactivity Timeout**: Strict 30-minute window enforced server-side.
- **Activity Heartbeat**: `last_activity` timestamp updated on every valid request to allow continuous usage while active.
- **Fingerprinting**: Sessions bound to IPv4 subnets (/24) and User-Agent strings.

## 3. System Constraints
- **Session Lifespan**: Max 30 mins of inactivity.
- **Idempotency Window**: 10 minutes per key.
- **Throttle Window**: Rolling 60-second windows (defaults).

## 4. Verification Procedures
1. **Timeout Test**: Set `$_SESSION['last_activity']` to `time() - 2000` and refresh.
2. **Tamper Test**: Use same `X-Idempotency-Key` with different POST data.
3. **Trigger Test**: Attempt `DELETE FROM audit_logs`.
