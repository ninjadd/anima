/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{vue,js,ts,jsx,tsx}',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        anima: {
          50: '#f5f7ff',
          100: '#ebf0fe',
          200: '#dce4fd',
          300: '#c2d0fb',
          400: '#9eb4f7',
          500: '#7590f2',
          600: '#546deb',
          700: '#3e52d6',
          800: '#3443ad',
          900: '#2d3b89',
          950: '#1b2354',
        },
      },
    },
  },
  plugins: [],
};
