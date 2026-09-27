import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [react()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      host: true,
      port: 5173,
      proxy: {
        // Dev: forward API calls to Laravel (php artisan serve, or the Docker nginx).
        '/api': { target: env.VITE_DEV_API_PROXY ?? 'http://localhost:8000', changeOrigin: true },
        '/storage': { target: env.VITE_DEV_API_PROXY ?? 'http://localhost:8000', changeOrigin: true },
      },
    },
    build: {
      sourcemap: false,
      chunkSizeWarningLimit: 900,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes('node_modules')) {
              if (id.includes('recharts') || id.includes('d3-') || id.includes('@mantine/charts')) return 'charts'
              if (id.includes('@mantine')) return 'mantine'
              if (id.includes('@tabler/icons')) return 'icons'
              if (id.includes('react-router') || id.includes('react-dom') || id.includes('/react/') || id.includes('scheduler')) return 'react'
              return 'vendor'
            }
          },
        },
      },
    },
  }
})
