# URL Parameter Tracker – WordPress Plugin

Captures **every URL parameter** from incoming site visits and stores them as JSON in a database table. Built specifically for marketing attribution debugging (missing `gclid`, UTM gaps, etc.).

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

```
