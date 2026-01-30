# Notification API Documentation

Base URL: `http://localhost/api/v1`

## Endpoints

### 1. Create Single Notification

```http
POST /notifications
Content-Type: application/json
X-Idempotency-Key: optional-unique-key

{
  "recipient": "+905551234567",
  "channel": "sms",
  "content": "Hello, this is a test notification",
  "priority": "high",
  "scheduled_at": "2026-01-31T10:00:00Z"
}
```

**Response (201):**
```json
{
  "message": "Notification created successfully",
  "data": {
    "id": "uuid",
    "recipient": "+905551234567",
    "channel": "sms",
    "content": "Hello, this is a test notification",
    "status": "pending",
    "priority": "high",
    "scheduled_at": "2026-01-31T10:00:00+00:00",
    "created_at": "2026-01-30T12:00:00+00:00"
  }
}
```

### 2. Create Batch Notifications

```http
POST /notifications
Content-Type: application/json

{
  "notifications": [
    {
      "recipient": "user1@example.com",
      "channel": "email",
      "content": "Email content here",
      "subject": "Email Subject",
      "priority": "normal"
    },
    {
      "recipient": "+905551234567",
      "channel": "sms",
      "content": "SMS content here",
      "priority": "high"
    }
  ]
}
```

**Response (201):**
```json
{
  "message": "Batch notifications created successfully",
  "batch_id": "uuid",
  "count": 2,
  "data": [...]
}
```

### 3. List Notifications

```http
GET /notifications?status=pending&channel=sms&per_page=20&page=1
```

**Query Parameters:**
- `status` - Filter by status (pending, queued, processing, sent, delivered, failed, cancelled)
- `channel` - Filter by channel (sms, email, push)
- `priority` - Filter by priority (low, normal, high)
- `batch_id` - Filter by batch ID
- `from` - Filter from date (ISO 8601)
- `to` - Filter to date (ISO 8601)
- `sort_by` - Sort field (default: created_at)
- `sort_order` - Sort order (asc, desc)
- `per_page` - Items per page (default: 15)

**Response (200):**
```json
{
  "data": [...],
  "meta": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7
  }
}
```

### 4. Get Notification by ID

```http
GET /notifications/{id}
```

**Response (200):**
```json
{
  "data": {
    "id": "uuid",
    "recipient": "+905551234567",
    "channel": "sms",
    "status": "delivered",
    ...
  }
}
```

**Response (404):**
```json
{
  "message": "Notification not found"
}
```

### 5. Cancel Notification

```http
DELETE /notifications/{id}
```

**Response (200):**
```json
{
  "message": "Notification cancelled successfully"
}
```

**Response (422):**
```json
{
  "message": "Cannot cancel notification in current status",
  "current_status": "sent"
}
```

### 6. Get Statistics

```http
GET /notifications/stats?batch_id=optional-batch-id
```

**Response (200):**
```json
{
  "data": {
    "total": 1000,
    "by_status": {
      "pending": 100,
      "sent": 800,
      "failed": 100
    },
    "by_channel": {
      "sms": 500,
      "email": 400,
      "push": 100
    },
    "by_priority": {
      "high": 200,
      "normal": 700,
      "low": 100
    }
  }
}
```

## Channels

- `sms` - SMS notifications
- `email` - Email notifications (requires `subject` field)
- `push` - Push notifications

## Priorities

- `low` - Low priority (1 worker)
- `normal` - Normal priority (2 workers) - default
- `high` - High priority (4 workers)

## Status Values

- `pending` - Created, not yet queued
- `queued` - Added to queue
- `processing` - Being processed
- `sent` - Sent to provider
- `delivered` - Confirmed delivery
- `failed` - Failed after retries
- `cancelled` - Cancelled by user

## Validation Rules

### Single Notification
- `recipient` - required, string
- `channel` - required, enum (sms, email, push)
- `content` - required, string
- `subject` - optional, string (required for email)
- `priority` - optional, enum (low, normal, high)
- `template_id` - optional, uuid, exists in templates
- `scheduled_at` - optional, date (must be future)
- `metadata` - optional, object

### Batch Notifications
- `notifications` - required, array (min: 1, max: 1000)
- Each item follows single notification rules

## Error Responses

**Validation Error (422):**
```json
{
  "message": "The given data was invalid",
  "errors": {
    "recipient": ["The recipient field is required."]
  }
}
```

**Not Found (404):**
```json
{
  "message": "Notification not found"
}
```

**Server Error (500):**
```json
{
  "message": "Internal server error"
}
```
