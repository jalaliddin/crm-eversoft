import { Badge, type BadgeProps } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { humanize } from '@/lib/format'
import { COLORS } from '@/lib/statusColors'


interface Props extends Omit<BadgeProps, 'children' | 'color'> {
  status?: string | null
  label?: string
}

export function StatusBadge({ status, label, ...props }: Props) {
  const { t, i18n } = useTranslation()
  if (!status) return <Badge color="gray" {...props}>—</Badge>

  const text = label ?? (i18n.exists(`status.${status}`) ? t(`status.${status}`) : humanize(status))

  return (
    <Badge color={COLORS[status] ?? 'gray'} {...props}>
      {text}
    </Badge>
  )
}

