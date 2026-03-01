/**
 * PostCSS configuration.
 *
 * When a postcss.config.js is present @wordpress/scripts uses it instead of
 * its built-in preset.  We reproduce the two default plugins
 * (postcss-import and autoprefixer) and add tailwindcss so that Tailwind
 * utility classes are generated for the admin build.
 *
 * Gutenberg blocks are NOT processed through this configuration because they
 * have their own separate build entry-points that do not import admin/style.css.
 */
module.exports = {
	plugins: {
		'postcss-import': {},
		tailwindcss: {},
		autoprefixer: {},
	},
};
