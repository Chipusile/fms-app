/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string
  readonly VITE_CSRF_URL?: string
  readonly VITE_APP_VERSION?: string
  readonly VITE_ENABLE_SOURCEMAPS?: string
  readonly VITE_DEV_PROXY_TARGET?: string
  readonly VITE_DEV_SERVER_PORT?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
