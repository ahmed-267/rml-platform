import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
        './resources/js/**/*.ts',
    ],

    theme: {
        extend: {
            colors: {
                rml: {
                    primary: '#16a34a',
                    'primary-light': '#dcfce7',
                    'primary-lighter': '#f0fdf4',
                    sidebar: '#0f172a',
                    blue: '#2563eb',
                    'blue-light': '#eff6ff',
                    amber: '#d97706',
                    red: '#dc2626',
                    text: '#111827',
                    muted: '#6b7280',
                    border: '#e5e7eb',
                    background: '#f8fafc',
                    card: '#ffffff',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['IBM Plex Mono', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(16 24 40 / 0.05)',
                dropdown: '0 12px 16px -4px rgb(16 24 40 / 0.08), 0 4px 6px -2px rgb(16 24 40 / 0.03)',
            },
            maxWidth: {
                content: '1280px',
            },
            keyframes: {
                'rml-float': {
                    '0%, 100%': { transform: 'translateY(0px)' },
                    '50%': { transform: 'translateY(-10px)' },
                },
            },
            animation: {
                'rml-float': 'rml-float 5s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
