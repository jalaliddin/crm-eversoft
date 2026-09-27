import { createTheme, type MantineColorsTuple } from '@mantine/core'

/**
 * Centralized design tokens.
 * Primary "khiva" teal comes from the glazed tilework of Ichan Kala; "saffron" marks lateness and attention.
 * Everything else stays neutral so status colors carry meaning.
 */
const khiva: MantineColorsTuple = ['#e6f6f7', '#c9ebee', '#9dd9de', '#6cc4cc', '#43b0ba', '#2699a4', '#0e7c86', '#0b6770', '#08535a', '#053f45']
const saffron: MantineColorsTuple = ['#fdf6e3', '#faeabd', '#f5da8e', '#f0c85c', '#e8b53a', '#d99a1e', '#b87e14', '#93630f', '#6f4a0b', '#4c3207']
const ink: MantineColorsTuple = ['#c9d1d9', '#a7b1bb', '#7d8893', '#5a6570', '#3a4550', '#26313a', '#1b252d', '#151f27', '#0f161c', '#0a1015']

export const theme = createTheme({
  primaryColor: 'khiva',
  primaryShade: { light: 6, dark: 5 },
  colors: { khiva, saffron, dark: ink },
  fontFamily: 'Onest, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
  headings: {
    fontFamily: 'Onest, system-ui, sans-serif',
    fontWeight: '650',
    sizes: {
      h1: { fontSize: '1.75rem', lineHeight: '1.25' },
      h2: { fontSize: '1.375rem', lineHeight: '1.3' },
      h3: { fontSize: '1.125rem', lineHeight: '1.35' },
      h4: { fontSize: '1rem', lineHeight: '1.4' },
    },
  },
  fontSizes: { xs: '0.75rem', sm: '0.875rem', md: '0.9375rem', lg: '1.0625rem', xl: '1.25rem' },
  defaultRadius: 'md',
  radius: { xs: '4px', sm: '6px', md: '8px', lg: '12px', xl: '16px' },
  cursorType: 'pointer',
  focusRing: 'auto',
  components: {
    Button: { defaultProps: { radius: 'md' } },
    Paper: { defaultProps: { radius: 'lg' } },
    Card: { defaultProps: { radius: 'lg', withBorder: true, padding: 'lg' } },
    Badge: { defaultProps: { radius: 'sm', variant: 'light' }, styles: { label: { textTransform: 'none', fontWeight: 600, letterSpacing: 0 } } },
    TextInput: { defaultProps: { radius: 'md' } },
    Select: { defaultProps: { radius: 'md', allowDeselect: false } },
    Modal: { defaultProps: { radius: 'lg', centered: true, overlayProps: { backgroundOpacity: 0.4, blur: 2 } } },
    Drawer: { defaultProps: { position: 'right', size: 'lg' } },
    Tooltip: { defaultProps: { withArrow: true, openDelay: 250 } },
    Table: { styles: { th: { whiteSpace: 'nowrap' } } },
  },
  other: {
    // Attendance status palette used by badges, calendar tiles and charts.
    status: {
      present: 'teal',
      late: 'saffron',
      early_leave: 'orange',
      absent: 'red',
      incomplete: 'grape',
      on_leave: 'indigo',
      holiday: 'cyan',
      weekend: 'gray',
      working: 'khiva',
      left: 'gray',
      not_arrived: 'gray',
    },
  },
})
