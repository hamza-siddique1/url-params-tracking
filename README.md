# URL Parameter Tracker – WordPress Plugin

Captures **every URL parameter** from incoming site visits and stores them as JSON in a database table. Built specifically for marketing attribution debugging (missing `gclid`, UTM gaps, etc.).

## Installation

1. Copy the `url-param-tracker/` folder into `wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. The DB table (`wp_url_params_log`) is created automatically on activation

## What it does

- Fires on every **front-end page load that has query params** (`?anything=value`)
- Records: timestamp, full page URL, referrer, visitor IP, user agent
- Stores all params as a **JSON blob** — flexible, no schema changes needed

## Admin Pages

| Page | URL |
|------|-----|
| All Logs | WP Admin → Param Tracker → All Logs |
| Coverage Analysis | WP Admin → Param Tracker → Param Coverage |
| Settings | WP Admin → Param Tracker → Settings |

## Coverage Page – what to look for

The **Param Coverage** page shows you exactly which params are present/missing:

- `gclid` coverage = 0% but traffic is coming from Google → Google Auto-Tagging is OFF in Google Ads
- `utm_source` coverage = 100%, `gclid` = 0% → manual UTM tagging only, no GCLID
- CRM shows higher `gclid` count than this table → CRM is reading from GA4 or cookies, not raw URL

## Database Schema

```sql
CREATE TABLE wp_url_params_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    page_url    TEXT NOT NULL,
    referrer    TEXT,
    ip_address  VARCHAR(45),
    user_agent  TEXT,
    params      JSON,
    INDEX idx_created (created_at)
);
```

## Querying the data directly (MySQL)

```sql
-- All visits missing gclid
SELECT * FROM wp_url_params_log
WHERE JSON_EXTRACT(params, '$.gclid') IS NULL
ORDER BY created_at DESC;

-- Count per day: gclid present vs missing
SELECT
    DATE(created_at) as day,
    COUNT(*) as total,
    SUM(JSON_EXTRACT(params, '$.gclid') IS NOT NULL) as has_gclid,
    SUM(JSON_EXTRACT(params, '$.gclid') IS NULL) as missing_gclid
FROM wp_url_params_log
GROUP BY day ORDER BY day DESC;

-- All unique param keys seen
SELECT DISTINCT JSON_KEYS(params) FROM wp_url_params_log LIMIT 100;
```
