import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Add Tubigon colors right here!
            colors: {
                tubigon: {
                    DEFAULT: '#003f7f',
                    hover: '#002d5c',
                    light: '#f0f5fa',
                }
            }
        },
    },

    plugins: [forms],
};
