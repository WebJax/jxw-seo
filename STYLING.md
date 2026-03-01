# Styling Guide

This document describes the Tailwind CSS setup and conventions used in the
**LocalSEO Booster** plugin (non-Gutenberg areas).

---

## Overview

Tailwind CSS (v3 LTS) is integrated into the admin build pipeline via PostCSS.  
**Gutenberg blocks are explicitly excluded** – they continue to use the
WordPress block-editor stylesheet and any custom block CSS they already have.

---

## Setup

| File | Purpose |
|------|---------|
| `tailwind.config.js` | Content paths (admin + templates only), preflight disabled |
| `postcss.config.js` | PostCSS pipeline: `postcss-import` → `tailwindcss` → `autoprefixer` |
| `admin/style.css` | Tailwind directives + thin CSS complement for selectors that cannot be expressed as utilities |

### Why `preflight: false`?

Tailwind's [Preflight](https://tailwindcss.com/docs/preflight) resets browser
defaults (margins, headings, lists…).  In a WordPress admin page those defaults
are already set by `wp-admin.css`.  Enabling Preflight would override WordPress
styles in unpredictable ways, so it is disabled.

### PostCSS config replaces `@wordpress/scripts` defaults

When a `postcss.config.js` file exists, `@wordpress/scripts` uses it instead of
its own preset (`postcss-import` + `autoprefixer`).  Our config reproduces those
two plugins and adds `tailwindcss` between them so the processing order is:

```
postcss-import → tailwindcss → autoprefixer
```

---

## Conventions

### Use Tailwind utilities directly in JSX

Prefer Tailwind utility classes in `className` props over separate CSS rules:

```jsx
// ✅ Good
<div className="flex items-center gap-2 mb-5">

// ❌ Avoid
<div className="my-custom-toolbar">   // and a .my-custom-toolbar { … } rule
```

### Keep custom CSS only for edge cases

The `admin/style.css` file now intentionally contains only:

1. `@tailwind` directives  
2. **Focus styles** for table inputs/textareas (pseudo-class selectors that
   cannot be inlined in JSX)  
3. **Column `min-width` rules** using `nth-child` selectors (not expressible
   as static utility classes on dynamically-rendered `<th>` elements)  
4. **WordPress component overrides** where a `!important` override is
   unavoidable (e.g., compact sizing for `Button` inside table cells)

### Avoid `style={{}}` props

Replace inline `style` objects with Tailwind classes unless the value is
genuinely dynamic (e.g., a calculated percentage width for a progress bar):

```jsx
// ✅ Good – static layout value → Tailwind class
<div className="flex gap-2.5 mt-4">

// ✅ Acceptable – dynamic/calculated value → keep inline style
<div style={{ width: `${pct}%` }} className="h-full bg-[#2271b1]" />

// ❌ Avoid – static value expressed as inline style
<div style={{ display: 'flex', gap: '10px' }}>
```

### Custom colour values

WordPress admin uses its own colour palette.  Where a colour doesn't map to a
Tailwind default, use an [arbitrary value](https://tailwindcss.com/docs/adding-custom-styles#using-arbitrary-values):

```jsx
className="text-[#8c8f94]"   // WP secondary text
className="bg-[#2271b1]"     // WP brand blue
className="border-[#ccc]"    // WP border grey
```

### Gutenberg blocks

Do **not** use Tailwind utilities inside any file under `src/blocks/` or any
file that is loaded in the block editor.  Those files use WordPress / Gutenberg
component styles only.

---

## Build commands

```bash
# Development watch mode
npm run start

# Production build
npm run build

# Lint JS
npm run lint:js

# Lint CSS
npm run lint:css
```

The production build outputs to `build/`.  `build/` is in `.gitignore` and is
not committed to the repository.
