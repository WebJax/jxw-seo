# LocalSEO Booster - WordPress Plugin

AI-Powered Programmatic SEO Plugin for WordPress Full Site Editing (FSE) that generates virtual landing pages based on City + Service data.

## Features

- 🗂️ **Data Center**: Spreadsheet-like interface for managing SEO data
- 🤖 **AI Integration**: Generate unique content using OpenAI, Anthropic, or Google Gemini APIs
- 🔗 **Virtual Routing**: Create landing pages without cluttering wp_posts
- 🧩 **Block Bindings**: Seamlessly integrate with FSE templates
- ⚡ **Performance**: No database bloat, fast virtual page rendering

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

### 2. Add Data

Go to **LocalSEO > Data Center**:
- Click "Add New Row" to create entries
- Fill in City, ZIP, and Service Keyword
- Click "Generate AI" to auto-fill content fields
- Or use "Generate All Missing AI Fields" for bulk generation

### 3. Virtual Page URLs

Pages are accessible via two URL patterns:
- `/localseo/{custom-slug}/`
- `/service/{service-keyword}/{city}/`

Example: `/localseo/kloakmester-dianalund/`

### 4. Block Bindings in FSE Templates

Create or edit a block template and bind content to LocalSEO data:

#### Available Binding Keys:
- `city` - City name
- `zip` - ZIP code
- `service` or `service_keyword` - Service name
- `intro` or `ai_generated_intro` - AI-generated introduction
- `meta_title` - SEO title
- `meta_description` - SEO description

#### Example Block Code:
```html
<!-- wp:heading {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}} -->
<h2>Service Name</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"intro_text"}}}}} -->
<p>AI-generated intro will appear here...</p>
<!-- /wp:paragraph -->
```

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
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

## REST API Endpoints

The plugin provides the following REST API endpoints:

- `GET /wp-json/localseo/v1/data` - Get all data
- `POST /wp-json/localseo/v1/data` - Create new row
- `PUT /wp-json/localseo/v1/data/{id}` - Update row
- `DELETE /wp-json/localseo/v1/data/{id}` - Delete row
- `POST /wp-json/localseo/v1/generate-ai/{id}` - Generate AI content for row
- `POST /wp-json/localseo/v1/generate-ai-bulk` - Bulk generate AI content

## Architecture

### Module A: Data Center (Admin UI)
React-based admin interface with TanStack Table for spreadsheet-like editing.

### Module B: AI Engine
Integrates with OpenAI (GPT-4o-mini) or Anthropic (Claude) APIs to generate unique content for each location.

### Module C: Virtual Routing
Custom rewrite rules and query vars to handle virtual pages without creating post objects.

### Module D: Block Bindings
WordPress 6.5+ Block Bindings API integration for seamless FSE template support.

## File Structure

```
localseo-booster/
├── admin/                      # React admin interface
│   ├── components/
│   │   └── DataCenter.js      # Main data grid component
│   ├── index.js               # Entry point
│   └── style.css              # Admin styles
├── build/                      # Compiled assets (generated)
├── includes/                   # PHP classes
│   ├── class-activator.php    # Plugin activation
│   ├── class-admin.php        # Admin interface
│   ├── class-ai-engine.php    # AI integration
│   ├── class-block-bindings.php # Block bindings
│   ├── class-database.php     # Database operations
│   ├── class-deactivator.php  # Plugin deactivation
│   ├── class-rest-api.php     # REST API endpoints
│   └── class-router.php       # Virtual routing
├── templates/                  # Template files
│   ├── block-templates/
│   │   └── single-localseo.html # FSE template example
│   └── single-localseo.php    # Fallback PHP template
├── localseo-booster.php       # Main plugin file
├── package.json               # NPM dependencies
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

## Support

For issues and feature requests, please use the GitHub repository: https://github.com/WebJax/jxw-seo

## License

GPL v2 or later
