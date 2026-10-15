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
            colors: {
                indigo: { 50:'#e8f5ec',100:'#d0ebd9',200:'#a3d7b4',300:'#6fbd88',400:'#3fa361',500:'#1a8a3c',600:'#1a8a3c',700:'#14702f',800:'#105a26',900:'#0c451d' },
                brand: { DEFAULT: '#1a8a3c', dark: '#14702f', gold: '#e9a400', soft: '#e8f5ec' },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
