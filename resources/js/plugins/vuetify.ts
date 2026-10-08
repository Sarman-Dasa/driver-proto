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
                    secondary: '#06B6D4', // Cyan 500
                    accent: '#8B5CF6', // Violet 500
                    success: '#10B981', // Emerald 500
                    warning: '#F59E0B', // Amber 500
                    error: '#EF4444', // Red 500
                    info: '#3B82F6', // Blue 500
                    background: '#F8FAFC', // Slate 50
                    surface: '#FFFFFF',
                },
            },
            dark: {
                colors: {
                    primary: '#6366F1',
                    secondary: '#22D3EE',
                    accent: '#A78BFA',
                    success: '#34D399',
                    warning: '#FBBF24',
                    error: '#F87171',
                    info: '#60A5FA',
                    background: '#0F172A',
                    surface: '#1E293B',
                },
            },
        },
    },
});
