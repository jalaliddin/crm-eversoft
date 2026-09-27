import { Anchor, Button, PasswordInput, Stack, Text, Title } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { notifySuccess } from '@/lib/notify'

const schema = z
  .object({ password: z.string().min(8, 'Use at least 8 characters'), password_confirmation: z.string() })
  .refine((v) => v.password === v.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match' })

export default function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { register, handleSubmit, formState, setError } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) })

  const onSubmit = handleSubmit(async (values) => {
    try {
      await api.post('/auth/reset-password', { ...values, token: params.get('token'), email: params.get('email') })
      notifySuccess('Password changed. Sign in with your new password.')
      navigate('/login')
    } catch (e) {
      applyServerErrors(e, setError)
    }
  })

  return (
    <form onSubmit={onSubmit} noValidate>
      <Stack gap="md">
        <div>
          <Title order={2}>Choose a new password</Title>
          <Text c="dimmed" size="sm">For {params.get('email')}</Text>
        </div>
        <PasswordInput label="New password" autoComplete="new-password" autoFocus {...register('password')} error={formState.errors.password?.message} />
        <PasswordInput label="Repeat new password" autoComplete="new-password" {...register('password_confirmation')} error={formState.errors.password_confirmation?.message} />
        <Button type="submit" loading={formState.isSubmitting} fullWidth>
          Save password
        </Button>
        <Anchor component={Link} to="/login" size="sm" ta="center">
          Back to sign in
        </Anchor>
      </Stack>
    </form>
  )
}
