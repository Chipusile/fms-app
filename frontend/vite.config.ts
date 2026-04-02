import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv, type PluginOption } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const proxyTarget = env.VITE_DEV_PROXY_TARGET || 'http://localhost:8000'
  const serverPort = Number(env.VITE_DEV_SERVER_PORT || 5173)
  const enableSourceMaps = env.VITE_ENABLE_SOURCEMAPS === 'true'
  const isProduction = mode === 'production'

  return {
    plugins: [
      vue(),
      !isProduction && vueDevTools(),
      tailwindcss(),
    ].filter(Boolean) as PluginOption[],
    build: {
      manifest: true,
      sourcemap: enableSourceMaps,
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes('/node_modules/vue-echarts/')) {
              return 'analytics-vue'
            }

            if (id.includes('/node_modules/zrender/')) {
              return 'analytics-zrender'
            }

            if (id.includes('/node_modules/echarts/')) {
              return 'analytics-echarts'
            }

            if (
              id.includes('/node_modules/vue/')
              || id.includes('/node_modules/@vue/')
              || id.includes('/node_modules/vue-router/')
              || id.includes('/node_modules/pinia/')
            ) {
              return 'framework'
            }

            if (id.includes('/node_modules/axios/')) {
              return 'network'
            }

            if (id.includes('/node_modules/@vueuse/')) {
              return 'utilities'
            }

            if (id.includes('node_modules')) {
              return 'vendor'
            }

            return undefined
          },
        },
      },
    },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: serverPort,
      strictPort: true,
      host: true,
      proxy: {
        '/api': {
          target: proxyTarget,
          changeOrigin: true,
        },
        '/sanctum': {
          target: proxyTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
