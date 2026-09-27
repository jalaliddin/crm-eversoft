/** Tile colors for calendar codes — same meaning as the status badges. */
export const CODE_STYLE: Record<string, { bg: string; fg: string }> = {
  P: { bg: 'var(--mantine-color-teal-light)', fg: 'var(--mantine-color-teal-light-color)' },
  L: { bg: 'var(--mantine-color-saffron-light)', fg: 'var(--mantine-color-saffron-light-color)' },
  A: { bg: 'var(--mantine-color-red-light)', fg: 'var(--mantine-color-red-light-color)' },
  EL: { bg: 'var(--mantine-color-orange-light)', fg: 'var(--mantine-color-orange-light-color)' },
  I: { bg: 'var(--mantine-color-grape-light)', fg: 'var(--mantine-color-grape-light-color)' },
  OL: { bg: 'var(--mantine-color-indigo-light)', fg: 'var(--mantine-color-indigo-light-color)' },
  H: { bg: 'var(--mantine-color-cyan-light)', fg: 'var(--mantine-color-cyan-light-color)' },
  W: { bg: 'transparent', fg: 'var(--mantine-color-dimmed)' },
}
