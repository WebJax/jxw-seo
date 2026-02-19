# LocalSEO Booster - Block Bindings Integration Guide

## What are Block Bindings?

Block Bindings is a WordPress 6.5+ feature that allows you to dynamically inject content into blocks. Instead of using shortcodes, you bind block attributes (like content) to a data source.

## How LocalSEO Booster Uses Block Bindings

The plugin registers a block bindings source called `localseo/data` that provides access to your SEO data (city, service, AI-generated content, etc.).

## Binding Blocks in the Block Editor (GUI Method)

### WordPress 6.7+

1. **Add a block** (Heading, Paragraph, etc.)
2. **Select the block** and open the settings sidebar
3. Click on **Advanced** 
4. Look for **Block Bindings** (if available in your WP version)
5. Select `localseo/data` as the source
6. Enter the key (e.g., `city`, `service`, `intro`)

### Alternative Method (Works in all versions)

Since WordPress doesn't have a GUI for all binding types yet, use the Code Editor:

1. Add your block (Heading, Paragraph, etc.)
2. Switch to **Code Editor** (⋮ → Editor → Code editor)
3. Find your block in the HTML
4. Add the binding metadata

## Block Binding Examples

### Example 1: Heading with Service Name

**Visual Editor:**
Add a Heading block with placeholder text.

**Code Editor:**
```html
<!-- wp:heading {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}} -->
<h2 class="wp-block-heading">Service Name</h2>
<!-- /wp:heading -->
```

**Result:**
The heading will display the service keyword (e.g., "Plumber", "Kloakmester")

### Example 2: Paragraph with AI-Generated Intro

**Code Editor:**
```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"intro"}}}}} -->
<p>AI-generated introduction will appear here...</p>
<!-- /wp:paragraph -->
```

**Result:**
The paragraph will display the AI-generated intro text.

### Example 3: Combined Heading with City and Service

You can't directly combine multiple bindings in one block, but you can use multiple blocks:

```html
<!-- wp:heading -->
<h1>
    <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}} -->
    <span>Service</span>
    <!-- /wp:paragraph -->
    in
    <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"city"}}}}} -->
    <span>City</span>
    <!-- /wp:paragraph -->
</h1>
<!-- /wp:heading -->
```

Or use a single Heading with text:

```html
<!-- wp:heading -->
<h1>Professional Services in 
    <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"city"}}}}} -->
    <span>City</span>
    <!-- /wp:paragraph -->
</h1>
<!-- /wp:heading -->
```

### Example 4: Complete Template

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
    
    <!-- Main heading with service -->
    <!-- wp:heading {"level":1,"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"service"}}}}} -->
    <h1>Service Name</h1>
    <!-- /wp:heading -->

    <!-- Subheading with city -->
    <!-- wp:paragraph {"fontSize":"large"} -->
    <p class="has-large-font-size">Expert service in 
        <strong data-binding='{"source":"localseo/data","args":{"key":"city"}}'>City</strong>, 
        <span data-binding='{"source":"localseo/data","args":{"key":"zip"}}'>ZIP</span>
    </p>
    <!-- /wp:paragraph -->

    <!-- AI-generated introduction -->
    <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"intro"}}}}} -->
    <p>AI-generated intro text will appear here...</p>
    <!-- /wp:paragraph -->

    <!-- Separator -->
    <!-- wp:separator -->
    <hr class="wp-block-separator has-alpha-channel-opacity"/>
    <!-- /wp:separator -->

    <!-- Additional content section -->
    <!-- wp:heading {"level":2} -->
    <h2>Why Choose Us?</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>We are your trusted local experts for 
        <strong data-binding='{"source":"localseo/data","args":{"key":"service"}}'>service</strong> 
        in the 
        <strong data-binding='{"source":"localseo/data","args":{"key":"city"}}'>city</strong> 
        area.
    </p>
    <!-- /wp:paragraph -->

    <!-- Call to action -->
    <!-- wp:buttons -->
    <div class="wp-block-buttons">
        <!-- wp:button -->
        <div class="wp-block-button">
            <a class="wp-block-button__link">Contact Us Today</a>
        </div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
```

## Available Binding Keys

| Key | Field | Description | Example |
|-----|-------|-------------|---------|
| `city` | City name | The city/location | "Copenhagen" |
| `zip` | ZIP code | Postal code | "1000" |
| `service` | Service keyword | The service name | "Plumber" |
| `service_keyword` | Service keyword | Same as `service` | "Plumber" |
| `intro` | AI intro | AI-generated intro text | "Professional plumbing..." |
| `intro_text` | AI intro | Same as `intro` | "Professional plumbing..." |
| `ai_generated_intro` | AI intro | Same as `intro` | "Professional plumbing..." |
| `meta_title` | SEO title | Meta title | "Plumber in Copenhagen" |
| `meta_description` | SEO description | Meta description | "Expert plumbing services..." |
| `slug` | Custom slug | URL slug | "plumber-copenhagen" |

## Creating Templates

### Method 1: Site Editor

1. Go to **Appearance → Editor**
2. Click **Templates** in the sidebar
3. Click **Add New Template**
4. Name it `single-localseo`
5. Design your template with bound blocks
6. Click **Save**

### Method 2: Theme File

Create a file in your theme:
```
wp-content/themes/YOUR_THEME/templates/single-localseo.html
```

The plugin will automatically use this template for LocalSEO pages.

### Method 3: Plugin Template

The plugin includes an example template at:
```
wp-content/plugins/localseo-booster/templates/block-templates/single-localseo.html
```

You can copy this to your theme and customize it.

## Styling Bound Content

You can style bound content just like regular blocks:

```html
<!-- wp:paragraph {"style":{"typography":{"fontSize":"24px"},"color":{"text":"#0066cc"}},"metadata":{"bindings":{"content":{"source":"localseo/data","args":{"key":"city"}}}}} -->
<p style="color:#0066cc;font-size:24px">City Name</p>
<!-- /wp:paragraph -->
```

## Troubleshooting

### Binding Not Working

1. **Check WordPress version**: Requires 6.5+
2. **Verify syntax**: Ensure JSON is valid in metadata
3. **Check key name**: Must match available keys exactly
4. **Clear cache**: Try clearing block editor cache
5. **Check plugin active**: LocalSEO Booster must be activated

### Content Not Updating

1. **Flush rewrite rules**: Settings → Permalinks → Save
2. **Check URL**: Ensure you're on a LocalSEO virtual page
3. **Verify data exists**: Check Data Center for the row

### Metadata Not Saving

1. **Use Code Editor**: Sometimes visual editor doesn't save metadata
2. **Valid JSON**: Ensure metadata JSON is properly formatted
3. **WordPress version**: Update to latest 6.5+

## Advanced: Custom Block Support

To add binding support to custom blocks:

```php
// In your block's block.json
{
  "supports": {
    "bindings": {
      "content": true
    }
  }
}
```

## Testing Your Bindings

1. Create a test row in **LocalSEO → Data Center**
2. Add data: City "TestCity", Service "TestService"
3. Create a template with bindings
4. Visit `/localseo/testservice-testcity/`
5. Verify the content appears correctly

## Best Practices

1. **Always provide fallback text** in your blocks
2. **Test with different data** to ensure responsiveness
3. **Use appropriate block types** (Heading for titles, Paragraph for text)
4. **Keep bindings simple** - one data point per block
5. **Document your templates** with comments

## Next Steps

- Create your first bound template
- Test with sample data
- Style your template with theme.json or custom CSS
- Deploy to production

For more help, see:
- [WordPress Block Bindings API](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-bindings/)
- QUICKSTART.md
- PLUGIN_README.md
