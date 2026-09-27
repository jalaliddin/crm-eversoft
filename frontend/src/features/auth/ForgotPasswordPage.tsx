import { useState } from 'react'
import { Alert, Anchor, Button, Stack, Text, TextInput, Title } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Link } from 'react-router'
import { api } from '@/lib/api'
import { notifyError } from '@/lib/notify'

const schema = z.object({ email: z.string().trim().email('Enter a valid email') })

export default function ForgotPasswordPage() {
  const [sent, setSent] = useState(false)
  const { register, handleSubmit, formState } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) })

  const onSubmit = handleSubmit(async ({ email }) => {
    try {
      await api.post('/auth/forgot-password', { email })
      setSent(true)
    } catch (e) {
      notifyError(e)
    }
  })

  return (
    <form onSubmit={onSubmit} noValidate>
      <Stack gap="md">
        <div>
          <Title order={2}>Reset your password</Title>
          <Text c="dimmed" size="sm">We will email you a link to choose a new password.</Text>
        </div>
        {sent ? (
          <Alert color="teal">If an account uses that address, a reset link is on its way. The link expires in 60 minutes.</Alert>
        ) : (
          <>
            <TextInput label="Email" type="email" autoFocus {...register('email')} error={formState.errors.email?.message} />
            <Button type="submit" loading={formState.isSubmitting} fullWidth>
              Send reset link
            </Button>
          </>
        )}
        <Anchor component={Link} to="/login" size="sm" ta="center">
          Back to sign in
        </Anchor>
      </Stack>
    </form>
  )
}
