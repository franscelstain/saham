/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
  ],
  // Keep this small and intentional. Only safelist classes that are applied dynamically from JS.
  safelist: [
    // badges are applied by Tabulator formatters in JS
    'badge','badge-outline','badge-success','badge-warning','badge-error','badge-info','badge-neutral','badge-ghost',
  ],
  theme: { extend: {} },
  plugins: [require('daisyui')],
  daisyui: {
    themes: [
      {
        // Soft-light theme: not "pure white", avoids washed-out UI but stays light.
        ajaib: {
          primary: '#2563eb',
          'primary-content': '#ffffff',

          secondary: '#0ea5e9',
          'secondary-content': '#02131f',

          accent: '#16a34a',
          'accent-content': '#04120a',

          neutral: '#111827',
          'neutral-content': '#f9fafb',

          'base-100': '#f7f8fb',
          'base-200': '#eef1f6',
          'base-300': '#e2e8f0',
          'base-content': '#111827',

          info: '#0284c7',
          success: '#16a34a',
          warning: '#d97706',
          error: '#dc2626',
        },
      },
    ],
  },
};
