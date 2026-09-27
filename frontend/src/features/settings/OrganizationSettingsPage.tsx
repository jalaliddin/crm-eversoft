import { useEffect } from 'react'
import { Avatar, Button, Card, Checkbox, FileButton, Group, SimpleGrid, Stack, Text, Title } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { notifyError, notifySuccess } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { FormField } from '@/components/FormField'
import { LoadingState } from '@/components/LoadingState'
import { useAuth } from '@/features/auth/useAuth'
import type { Organization, OrganizationSettings } from '@/types/models'

interface Values {
  name: string
  timezone: string
  currency: string
  locale: string
  settings: OrganizationSettings
}

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const TIMEZONES = ['Asia/Tashkent', 'Asia/Samarkand', 'Asia/Almaty', 'Asia/Bishkek', 'Asia/Dushanbe', 'Europe/Moscow', 'Europe/Istanbul', 'Asia/Dubai', 'UTC']

export default function OrganizationSettingsPage() {
  const { can, refresh } = useAuth()
  const readOnly = !can('settings.manage')
  const { data, isLoading, refetch } = useQuery({ queryKey: ['settings', 'organization'], queryFn: () => api.get<Organization>('/settings/organization') })
  const form = useForm<Values>()

  useEffect(() => {
    if (data) form.reset({ name: data.name, timezone: data.timezone, currency: data.currency, locale: data.locale, settings: data.settings })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data])

  if (isLoading || !data) return <LoadingState />

  const submit = form.handleSubmit(async (values) => {
    try {
      await api.put('/settings/organization', values)
      await Promise.all([refetch(), refresh()])
      notifySuccess('Settings saved.')
    } catch (e) {
      applyServerErrors(e, form.setError)
    }
  })

  const uploadLogo = async (file: File | null) => {
    if (!file) return
    try {
      await api.upload('/settings/organization/logo', 'logo', file)
      await Promise.all([refetch(), refresh()])
      notifySuccess('Logo updated.')
    } catch (e) {
      notifyError(e)
    }
  }

  return (
    <form onSubmit={submit}>
      <PageHeader
        title="Organization"
        description="Applies to everyone in your organization."
        actions={!readOnly && <Button type="submit" loading={form.formState.isSubmitting}>Save settings</Button>}
      />
      <fieldset disabled={readOnly} style={{ border: 0, padding: 0, margin: 0 }}>
        <Stack gap="lg" maw={960}>
          <Card>
            <Title order={3} fz="md" mb="md">General</Title>
            <Group mb="md" gap="md">
              <Avatar src={data.logo_url} size={56} radius="md" color="khiva">{data.name.slice(0, 1)}</Avatar>
              {!readOnly && (
                <FileButton onChange={uploadLogo} accept="image/png,image/jpeg,image/webp">
                  {(props) => <Button {...props} variant="default" size="xs">Upload logo</Button>}
                </FileButton>
              )}
            </Group>
            <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
              <FormField control={form.control} name="name" label="Organization name" required />
              <FormField control={form.control} name="timezone" label="Timezone" kind="select" searchable data={TIMEZONES} description="Attendance and reports use this timezone." />
              <FormField control={form.control} name="currency" label="Currency" kind="select" data={['UZS', 'USD', 'EUR', 'RUB', 'KZT']} />
              <FormField control={form.control} name="locale" label="Default language" kind="select" data={[{ value: 'en', label: 'English' }, { value: 'ru', label: 'Русский' }, { value: 'uz', label: "O'zbekcha" }]} />
              <FormField control={form.control} name="settings.date_format" label="Date format" kind="select" data={['DD.MM.YYYY', 'YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY']} />
              <FormField control={form.control} name="settings.time_format" label="Time format" kind="select" data={[{ value: 'HH:mm', label: '24-hour (17:30)' }, { value: 'hh:mm A', label: '12-hour (05:30 PM)' }]} />
            </SimpleGrid>
            <Text size="sm" fw={500} mt="md" mb={6}>Work week</Text>
            <Controller
              control={form.control}
              name="settings.work_week"
              render={({ field }) => (
                <Group gap="md">
                  {DAYS.map((label, i) => (
                    <Checkbox
                      key={label}
                      label={label}
                      checked={(field.value ?? []).includes(i + 1)}
                      onChange={(e) => field.onChange(e.currentTarget.checked ? [...(field.value ?? []), i + 1].sort() : (field.value ?? []).filter((d) => d !== i + 1))}
                    />
                  ))}
                </Group>
              )}
            />
            <Text size="xs" c="dimmed" mt={4}>Used for employees without a work schedule.</Text>
          </Card>

          <Card>
            <Title order={3} fz="md" mb="md">Attendance</Title>
            <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
              <FormField control={form.control} name="settings.attendance.default_grace_minutes" label="Default grace period for new schedules" kind="number" suffix=" min" min={0} max={240} />
              <FormField control={form.control} name="settings.attendance.duplicate_punch_window_seconds" label="Ignore repeated scans within" kind="number" suffix=" s" min={0} max={3600} description="A second scan this soon after the first is not a check-out." />
              <FormField control={form.control} name="settings.attendance.monitor_refresh_seconds" label="Monitor refresh" kind="number" suffix=" s" min={10} max={600} />
              <FormField control={form.control} name="settings.attendance.auto_mark_absent" label="Mark missing employees absent when the day closes" kind="switch" />
            </SimpleGrid>
          </Card>

          <Card>
            <Title order={3} fz="md" mb="md">Payroll & penalties</Title>
            <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
              <FormField control={form.control} name="settings.payroll.monthly_mode" label="Monthly salary" kind="select" data={[
                { value: 'fixed', label: 'Full salary; absences via penalty rules' },
                { value: 'prorated', label: 'Prorated by days worked' },
              ]} />
              <FormField control={form.control} name="settings.payroll.overtime_rate_multiplier" label="Overtime rate" kind="number" min={1} max={5} step={0.25} decimalScale={2} suffix="×" />
              <FormField control={form.control} name="settings.payroll.overtime_pay_enabled" label="Pay overtime" kind="switch" />
              <FormField control={form.control} name="settings.payroll.count_incomplete_as_worked" label="Count days without check-out as worked" kind="switch" />
              <FormField control={form.control} name="settings.penalties.auto_approve" label="Approve automatic penalties without review" kind="switch" />
            </SimpleGrid>
          </Card>
        </Stack>
      </fieldset>
    </form>
  )
}
