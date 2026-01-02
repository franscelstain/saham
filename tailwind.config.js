/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
  ],
  safelist: [
    "badge","badge-outline","badge-success","badge-warning","badge-error","badge-info","badge-neutral","badge-ghost",
    "btn","btn-primary","btn-outline","btn-sm","btn-xs",
    "card","card-body","divider",
    "bg-base-100","bg-base-200","bg-base-300","text-base-content","border-base-300",
    "ring-2","ring-primary"
  ],
  theme: { extend: {} },
  plugins: [require("daisyui")],
  daisyui: {
    themes: [
      {
        ajaib: {
          "primary": "#2563eb",
          "primary-content": "#ffffff",
          "secondary": "#0ea5e9",
          "accent": "#22c55e",
          "neutral": "#111827",
          "base-100": "#ffffff",
          "base-200": "#f5f7fb",
          "base-300": "#e5e7eb",
          "info": "#0ea5e9",
          "success": "#16a34a",
          "warning": "#f59e0b",
          "error": "#ef4444",
        }
      }
    ],
  }
}
