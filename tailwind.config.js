/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
		'./admin/**/*.{js,jsx,ts,tsx}',
		'./templates/**/*.{php,html}',
	],
	// Disable Tailwind's CSS reset to avoid conflicts with WordPress admin styles.
	corePlugins: {
		preflight: false,
	},
	theme: {
		extend: {},
	},
	plugins: [],
};
