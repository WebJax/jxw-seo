# LocalSEO Booster - WordPress Plugin

AI-Powered Programmatic SEO Plugin for WordPress Full Site Editing (FSE) that generates virtual landing pages based on City + Service data.

## Features

- 🗂️ **Data Center**: Spreadsheet-like interface for managing SEO data
- 🤖 **AI Integration**: Generate unique content using OpenAI, Anthropic, or Google Gemini APIs
- 🔗 **Virtual Routing**: Create landing pages without cluttering wp_posts
- 🧩 **Block Bindings**: Seamlessly integrate with FSE templates
- ⚡ **Performance**: No database bloat, fast virtual page rendering
- 🔍 **SEO Meta Tags**: Automatic meta title, description, Open Graph, and Twitter Cards
- 📄 **Schema Markup**: JSON-LD structured data (LocalBusiness, Service, etc.)
- 🗺️ **XML Sitemap**: Integration with WordPress core sitemaps
- 📝 **SEO Meta Box**: Add SEO fields to all WordPress posts and pages
- 📥 **CSV Import/Export**: Bulk-import city/service data from a CSV file and export your dataset at any time
- 🔀 **Redirect Manager**: Manage 301/302 URL redirects with a click-counter to track usage
- 🤖 **Robots.txt Editor**: Append custom directives to the WordPress-generated robots.txt

## Requirements

- WordPress 6.5+
- PHP 8.1+
- OpenAI API key, Anthropic API key, or Google Gemini API key (for AI features)

## Installation

1. Upload the plugin files to `/wp-content/plugins/localseo-booster/`
2. Run `npm install` to install dependencies
3. Run `npm run build` to build the admin interface
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Go to LocalSEO > Settings to configure your API key

## Build Instructions

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Development mode with watch
npm run start
```

## Usage

### 1. Configure Settings

Navigate to **LocalSEO > Settings** in WordPress admin:
- Select your AI provider (OpenAI, Anthropic, or Google Gemini)
- Enter your API key
- Customize the system prompt template
- Configure SEO settings (robots, OG image)
- Set business details (Business Name, Business Phone)
- Set social-proof values (Response Time, Customers Served text)
- Enable/disable sitemap and schema output

### 2. Add Data

Go to **LocalSEO > Data Center**:
- Click "Add New Row" to create entries
- Fill in City, ZIP, and Service Keyword
- Click "Generate AI" to auto-fill content fields
- Or use "Generate All Missing AI Fields" for bulk generation

### 3. Virtual Page URLs

Pages are accessible via the canonical URL pattern:
- `/service/{service-keyword}/{city}/`

Example: `/service/kloakmester/dianalund/`

The legacy `/localseo/{custom-slug}/` pattern is still supported for backward compatibility and automatically **301-redirects** to the canonical `/service/{service}/{city}/` URL.

### 4. Block Bindings in FSE Templates

Create or edit a block template and bind content to LocalSEO data:

#### Available Binding Keys:
- `city` - City name
- `zip` - ZIP code
- `service` or `service_keyword` - Service name
- `intro`, `intro_text`, or `ai_generated_intro` - AI-generated introduction
- `meta_title` - SEO title
- `meta_description` - SEO description
- `slug` - Custom URL slug
- `nearby_cities` - Nearby cities (comma-separated)
- `local_landmarks` - Notable local landmarks
- `phone_url` - Business phone as a `tel:` link (computed)
- `cta_label` - Call-to-action label including city and phone (computed)

#### Example Block Code:
```html
<!-- wp:heading {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}} -->
<h2>Service Name</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"intro_text"}}}}} -->
<p>AI-generated intro will appear here...</p>
<!-- /wp:paragraph -->
```

### 5. SEO for Regular Posts & Pages

The plugin adds an SEO meta box to all public post types:
- Meta Title (with character count)
- Meta Description (with character count)
- Open Graph Image URL
- Canonical URL
- Robots directive (index/noindex, follow/nofollow)
- Schema Type (Article, WebPage, LocalBusiness, etc.)

### 6. CSV Import / Export

In **LocalSEO > Data Center**:
- Click **Export CSV** to download all your city/service data as a UTF-8 CSV file (Excel-compatible BOM included).
- Click **Import CSV** to bulk-import rows from a CSV file.

CSV format (header row required):

```
city,zip,service_keyword,meta_title,meta_description,nearby_cities,local_landmarks
Copenhagen,1000,Plumber,,,
Aarhus,8000,VVS,,,
```

`city` and `service_keyword` are the only required columns; all others are optional.

### 7. Redirect Manager

Go to **LocalSEO > Redirects** to manage URL redirects:
- Enter the **Source Path** (relative, e.g. `/old-page/`)
- Enter the **Target URL** (full URL, e.g. `https://example.com/new-page/`)
- Choose **301 – Permanent** (recommended for SEO) or **302 – Temporary**
- Each redirect row tracks the number of times it has been triggered (**Hits**)

### 8. Robots.txt Editor

Go to **LocalSEO > Settings** and scroll to the **Robots.txt** section to append custom directives to the WordPress-generated `robots.txt`.  
Example:

```
User-agent: GPTBot
Disallow: /
```

A link to the live `/robots.txt` is provided so you can verify the output.

## Database Schema

The plugin creates a custom table `wp_localseo_data` with:
- `id` - Primary key
- `city` - City name
- `zip` - ZIP/postal code
- `service_keyword` - Service identifier
- `custom_slug` - URL slug (auto-generated if empty)
- `ai_generated_intro` - AI-generated introduction text
- `meta_title` - SEO title tag
- `meta_description` - SEO meta description
- `nearby_cities` - Comma-separated nearby cities
- `local_landmarks` - Notable local landmarks
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

The Redirect Manager stores rules in `wp_localseo_redirects`:
- `id` - Primary key
- `source_url` - Path to redirect from (e.g. `/old-page/`)
- `target_url` - Full URL to redirect to
- `redirect_type` - HTTP status code (301 or 302)
- `hits` - Number of times this rule has fired
- `created_at` - Creation timestamp

## REST API Endpoints

The plugin provides the following REST API endpoints:

- `GET /wp-json/localseo/v1/data` - Get all data
- `POST /wp-json/localseo/v1/data` - Create new row
- `PUT /wp-json/localseo/v1/data/{id}` - Update row
- `DELETE /wp-json/localseo/v1/data/{id}` - Delete row
- `POST /wp-json/localseo/v1/generate-ai/{id}` - Generate AI content for row
- `POST /wp-json/localseo/v1/generate-ai-bulk` - Bulk generate AI content
- `POST /wp-json/localseo/v1/import-csv` - Import rows from a CSV string (body: `{ "csv": "…" }`)

CSV export is triggered via the admin UI (Downloads the file directly).

## Architecture

### Module A: Data Center (Admin UI)
React-based admin interface with TanStack Table for spreadsheet-like editing.

### Module B: AI Engine
Integrates with OpenAI (GPT-4o-mini), Anthropic (Claude), or Google Gemini APIs to generate unique content for each location.

### Module C: Virtual Routing
Custom rewrite rules and query vars to handle virtual pages without creating post objects.

### Module D: Block Bindings
WordPress 6.5+ Block Bindings API integration for seamless FSE template support.

### Module E: SEO Tags
Outputs meta tags (title, description, Open Graph, Twitter Cards, canonical, robots) for both virtual pages and regular posts/pages.

### Module F: Schema Markup
JSON-LD structured data output for LocalSEO pages with support for LocalBusiness, Service, and related schema types. Includes BreadcrumbList markup and outputs schema for regular posts/pages when a schema type is set in the SEO meta box.

### Module G: XML Sitemap
Integration with WordPress core sitemaps API to include virtual LocalSEO pages.

### Module H: SEO Meta Box
Adds comprehensive SEO fields to all public post types for complete site-wide SEO control.

### Module I: CSV Import/Export
Bulk import city/service rows from a CSV file via the Data Center UI. Export the full dataset as a UTF-8 CSV with a single click.

### Module J: Redirect Manager
Manages 301/302 URL redirects stored in a dedicated `wp_localseo_redirects` database table. Redirects are processed on every frontend request (priority 1) and cached in a transient for performance. A hit counter tracks how many times each rule fires.

### Module K: Robots.txt Editor
Appends custom robots.txt directives to the WordPress-generated output via the `robots_txt` filter. Rules are configured through **LocalSEO > Settings**.

## File Structure

```
localseo-booster/
├── admin/                      # React admin interface
│   ├── components/
│   │   └── DataCenter.js      # Main data grid component (with CSV import/export)
│   ├── index.js               # Entry point
│   └── style.css              # Admin styles
├── build/                      # Compiled assets (generated)
├── includes/                   # PHP classes
│   ├── class-activator.php    # Plugin activation
│   ├── class-admin.php        # Admin interface + robots.txt filter
│   ├── class-ai-engine.php    # AI integration
│   ├── class-block-bindings.php # Block bindings
│   ├── class-database.php     # Database operations
│   ├── class-deactivator.php  # Plugin deactivation
│   ├── class-redirects.php    # Redirect Manager (301/302)
│   ├── class-rest-api.php     # REST API endpoints (incl. CSV import/export)
│   ├── class-router.php       # Virtual routing
│   ├── class-seo-tags.php     # SEO meta tags output
│   ├── class-schema.php       # JSON-LD schema markup
│   ├── class-sitemap.php      # XML sitemap integration
│   └── class-meta-box.php     # SEO meta box for posts/pages
├── templates/                  # Template files
│   ├── block-templates/
│   │   └── single-localseo.html # FSE template example
│   └── single-localseo.php    # Fallback PHP template
├── examples/                   # Example files
├── localseo-booster.php       # Main plugin file
├── package.json               # NPM dependencies
├── QUICKSTART.md              # Quick start guide
├── BLOCK_BINDINGS_GUIDE.md    # Block bindings documentation
└── README.md                  # This file
```

## Developer Notes

### Extending the Plugin

#### Add Custom Fields
Edit `includes/class-activator.php` to modify the database schema and `includes/class-database.php` to handle new fields.

#### Custom AI Prompts
The system prompt supports placeholders: `{service}`, `{city}`, `{zip}`. These are replaced with actual data when calling the AI API.

#### Template Customization
1. Copy `templates/block-templates/single-localseo.html` to your theme
2. Modify the blocks and bindings as needed
3. The plugin will use your theme's version

#### SEO Customization
All SEO output can be customized via WordPress filters and options. Check the respective class files for available hooks.

## Documentation

- **Quick Start Guide**: See [QUICKSTART.md](QUICKSTART.md)
- **Block Bindings Guide**: See [BLOCK_BINDINGS_GUIDE.md](BLOCK_BINDINGS_GUIDE.md)
- **Implementation Details**: See [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)

## Support

For issues and feature requests, please use the GitHub repository: https://github.com/WebJax/jxw-seo

## License

GPL v2 or later