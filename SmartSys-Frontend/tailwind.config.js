/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  // Safelist dynamic classes that are built via string concatenation
  // (e.g. `bg-${color}-500`) so they are not purged.
  safelist: [
    // Status badge colors used dynamically
    { pattern: /^(bg|text|border)-(red|green|yellow|blue|amber|slate|gray|indigo|purple|rose|emerald)-(50|100|200|400|500|600|700|800)$/ },
    // Animation classes added programmatically
    'animate-fadeIn',
    'animate-fadeInUp',
    'animate-pulse',
    'animate-spin',
    'animate-zoomIn',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Cairo', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
      },
      animation: {
        fadeIn:  'fadeIn 0.15s ease',
        zoomIn:  'zoomIn 0.15s ease',
      },
      keyframes: {
        fadeIn: { from: { opacity: '0' }, to: { opacity: '1' } },
        zoomIn: { from: { opacity: '0', transform: 'scale(0.95)' }, to: { opacity: '1', transform: 'scale(1)' } },
      },
    },
  },
  plugins: [],
}