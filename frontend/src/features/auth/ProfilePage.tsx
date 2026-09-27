import { Avatar, Badge, Button, Card, FileButton, Grid, Group, Stack, Table, Text, Title } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader'
import { FormField } from '@/components/FormField'
import { confirmAction } from '@/components/ConfirmDialog'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { fmtDateTime, fromNow, initials } from '@/lib/format'
import { notifySuccess, notifyError } from '@/lib/notify'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from './useAuth'

const profileSchema = z.object({
  name: z.string().trim().min(1, 'Required'),
  email: z.string().trim().email('Enter a valid email'),
  phone: z.string().nullable(),
  locale: z.string().nullable(),
})

const passwordSchema = z
  .object({
    current_password: z.string().min(1, 'Required'),
    password: z.string().min(8, 'Use at least 8 characters'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match' })

interface Session {
  id: number
  name: string
  last_used_at: string | null
  created_at: string
  expires_at: string | null
  is_current: boolean
}

export default function ProfilePage() {
  const { me, refresh } = useAuth()
  const user = me!.user

  const profile = useForm<z.infer<typeof profileSchema>>({
    resolver: zodResolver(profileSchema),
    defaultValues: { name: user.name, email: user.email, phone: user.phone, locale: user.locale },
  })
  const password = useForm<z.infer<typeof passwordSchema>>({ resolver: zodResolver(passwordSchema), defaultValues: { current_password: '', password: '', password_confirmation: '' } })
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: () => api.get<Session[]>('/profile/sessions') })
  const revoke = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/profile/sessions/${id}`), invalidate: [['sessions']] })
  const revokeOthers = useApiMutation({ mutationFn: () => api.delete('/profile/sessions'), invalidate: [['sessions']] })

  const saveProfile = profile.handleSubmit(async (values) => {
    try {
      await api.put('/profile', values)
      await refresh()
      notifySuccess('Profile saved.')
    } catch (e) {
      applyServerErrors(e, profile.setError)
    }
  })

  const savePassword = password.handleSubmit(async (values) => {
    try {
      await api.put('/profile/password', values)
      password.reset()
      void sessions.refetch()
      notifySuccess('Password changed. Other sessions were signed out.')
    } catch (e) {
      applyServerErrors(e, password.setError)
    }
  })

  const uploadAvatar = async (file: File | null) => {
    if (!file) return
    try {
      await api.upload('/profile/avatar', 'avatar', file)
      await refresh()
      notifySuccess('Photo updated.')
    } catch (e) {
      notifyError(e)
    }
  }

  return (
    <>
      <PageHeader title="Your profile" description="How you appear to colleagues, and how you sign in." />
      <Grid gap="lg">
        <Grid.Col span={{ base: 12, lg: 7 }}>
          <Stack gap="lg">
            <Card>
              <Group gap="lg" mb="md">
                <Avatar src={user.avatar_url} size={72} radius="xl" color="khiva">
                  {initials(user.name)}
                </Avatar>
                <Stack gap={6}>
                  <Group gap="xs">
                    <FileButton onChange={uploadAvatar} accept="image/png,image/jpeg,image/webp">
                      {(props) => <Button variant="default" size="xs" {...props}>Upload photo</Button>}
                    </FileButton>
                    {user.avatar_url && (
                      <Button variant="subtle" color="red" size="xs" onClick={async () => { await api.delete('/profile/avatar'); await refresh() }}>
                        Remove
                      </Button>
                    )}
                  </Group>
                  <Text size="xs" c="dimmed">JPG, PNG or WebP, up to 2 MB.</Text>
                </Stack>
              </Group>
              <form onSubmit={saveProfile}>
                <Stack gap="sm">
                  <FormField control={profile.control} name="name" label="Full name" required />
                  <FormField control={profile.control} name="email" label="Email" kind="email" required />
                  <FormField control={profile.control} name="phone" label="Phone" />
                  <FormField
                    control={profile.control}
                    name="locale"
                    label="Language"
                    kind="select"
                    clearable
                    data={[{ value: 'en', label: 'English' }, { value: 'ru', label: 'Русский' }, { value: 'uz', label: "O'zbekcha" }]}
                  />
                  <Group justify="flex-end">
                    <Button type="submit" loading={profile.formState.isSubmitting}>Save profile</Button>
                  </Group>
                </Stack>
              </form>
            </Card>

            <Card>
              <Title order={3} mb="sm">Change password</Title>
              <form onSubmit={savePassword}>
                <Stack gap="sm">
                  <FormField control={password.control} name="current_password" label="Current password" kind="password" />
                  <FormField control={password.control} name="password" label="New password" kind="password" />
                  <FormField control={password.control} name="password_confirmation" label="Repeat new password" kind="password" />
                  <Group justify="flex-end">
                    <Button type="submit" loading={password.formState.isSubmitting}>Change password</Button>
                  </Group>
                </Stack>
              </form>
            </Card>
          </Stack>
        </Grid.Col>

        <Grid.Col span={{ base: 12, lg: 5 }}>
          <Card>
            <Group justify="space-between" mb="sm">
              <Title order={3}>Signed-in devices</Title>
              {(sessions.data?.length ?? 0) > 1 && (
                <Button
                  size="xs"
                  variant="light"
                  color="red"
                  onClick={() => confirmAction({ title: 'Sign out other devices?', message: 'Every session except this one will need to sign in again.', confirmLabel: 'Sign out others', danger: true, onConfirm: () => revokeOthers.mutate() })}
                >
                  Sign out others
                </Button>
              )}
            </Group>
            <Table verticalSpacing="xs">
              <Table.Tbody>
                {sessions.data?.map((s) => (
                  <Table.Tr key={s.id}>
                    <Table.Td>
                      <Text size="sm" lineClamp={1} title={s.name}>{s.name}</Text>
                      <Text size="xs" c="dimmed">
                        Active {fromNow(s.last_used_at ?? s.created_at)} · expires {fmtDateTime(s.expires_at)}
                      </Text>
                    </Table.Td>
                    <Table.Td ta="right">
                      {s.is_current ? (
                        <Badge>This device</Badge>
                      ) : (
                        <Button size="compact-xs" variant="subtle" color="red" onClick={() => revoke.mutate(s.id)}>
                          Sign out
                        </Button>
                      )}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Card>
        </Grid.Col>
      </Grid>
    </>
  )
}
