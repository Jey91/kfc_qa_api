# JSON Response Structure

## Overview
This document defines the standard JSON response structure for API requests. The response includes a `status_code`, a `message`, and a `data` object containing relevant details.

---

## Response Format
Each response follows the structure below:

```json
{
    "status_code": <integer>,
    "message": "<string>",
    "data": {
        "user": {
            "id": <integer>,
            "username": "<string>",
            "email": "<string>",
            "first_name": "<string>",
            "last_name": "<string>",
            "is_active": <integer>,
            "created_at": "<YYYY-MM-DD HH:MM:SS>",
            "updated_at": "<YYYY-MM-DD HH:MM:SS>"
        },
        "access_token": "<string>",
        "expires_at": "<YYYY-MM-DD HH:MM:SS>"
    }
}
```

---

## Status Codes
| Status Code | Description          |
|------------|----------------------|
| 200        | OK                   |
| 400        | Bad Request          |
| 401        | Token Expired        |

---

## Key Naming Conventions
- All keys are in **small lowercase letters** (e.g., `expires_at` ✅, `expiresAt` ❌).
- Timestamps are in the format `YYYY-MM-DD HH:MM:SS`.

---

## Example Response
```json
{
    "status_code": 200,
    "message": "Resource created successfully",
    "data": {
        "user": {
            "id": 2,
            "username": "user",
            "email": "user@example.com",
            "first_name": "John",
            "last_name": "Doe",
            "is_active": 1,
            "created_at": "2025-03-11 16:07:49",
            "updated_at": "2025-03-11 16:07:49"
        },
        "access_token": "ea7c7cf0b443aa1d538484b4be0fbf1687aafe6a843da1b300d1422b8144c677",
        "expires_at": "2025-03-11 09:07:49"
    }
}
```

---

## How To Run
docker compose up -d