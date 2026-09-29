import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Navy from the Philsaga logo used by the legacy OSTR app.
                brand: {
                    50: '#eef3fb',
                    100: '#d5e0f3',
                    200: '#aec2e6',
                    300: '#7f9dd4',
                    500: '#2f5aa8',
                    600: '#1f4488',
                    700: '#15356e',
                    800: '#0b2a5f',
                    900: '#042358',
                },
            },
        },
    },

    plugins: [forms],
};
