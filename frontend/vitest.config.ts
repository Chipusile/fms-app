import crypto from 'node:crypto'
import { mergeConfig } from 'vite'
import { configDefaults, defineConfig } from 'vitest/config'
import viteConfig from './vite.config'

type HashEncoding = crypto.BinaryToTextEncoding | 'buffer'

if (!('hash' in crypto)) {
  Object.assign(crypto, {
    hash(algorithm: string, data: crypto.BinaryLike, outputEncoding?: HashEncoding) {
      const digest = crypto.createHash(algorithm).update(data)

      if (!outputEncoding || outputEncoding === 'buffer') {
        return digest.digest()
      }

      return digest.digest(outputEncoding)
    },
  })
}

const resolvedViteConfig = typeof viteConfig === 'function'
  ? viteConfig({ command: 'serve', mode: 'test', isSsrBuild: false })
  : viteConfig

export default mergeConfig(resolvedViteConfig, defineConfig({
  test: {
    environment: 'jsdom',
    pool: 'threads',
    fileParallelism: false,
    maxWorkers: 1,
    isolate: false,
    teardownTimeout: 30000,
    setupFiles: ['./src/test/setup.ts'],
    exclude: [
      ...configDefaults.exclude,
      'e2e/**',
    ],
  },
}))
