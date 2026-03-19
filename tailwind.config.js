import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Arial Nova', 'Arial', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50:  '#e8f5fb',
                    100: '#c5e4f4',
                    200: '#9dd0ea',
                    300: '#6fbcdf',
                    400: '#4aadd7',
                    500: '#1e8fb8',
                    600: '#1a7da3',
                    700: '#14678a',
                    800: '#0f5272',
                    900: '#086a83',
                    950: '#054d61',
                },
            },
            boxShadow: {
                'card': '0 1px 3px 0 rgba(8,106,131,0.08), 0 1px 2px -1px rgba(8,106,131,0.06)',
                'card-md': '0 4px 6px -1px rgba(8,106,131,0.1), 0 2px 4px -2px rgba(8,106,131,0.07)',
            },
        },
    },

    plugins: [forms],
};
