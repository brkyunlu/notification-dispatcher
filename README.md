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
# Clone and start (everything is auto-configured!)
git clone <repo-url>
cd notification-dispatcher
docker-compose up -d
```

When you run `docker-compose up -d`, the following happens automatically:
- Creates `.env` from `.env.example`
- Installs dependencies (composer, npm)
- Runs database migrations and seeds demo data (templates & notifications)
- Generates API key for the dashboard
- Gets a unique webhook URL from webhook.site and writes it to `.env`
- Builds frontend assets

**That's it!** Wait for init to complete (check logs with `docker-compose logs init`), then:

| Service | URL |
|---------|-----|
| API | http://localhost:8000/api/v1 |
| Dashboard | http://localhost:8000/dashboard |
| RabbitMQ | http://localhost:15672 (username:notification pass:secret) |
| Jaeger | http://localhost:16686 |

### Using the generated `.env`

Init writes two values into `.env` that you can use elsewhere:

- **`NOTIFICATION_WEBHOOK_URL`** — Outgoing notifications are posted to this URL (e.g. `https://webhook.site/your-unique-uuid`). To inspect requests: open the same URL in your browser, or go to `https://webhook.site/#!/view/your-unique-uuid` (use the UUID from the URL in `.env`). Init logs also print the "View requests" link.

- **`VITE_API_KEY`** — This is the API key for the dashboard and for API calls. In Postman: import the [Notification Dispatcher API (Minimal) collection](Notification_Dispatcher_API_Minimal.postman_collection.json), then set the collection variable **`api_key`** to the value of `VITE_API_KEY` from your `.env`. All requests in the collection use `Authorization: Bearer {{api_key}}`.

To see the API key again in init logs:
```bash
docker-compose logs init | grep "API Key"
```

## Manual Configuration (Optional)

If you prefer manual setup or need to change values:

### If .env creation or update fails

If init did not create or update `.env` correctly (e.g. missing `NOTIFICATION_WEBHOOK_URL` or `VITE_API_KEY`), create or edit `.env` manually: copy from `.env.example`, then set the values you need (see Webhook Provider and API Key below).

### Making Docker see .env changes

Containers load `.env` when they start. Laravel can also cache config and application cache; if you change `.env` and still see old values, do the following.

1. **Clear Laravel caches** (so the app stops using cached config/values):

```bash
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

2. **Restart the services** that use `.env` so they load it again:

```bash
docker-compose restart app queue-worker scheduler
```

After this, the PHP app, queue workers, and scheduler will use the updated `.env` and no longer serve stale cached values.

> **⚠️ Important:** If you change `VITE_*` variables (like `VITE_API_KEY`, `VITE_REVERB_*`), you must also rebuild the frontend since these values are embedded at build time:
> ```bash
> docker-compose exec app npm run build
> ```
> Then hard refresh your browser (Cmd+Shift+R / Ctrl+Shift+R).

### Webhook Provider

Auto-configured, but you can use your own:
```bash
# Get your own webhook URL
curl -s -X POST https://webhook.site/token | jq -r '.uuid'

# Update .env
NOTIFICATION_WEBHOOK_URL=https://webhook.site/<your-uuid>
```

### API Key

Auto-generated, but you can create additional keys:
```bash
docker-compose exec app php artisan api:generate-key "MyApp" --permissions=read,write
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

# Rate Limits
RATE_LIMIT_RPS=100  # requests per second per channel
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
