# Notification Dispatcher

Event-driven notification system built with Laravel 11. Supports SMS, Email, and Push channels with priority queues, retry logic, and real-time tracking.

## Case Requirements Coverage

| Requirement | Status |
|------------|--------|
| Multi-channel support (SMS, Email, Push) | ✅ |
| Batch creation (up to 1000) | ✅ |
| Query by ID or batch ID | ✅ |
| Cancel pending notifications | ✅ |
| List with filtering & pagination | ✅ |
| Async processing via queue | ✅ |
| Rate limiting (100/sec per channel) | ✅ |
| Priority queues (high, normal, low) | ✅ |
| Content validation | ✅ |
| Idempotency support | ✅ |
| Real-time metrics endpoint | ✅ |
| Structured logging with correlation IDs | ✅ |
| Health check endpoint | ✅ |
| webhook.site integration | ✅ |

### Bonus Features Implemented

| Feature | Description |
|---------|-------------|
| Failure Handling | Circuit breaker, dead letter queue, exponential backoff retry |
| Scheduled Notifications | Future delivery with `scheduled_at` parameter |
| Template System | Reusable templates with `{{variable}}` substitution |
| WebSocket Updates | Real-time status via Laravel Reverb |
| Distributed Tracing | OpenTelemetry integration with correlation IDs |
| GitHub Actions CI/CD | Automated tests, PHPStan, PHP CS Fixer |

## Quick Start

```bash
# Clone and setup
git clone <repo-url>
cd notification-dispatcher
cp .env.example .env

# Start all services
docker-compose up -d

# Install dependencies and setup
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate

# Generate API key
docker-compose exec app php artisan api:generate-key --name="Dev" --permissions=read,write
```

API available at `http://localhost:8000/api/v1`

## Configuration

### Webhook Provider

1. Go to https://webhook.site and copy your unique UUID
2. Set in `.env`:
```env
WEBHOOK_URL=https://webhook.site/your-uuid-here
```

### Environment Variables

```env
# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=notification_dispatcher

# Queue
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq

# Rate Limits (per minute)
RATE_LIMIT_EMAIL=50
RATE_LIMIT_SMS=20
RATE_LIMIT_PUSH=100
```

## API Usage

All requests require `Authorization: Bearer <api_key>` header.

### Create Notification

```bash
# SMS
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"recipient": "+905551234567", "channel": "sms", "content": "Hello!", "priority": "high"}'

# Email
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"recipient": "user@example.com", "channel": "email", "subject": "Welcome", "content": "Hello!", "priority": "normal"}'
```

### Batch Notifications

```bash
curl -X POST http://localhost:8000/api/v1/notifications/batch \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "notifications": [
      {"recipient": "+905551111111", "channel": "sms", "content": "Message 1", "priority": "high"},
      {"recipient": "user@example.com", "channel": "email", "subject": "Hi", "content": "Message 2"}
    ]
  }'
```

### Scheduled Notification

```bash
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"recipient": "+905551234567", "channel": "sms", "content": "Scheduled!", "scheduled_at": "2026-02-01T10:00:00Z"}'
```

### With Template

```bash
# Create template
curl -X POST http://localhost:8000/api/v1/templates \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "Welcome", "channel": "email", "subject": "Welcome {{name}}", "content": "Hello {{name}}, your code is {{code}}"}'

# Use template
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"recipient": "user@example.com", "channel": "email", "template_id": "<uuid>", "variables": {"name": "John", "code": "123456"}}'
```

### Query & Cancel

```bash
# List with filters
curl -H "Authorization: Bearer $API_KEY" \
  "http://localhost:8000/api/v1/notifications?status=sent&channel=sms&per_page=20"

# Get by ID
curl -H "Authorization: Bearer $API_KEY" \
  "http://localhost:8000/api/v1/notifications/{id}"

# Get statistics
curl -H "Authorization: Bearer $API_KEY" \
  "http://localhost:8000/api/v1/notifications/stats"

# Cancel pending
curl -X DELETE -H "Authorization: Bearer $API_KEY" \
  "http://localhost:8000/api/v1/notifications/{id}"
```

### Health & Metrics

```bash
# Health check (no auth)
curl http://localhost:8000/health

# Metrics
curl -H "Authorization: Bearer $API_KEY" http://localhost:8000/api/v1/metrics
```

## Architecture

```
Request → API (validation, auth) → Database → RabbitMQ Queue → Worker → Webhook Provider
                                                    ↓
                                            WebSocket Broadcast → Dashboard
```

### Queue Structure

Channel-based queues with RabbitMQ native priority (0-255):

| Queue | Priority Levels | Description |
|-------|-----------------|-------------|
| `notifications-sms` | HIGH=250, NORMAL=100, LOW=10 | SMS channel |
| `notifications-email` | HIGH=250, NORMAL=100, LOW=10 | Email channel |
| `notifications-push` | HIGH=250, NORMAL=100, LOW=10 | Push channel |

Docker Compose runs 3 queue workers that listen to all channels. High priority messages are processed first within each channel.

### Rate Limiting

Two-level rate limiting protects both API and external providers:

| Type | Limit | Scope | Purpose |
|------|-------|-------|---------|
| API Rate Limit | 1000 req/min (auth) | Per API key | DDoS protection |
| API Rate Limit | 100 req/min (unauth) | Per IP | DDoS protection |
| Channel Rate Limit | 100 msg/sec | Per channel | Provider protection (case requirement) |

API responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers.

### Circuit Breaker

Protects system when external provider fails:

- **Threshold**: 5 consecutive failures → circuit opens
- **Recovery**: 30 seconds wait, then half-open state
- **Half-open**: 1 test request allowed; success closes circuit, failure reopens

### Failure & Retry Mechanism

```
Job starts → PROCESSING → Delivery attempt
                              ↓
                    ┌─────────┴─────────┐
                Success              Failure
                    ↓                    ↓
                  SENT          attempts < 5?
                              ┌─────┴─────┐
                             Yes          No
                              ↓            ↓
                     Wait & Retry      FAILED
                     (stay PROCESSING)    ↓
                                    Dead Letter Queue
                                    (failed_notifications)
```

**Status during retries:** Notification stays in `PROCESSING` status until either success (`SENT`) or final failure (`FAILED`).

**Exponential backoff delays:**

| Attempt | Delay | Total Wait |
|---------|-------|------------|
| 1st retry | 1 minute | 1 min |
| 2nd retry | 5 minutes | 6 min |
| 3rd retry | 30 minutes | 36 min |
| 4th retry | 2 hours | ~2.5 hours |
| 5th fail | - | → Dead Letter Queue |

**Dead Letter Queue:** After 5 failed attempts, notification is marked `FAILED` and a record is created in `failed_notifications` table with error details for analysis.

### Modular Monolith Structure

```
app/Modules/
├── Auth/           # API key authentication
├── Delivery/       # Providers, circuit breaker, rate limiter
├── Notification/   # Core notification logic, jobs
├── Observability/  # Health, metrics, tracing
└── Template/       # Template CRUD and rendering

app/Shared/
└── Enums/          # Channel, Priority, Status
```

Each module is self-contained with its own Controllers, Services, Models, and Exceptions. Designed for potential microservice extraction if needed.

## Dashboard

Vue.js dashboard with real-time updates via WebSocket.

**Access:** `http://localhost:8000/dashboard`

**Features:**
- Live notification list (auto-updates)
- Queue status and metrics
- Channel breakdown (SMS, Email, Push)
- Recent notifications with status

Dashboard connects to Laravel Reverb (WebSocket) and updates automatically when notifications are sent or fail.

## Testing

```bash
# Run all tests
docker-compose exec app php artisan test

# With coverage (min 78%)
docker-compose exec app php artisan test --coverage --min=78
```

## Services

| Service | Port | Description |
|---------|------|-------------|
| API | 8000 | Laravel application |
| Dashboard | 8000/dashboard | Vue.js real-time dashboard |
| MySQL | 3307 | Database |
| Redis | 6379 | Cache & rate limiting |
| RabbitMQ | 5672 | Message queue |
| RabbitMQ Management | 15672 | Queue UI (notification/secret) |
| Reverb (WebSocket) | 8080 | Real-time updates |

## API Documentation

- OpenAPI spec: `openapi.yaml`
- Postman collection: `Notification_Dispatcher_API_Minimal.postman_collection.json`

## Tech Stack

- Laravel 11, PHP 8.3
- MySQL 8.0, Redis 7, RabbitMQ 3.12
- Laravel Reverb (WebSocket)
- Docker & Docker Compose
- GitHub Actions (CI/CD)
