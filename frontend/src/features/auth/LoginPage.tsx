import { useState } from 'react'
import { Alert, Anchor, Button, Checkbox, Group, PasswordInput, Stack, Text, TextInput, Title } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Link, useLocation, useNavigate } from 'react-router'
import { IconAlertCircle } from '@tabler/icons-react'
import { ApiError } from '@/lib/api'
import { useAuth } from './useAuth'

const schema = z.object({
  email: z.string().trim().min(1, 'Enter your email').email('Enter a valid email'),
  password: z.string().min(1, 'Enter your password'),
  remember: z.boolean(),
})
type Values = z.infer<typeof schema>

export default function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [error, setError] = useState<string | null>(null)
  const { register, handleSubmit, formState } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '', remember: true } })

  const onSubmit = handleSubmit(async (values) => {
    setError(null)
    try {
      const me = await login(values.email, values.password, values.remember)
      const from = (location.state as { from?: string } | null)?.from
      navigate(from ?? (me.is_platform_user ? '/platform' : '/dashboard'), { replace: true })
    } catch (e) {
      setError(e instanceof ApiError ? (e.firstError('email') ?? e.message) : 'Sign-in failed.')
    }
  })

  return (
    <form onSubmit={onSubmit} noValidate>
      <Stack gap="md">
        <div>
          <Title order={2}>Sign in</Title>
          <Text c="dimmed" size="sm">Use the email your administrator registered for you.</Text>
        </div>
        {error && (
          <Alert color="red" icon={<IconAlertCircle size={18} />} role="alert">
            {error}
          </Alert>
        )}
        <TextInput label="Email" type="email" autoComplete="username" autoFocus {...register('email')} error={formState.errors.email?.message} />
        <PasswordInput label="Password" autoComplete="current-password" {...register('password')} error={formState.errors.password?.message} />
        <Group justify="space-between">
          <Checkbox label="Keep me signed in for 30 days" {...register('remember')} />
          <Anchor component={Link} to="/forgot-password" size="sm">
            Forgot password?
          </Anchor>
        </Group>
        <Button type="submit" loading={formState.isSubmitting} fullWidth size="md">
          Sign in
        </Button>
      </Stack>
    </form>
  )
}
