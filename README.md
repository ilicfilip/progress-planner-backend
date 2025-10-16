# Progress Planner Backend

A Laravel-based monitoring dashboard for tracking Progress Planner WordPress plugin installations across multiple sites.

## Features

- 🔐 Secure authentication (Laravel Breeze)
- 📊 Dashboard displaying all registered sites
- 🔄 Background job queue for fetching site stats (prevents timeouts)
- 📈 Plugin version tracking
- ✅ API availability status monitoring
- 🌐 Direct links to site endpoints
- 📋 Detailed site information modal with raw API data
- 🎨 Dark mode support
- 📸 HTML snapshot fetching via Cloudflare Workers (prevents IP tracking & timeouts)

## Requirements

- PHP 8.3+
- Composer
- SQLite (or any Laravel-supported database)
- Node.js & NPM (for asset compilation)

## Installation

### 1. Clone and Install Dependencies

```bash
# Navigate to project directory
cd /Users/filip/Valet/planner-backend/htdocs

# Install PHP dependencies
composer install

# Install NPM dependencies
npm install
```

### 2. Environment Configuration

The `.env` file should already be configured with SQLite. Verify these settings:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/Users/filip/Valet/planner-backend/htdocs/database/database.sqlite

# Queue configuration (already set)
QUEUE_CONNECTION=database

# Cloudflare Worker URL for HTML fetching
CLOUDFLARE_WORKER_URL=https://your-worker.workers.dev
```

The queue is configured to use the database driver, which stores jobs in the `jobs` table.

### 3. Generate Application Key

If not already generated:

```bash
php artisan key:generate
```

### 4. Run Database Migrations

```bash
php artisan migrate
```

### 5. Build Frontend Assets

```bash
npm run build
```

## Creating a User Account

### Option 1: Using Tinker (Recommended)

```bash
php artisan tinker
```

Then run:

```php
App\Models\User::create([
    'name' => 'Your Name',
    'email' => 'your@email.com',
    'password' => bcrypt('your-secure-password')
]);
```

Press `Ctrl+C` to exit Tinker.

### Option 2: Using Registration Page

1. Start your Laravel server
2. Navigate to `/register`
3. Fill in the registration form

## Fetching Progress Planner Data

### Initial Data Fetch

Before accessing the dashboard, fetch the data from Progress Planner:

```bash
php artisan progress-planner:fetch
```

This command will:
- Fetch all registered sites from the Progress Planner API
- Store them in the database
- Attempt to fetch stats from each site's API endpoint
- Display a summary of successful and failed requests

### Force Refresh (Clear Cache)

```bash
php artisan progress-planner:fetch --force
```

## Running the Application

### Laravel Valet (macOS)

If you're using Laravel Valet:

```bash
# The site should already be accessible at:
# http://planner-backend.test
```

### Built-in PHP Server

```bash
php artisan serve
```

Access at: `http://localhost:8000`

### Laravel Sail (Docker)

```bash
./vendor/bin/sail up
```

## Running the Queue Worker

**IMPORTANT**: The queue worker must be running for the "Refetch Data" button to work properly. The application uses background jobs to fetch stats from hundreds of sites, preventing HTTP timeouts.

### For Development

Open a **separate terminal** and run:

```bash
php artisan queue:work
```

This will process background jobs as they are dispatched. Keep this terminal open while using the application.

### For Production (using Supervisor)

Install Supervisor (if not already installed):

```bash
# macOS
brew install supervisor

# Ubuntu/Debian
sudo apt-get install supervisor
```

Create a Supervisor configuration file at `/etc/supervisor/conf.d/progress-planner-worker.conf`:

```ini
[program:progress-planner-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=your-user
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then start the workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start progress-planner-worker:*
```

### Monitoring Queue Jobs

Check pending jobs:
```bash
php artisan queue:monitor
```

View failed jobs:
```bash
php artisan queue:failed
```

Retry failed jobs:
```bash
php artisan queue:retry all
```

## Accessing the Dashboard

1. Open your browser and navigate to your application URL
2. Log in with your credentials
3. You'll be redirected to the dashboard showing all registered sites

### Dashboard Features

- **Site URL**: Clickable link to the actual site
- **License Key**: Truncated license key for security
- **Plugin Version**: Current version of Progress Planner plugin
- **Last Emailed**: Date of last email sent to site owner
- **API Status**: Green (Available) or Red (Failed)
- **API Endpoint**: Direct link to the site's stats API (if available)
- **HTML Snapshot**: Preview fetched homepage HTML (opens in new tab)
  - Shows "Preview" link if snapshot exists
  - Displays when the snapshot was last fetched
  - Shows "Not fetched" if no snapshot is available
- **View Details**: Opens a modal with complete site information and raw API responses
- **Refetch Data Button**: Queues background jobs to refresh all data from the API

### How Refetch Works

When you click "Refetch Data":
1. The registered sites list is fetched from Progress Planner API (fast, ~2 seconds)
2. Sites are synced to the database
3. Background jobs are dispatched for each site to fetch individual stats
4. The page returns immediately with a success message
5. Jobs process in the background (requires queue worker to be running)
6. Refresh the page to see updated stats as jobs complete

## Artisan Commands

### Fetch Progress Planner Data

```bash
# Normal fetch (uses cached data if available)
php artisan progress-planner:fetch

# Force refresh (ignores cache)
php artisan progress-planner:fetch --force
```

### Fetch Site HTML via Cloudflare Worker

Fetch and store homepage HTML snapshots for all registered sites using Cloudflare Workers. This prevents timeout issues and keeps your server IP private.

**Important**: Only fetches HTML from sites where the Progress Planner plugin is confirmed active (api_available = true).

```bash
# Fetch HTML for all sites with active plugin (only those not fetched in last hour)
php artisan sites:fetch-html

# Force fetch all sites regardless of last fetch time
php artisan sites:fetch-html --force

# Fetch specific domain(s)
php artisan sites:fetch-html --domains=example.com
php artisan sites:fetch-html --domains=example.com --domains=another.com

# Check fetch status and progress
php artisan sites:html-status

# Clear stale cache and start fresh
php artisan sites:html-status --clear
```

**How it works:**
1. Command sends domain list to Cloudflare Worker (only sites with active plugin)
2. Worker fetches HTML from each site (distributed, anonymous)
3. After 60 seconds, Laravel retrieves and stores the HTML snapshots
4. Background jobs handle the entire process (requires queue worker running)

**Troubleshooting:**
- If you see "Domains are ready but no jobs queued", run `php artisan queue:work`
- If fetch seems stuck, check status with `php artisan sites:html-status`
- To reset everything: `php artisan sites:html-status --clear` then `php artisan sites:fetch-html --force`

### Preview Fetched HTML

Once HTML snapshots have been fetched, you can preview them directly from the dashboard:

1. Navigate to the "Registered Sites" dashboard
2. Find the site you want to preview in the table
3. In the "HTML Snapshot" column:
   - If a snapshot exists, you'll see a "Preview" link
   - Click the link to open the HTML in a new tab
   - The timestamp below shows when it was last fetched
4. The preview displays the actual HTML content as it was fetched by the Cloudflare Worker

**Route**: The preview is available at `/registered-sites/{site}/preview-html`

**Note**: The HTML is served with `X-Frame-Options: SAMEORIGIN` header for security.

## Project Structure

```
app/
├── Console/Commands/
│   ├── FetchProgressPlannerData.php    # Artisan command for data fetching
│   ├── FetchSiteHtmlCommand.php        # Artisan command for HTML fetching
│   └── CheckHtmlFetchStatus.php        # Artisan command for checking HTML fetch status
├── Http/Controllers/
│   └── DashboardController.php         # Dashboard and refetch logic
├── Jobs/
│   ├── FetchSiteStatsJob.php           # Background job for fetching site stats
│   ├── QueueSiteHtmlFetchJob.php       # Job to queue domains with CF Worker
│   └── FetchSiteHtmlJob.php            # Job to retrieve HTML from CF Worker
├── Models/
│   ├── RegisteredSite.php              # Site model
│   ├── SiteStat.php                    # Stats model
│   └── SiteSnapshot.php                # HTML snapshot model
└── Services/
    ├── ProgressPlannerService.php      # API fetching and caching
    ├── SiteStatsService.php            # Individual site stats fetching
    └── CloudflareWorkerService.php     # Cloudflare Worker communication

database/migrations/
├── *_create_registered_sites_table.php
├── *_create_site_stats_table.php
├── *_create_site_snapshots_table.php   # HTML snapshots table
└── *_create_jobs_table.php             # Queue jobs table

resources/views/
└── dashboard.blade.php                 # Main dashboard view with modal
```

## Database Schema

### registered_sites
- `id`: Primary key
- `site_url`: Unique site URL
- `license_key`: Plugin license key
- `last_emailed_at`: YYYYWW format from API
- `last_emailed_date`: Converted date (Y-m-d)
- `raw_data`: JSON of complete API response
- `timestamps`: created_at, updated_at

### site_stats
- `id`: Primary key
- `registered_site_id`: Foreign key to registered_sites
- `api_available`: Boolean indicating if API is accessible
- `plugin_version`: Detected plugin version
- `raw_response`: JSON of complete API response
- `error_message`: Error details if API call failed
- `last_fetched_at`: Timestamp of last fetch attempt
- `timestamps`: created_at, updated_at

### site_snapshots
- `id`: Primary key
- `registered_site_id`: Foreign key to registered_sites
- `domain`: Site domain name (indexed)
- `html_content`: Full HTML content of homepage
- `timestamps`: created_at, updated_at (updated_at tracks last fetch)

## API Configuration

### Progress Planner API

The Progress Planner API configuration is located in:

**File**: `app/Services/ProgressPlannerService.php`

```php
private const API_URL = 'https://progressplanner.com/wp-json/progress-planner-saas/v1/registered-sites';
private const API_TOKEN = 'ebd6a96d7320c0d1dd5e819098676f08';
private const CACHE_KEY = 'registered_sites_data';
private const CACHE_TTL = 3600; // 1 hour
```

To modify the API endpoint or token, edit these constants.

### Cloudflare Worker Configuration

The Cloudflare Worker service configuration is located in:

**File**: `app/Services/CloudflareWorkerService.php`

```php
private const CACHE_PREFIX_IN_PROGRESS = 'html_fetch_in_progress';
private const CACHE_PREFIX_PENDING = 'html_fetch_pending';
private const CACHE_TTL = 3600; // 1 hour
private const FETCH_DELAY = 60; // Wait 60 seconds before fetching results
private const REQUEST_TIMEOUT = 45; // seconds
```

The Worker URL is configured in `.env`:
```env
CLOUDFLARE_WORKER_URL=https://your-worker.workers.dev
```

**Worker Endpoints:**
- `POST /fetch-domains` - Queue domains for HTML fetching
- `GET /get-results?domain=example.com` - Retrieve HTML for a domain

## Caching

Registered sites data is cached for 1 hour by default to reduce API calls. You can:

- Clear cache: `php artisan cache:clear`
- Force refresh: Use the "Refetch Data" button or `--force` flag

## Troubleshooting

### Database Issues

```bash
# Reset database
php artisan migrate:fresh

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Asset Issues

```bash
# Rebuild assets
npm run build

# For development with hot reload
npm run dev
```

### Clear All Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Check Logs

```bash
tail -f storage/logs/laravel.log
```

### Queue Worker Issues

If the refetch button doesn't update data:

```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Check pending jobs
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Restart queue worker
# Stop with Ctrl+C and restart
php artisan queue:work

# Clear and restart queue
php artisan queue:restart
```

If jobs are failing:
- Check `storage/logs/laravel.log` for errors
- Verify the site's API is accessible
- Check timeout settings in `app/Services/SiteStatsService.php`

## Security Notes

- All routes require authentication
- The root URL redirects to dashboard (requires login)
- API token is hardcoded in service class (consider moving to .env for production)
- Database contains sensitive site information (ensure proper server security)

## Development

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
# Laravel Pint (included)
./vendor/bin/pint
```

## License

Private project for Progress Planner monitoring.

## Support

For issues or questions, contact the development team.
