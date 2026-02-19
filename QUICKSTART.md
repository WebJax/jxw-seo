# LocalSEO Booster - Quick Start Guide

## Installation

1. **Install the Plugin**
   - Copy the plugin folder to `/wp-content/plugins/localseo-booster/`
   - Activate the plugin through WordPress admin

2. **Install Dependencies and Build**
   ```bash
   cd /wp-content/plugins/localseo-booster/
   npm install
   npm run build
   ```

3. **Configure Settings**
   - Go to **WordPress Admin > LocalSEO > Settings**
   - Choose AI Provider (OpenAI or Anthropic)
   - Enter your API key
   - Customize the system prompt (optional)

## Basic Usage

### Step 1: Add Your First Entry

1. Navigate to **WordPress Admin > LocalSEO > Data Center**
2. Click **"Add New Row"**
3. Click on the empty cells to edit:
   - **City**: Enter "Dianalund"
   - **ZIP**: Enter "4293"
   - **Service**: Enter "Kloakmester"

### Step 2: Generate AI Content

1. Click the **"Generate AI"** button for the row
2. Wait a few seconds while the AI generates:
   - Unique introduction text
   - SEO-optimized meta title
   - Meta description

### Step 3: View Your Page

The virtual page is now accessible at:
- `https://yoursite.com/localseo/kloakmester-dianalund/`
- Or: `https://yoursite.com/service/kloakmester/dianalund/`

## Creating a Custom FSE Template

### Method 1: Using Block Editor

1. Go to **Appearance > Editor**
2. Click **Templates** in the sidebar
3. Click **Add New Template**
4. Name it `single-localseo`
5. Add blocks and bind them to LocalSEO data:

#### Binding Example for Heading Block:
1. Add a Heading block
2. In the block settings sidebar, click **Advanced**
3. Add this to the block's HTML attributes:
   ```
   metadata: {"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}
   ```

### Method 2: Copy Example Template

Copy `templates/block-templates/single-localseo.html` to your theme:
```bash
cp /wp-content/plugins/localseo-booster/templates/block-templates/single-localseo.html \
   /wp-content/themes/YOUR_THEME/templates/
```

Edit the template in your theme to customize.

## Available Block Binding Keys

Use these keys in your block bindings `args`:

| Key | Description | Example Value |
|-----|-------------|---------------|
| `city` | City name | "Dianalund" |
| `zip` | ZIP code | "4293" |
| `service` | Service keyword | "Kloakmester" |
| `intro` | AI-generated intro | "Professional plumbing services..." |
| `meta_title` | SEO title | "Kloakmester in Dianalund" |
| `meta_description` | SEO description | "Get expert plumbing..." |
| `slug` | Custom URL slug | "kloakmester-dianalund" |

## Example Block Template

```html
<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
    <!-- Heading bound to service -->
    <!-- wp:heading {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}} -->
    <h1>Service Name</h1>
    <!-- /wp:heading -->

    <!-- Subheading with city -->
    <!-- wp:paragraph -->
    <p>Professional service in <strong data-binding='{"source":"localseo/data","args":{"key":"city"}}'>City</strong></p>
    <!-- /wp:paragraph -->

    <!-- AI-generated intro -->
    <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"intro"}}}}} -->
    <p>AI-generated introduction will appear here...</p>
    <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->
```

## Bulk Operations

### Generate AI Content for All Rows

1. Add multiple rows in the Data Center
2. Click **"Generate All Missing AI Fields"**
3. The plugin will process all rows that don't have AI content
4. There's a built-in delay (0.5s) between requests to avoid rate limiting

### Import Data (Manual CSV Upload)

While there's no CSV import feature built-in yet, you can:

1. Use the REST API to bulk import
2. Add rows programmatically via PHP
3. Use the Data Center UI for manual entry

## REST API Examples

### Get All Data
```bash
curl -X GET "https://yoursite.com/wp-json/localseo/v1/data" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

### Create New Row
```bash
curl -X POST "https://yoursite.com/wp-json/localseo/v1/data" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{
    "city": "Copenhagen",
    "zip": "1000",
    "service_keyword": "Plumber"
  }'
```

### Generate AI Content
```bash
curl -X POST "https://yoursite.com/wp-json/localseo/v1/generate-ai/1" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

## Customizing the AI Prompt

The default prompt is:
```
You are an SEO expert for a local service company. 
Write a 50-word intro for {service} in {city} ({zip}). 
Focus on local expertise and trust.
```

### Custom Prompt Examples

**For Real Estate:**
```
You are a real estate expert. Write a compelling 50-word description 
highlighting {service} opportunities in {city} ({zip}). 
Emphasize local market knowledge and community benefits.
```

**For Restaurants:**
```
You are a food critic. Write an appetizing 50-word description 
for {service} in {city} ({zip}). 
Focus on authentic flavors and local ingredients.
```

**For Medical Services:**
```
You are a healthcare communications expert. Write a compassionate 
50-word description for {service} in {city} ({zip}). 
Emphasize patient care, expertise, and accessibility.
```

## Troubleshooting

### Pages Return 404

1. Go to **Settings > Permalinks**
2. Click **Save Changes** (this flushes rewrite rules)
3. Try accessing the page again

### AI Generation Fails

1. Check your API key in **LocalSEO > Settings**
2. Verify you have API credits/quota available
3. Check the browser console for error messages
4. Ensure the API provider is correct (OpenAI vs Anthropic)

### Block Bindings Don't Work

1. Ensure WordPress 6.5+ is installed
2. Check that the block has the correct metadata structure
3. Verify the binding key matches available keys
4. Try clearing the block editor cache

### React Admin Not Loading

1. Ensure `build/` directory exists
2. Run `npm run build` again if needed
3. Check browser console for JavaScript errors
4. Clear browser cache

## Best Practices

1. **SEO Optimization**
   - Keep meta titles under 60 characters
   - Keep meta descriptions under 155 characters
   - Use unique content for each location

2. **Performance**
   - Limit bulk AI generation to 10-20 rows at a time
   - Monitor API usage and costs
   - Cache generated content (it's stored in the database)

3. **Content Quality**
   - Review AI-generated content before publishing
   - Customize the system prompt for your industry
   - Edit generated content as needed

4. **URL Structure**
   - Use descriptive slugs
   - Follow a consistent naming pattern
   - Avoid special characters in slugs

## Next Steps

1. Create more location pages
2. Design a custom FSE template
3. Add internal linking between pages
4. Set up tracking (Google Analytics, Search Console)
5. Monitor SEO performance

For more information, see the main PLUGIN_README.md file.
