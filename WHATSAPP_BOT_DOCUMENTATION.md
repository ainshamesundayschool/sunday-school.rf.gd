# Sunday School WhatsApp OTP & Password Reset Architecture

This document provides a comprehensive technical reference for the WhatsApp OTP verification system, password recovery flow, and backend API integration. **All future AI agents and developers must read this document before modifying authentication or WhatsApp bot logic.**

---

## 🏗️ Architecture Overview

The system uses a decoupled, out-of-band architecture:
1. **Frontend (`/user/login/index.html`)**: Requests OTP verification via absolute root URL (`/api.php`).
2. **PHP Backend (`/api.php`)**: Validates student registration, generates a secure 6-digit OTP, and stores it in the `phone_verifications` database table with status `is_sent = 0`.
3. **Node.js Baileys Bot (`@workspace/api-server`)**: Polls `getPendingOTPMessages` every 3 seconds, sends the 6-digit code directly to the student's WhatsApp number (`201037011355@s.whatsapp.net`), and calls `markOTPSent` (`is_sent = 1`).

---

## 🗄️ Database Schema: `phone_verifications`

```sql
CREATE TABLE IF NOT EXISTS phone_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    request_token VARCHAR(32) DEFAULT NULL,
    otp_code VARCHAR(10) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    is_sent TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🔌 Key API Endpoints in `api.php`

### 1. `sendCustomWhatsAppOTP`
- **Params**: `phone`
- **Logic**:
  - Validates that `phone` exists in `students` table using `RIGHT(phone, 10)` SQL matching.
  - Rejects unregistered numbers with: *"عذراً، رقم الهاتف غير مسجل في نظام مدارس الأحد"*.
  - Generates 6-digit random code and unique request token (`REQ-XXXXXXXX`).
  - Inserts row into `phone_verifications` with `is_sent = 0`.
  - **Zero Leak Policy**: Does **NOT** return `otp_code` in JSON response.

### 2. `getPendingOTPMessages`
- **Params**: None
- **Logic**: Returns up to 10 unverified (`is_verified = 0`) and unsent (`is_sent = 0`) OTP records created within the last 10 minutes.

### 3. `markOTPSent`
- **Params**: `id`
- **Logic**: Sets `is_sent = 1` for the specified `phone_verifications` record once delivered by the WhatsApp bot.

### 4. `verifyCustomWhatsAppOTP`
- **Params**: `phone`, `code`
- **Logic**: Checks `phone_verifications` for valid matching 6-digit OTP within 10 minutes. Marks `is_verified = 1` upon success.

### 5. `checkKidPasswordByPhone`
- **Params**: `phone`
- **Logic**: Checks if a registered student has an existing `password_hash`. Wrapped in `Throwable` catch block to prevent HTTP 500 errors.

### 6. `kidLoginByPhoneWithPassword`
- **Params**: `phone`, `password`
- **Logic**: Authenticates student via `password_hash` (`password_verify` or legacy SHA-256).

---

## 🌐 Frontend API Call Rule (`/user/login/index.html`)

> [!IMPORTANT]
> All fetch requests from subdirectories (such as `/user/login/`) **MUST** use the absolute root API path `'/api.php'` instead of relative `'api.php'`. Using relative paths resolves to `/user/login/api.php` which causes 500 HTML error responses.

---

## 🤖 Node WhatsApp Bot Implementation (`src/lib/whatsapp.ts`)

- **Polling Loop**: Every 3 seconds, fetches `getPendingOTPMessages` from `https://sunday-school.online/api.php` and dispatches messages to `${formattedPhone}@s.whatsapp.net`.
- **Anti-Loop Safety**: Ignores `m.key.fromMe = true` and only reacts to incoming messages containing `REQ-` or explicit keyword `"طلب كود"`.
- **Reconnection Handling**: Uses a 5-second backoff delay on close to prevent 440 session conflict loops.

---

## 🚀 How to Run the Bot in Production / Background

### 1. Start in Background (Memory-Optimized)
```bash
lsof -ti:5000 | xargs kill -9 2>/dev/null || true; nohup sh -c 'NODE_OPTIONS="--max-old-space-size=256" PORT=5000 pnpm --filter @workspace/api-server run dev' > bot.log 2>&1 &
```

### 2. View Bot Log Output
```bash
cat bot.log
```

### 3. Stop Background Bot Process
```bash
lsof -ti:5000 | xargs kill -9 2>/dev/null || true
```
