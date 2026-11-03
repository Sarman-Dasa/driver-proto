import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

export default createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                colors: {
                    primary: '#4F46E5', // Indigo 600
                    'primary-darken-1': '#4338CA',
                    secondary: '#0EA5E9', // Sky 500
                    accent: '#8B5CF6', // Violet 500
                    success: '#10B981', // Emerald 500
                    warning: '#F59E0B', // Amber 500
                    error: '#EF4444', // Red 500
                    info: '#3B82F6', // Blue 500
                    background: '#F8FAFC', // Slate 50
                    surface: '#FFFFFF',
                    'surface-variant': '#F1F5F9', // Slate 100
                    'on-surface-variant': '#64748B', // Slate 500
                },
            },
            dark: {
                colors: {
                    primary: '#6366F1',
                    'primary-darken-1': '#4F46E5',
                    secondary: '#38BDF8',
                    accent: '#A78BFA',
                    success: '#34D399',
                    warning: '#FBBF24',
                    error: '#F87171',
                    info: '#60A5FA',
                    background: '#0F172A',
                    surface: '#1E293B',
                    'surface-variant': '#334155',
                    'on-surface-variant': '#94A3B8',
                },
            },
        },
    },
    defaults: {
        VCard: {
            elevation: 0,
            rounded: 'xl',
        },
        VBtn: {
            rounded: 'lg',
            elevation: 0,
        },
        VTextField: {
            variant: 'outlined',
            density: 'comfortable',
            color: 'primary',
        },
        VSelect: {
            variant: 'outlined',
            density: 'comfortable',
            color: 'primary',
        },
        VAutocomplete: {
            variant: 'outlined',
            density: 'comfortable',
            color: 'primary',
        },
        VTextarea: {
            variant: 'outlined',
            density: 'comfortable',
            color: 'primary',
        },
        VChip: {
            rounded: 'lg',
        },
        VAlert: {
            rounded: 'xl',
        },
    },
});
