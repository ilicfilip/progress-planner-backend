# Progress Planner Backend

A Laravel-based monitoring dashboard for tracking Progress Planner WordPress plugin installations across multiple sites.

## Features

- 🔐 Secure authentication (Laravel Breeze)
- 📊 Dashboard displaying all registered sites
- 🔄 Automatic data fetching and caching from Progress Planner API
- 📈 Plugin version tracking
- ✅ API availability status monitoring
- 🌐 Direct links to site endpoints
- 🎨 Dark mode support

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
```

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
- **Refetch Data Button**: Manually refresh all data from the API

## Artisan Commands

### Fetch Progress Planner Data

```bash
# Normal fetch (uses cached data if available)
php artisan progress-planner:fetch

# Force refresh (ignores cache)
php artisan progress-planner:fetch --force
```

## Project Structure

```
app/
├── Console/Commands/
│   └── FetchProgressPlannerData.php    # Artisan command for data fetching
├── Http/Controllers/
│   └── DashboardController.php         # Dashboard and refetch logic
├── Models/
│   ├── RegisteredSite.php              # Site model
│   └── SiteStat.php                    # Stats model
└── Services/
    ├── ProgressPlannerService.php      # API fetching and caching
    └── SiteStatsService.php            # Individual site stats fetching

database/migrations/
├── *_create_registered_sites_table.php
└── *_create_site_stats_table.php

resources/views/
└── dashboard.blade.php                 # Main dashboard view
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

## API Configuration

The Progress Planner API configuration is located in:

**File**: `app/Services/ProgressPlannerService.php`

```php
private const API_URL = 'https://progressplanner.com/wp-json/progress-planner-saas/v1/registered-sites';
private const API_TOKEN = 'ebd6a96d7320c0d1dd5e819098676f08';
private const CACHE_KEY = 'registered_sites_data';
private const CACHE_TTL = 3600; // 1 hour
```

To modify the API endpoint or token, edit these constants.

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
