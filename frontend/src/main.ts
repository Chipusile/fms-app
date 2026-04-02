import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import { reportClientError } from './lib/monitoring'
import router from './router'

const app = createApp(App)

app.config.errorHandler = (error, instance, info) => {
  reportClientError(error, {
    source: 'vue',
    info,
    component: typeof instance?.$options === 'object' ? instance.$options.name : undefined,
  })
}

window.addEventListener('error', (event) => {
  reportClientError(event.error ?? event.message, {
    source: 'window',
    filename: event.filename,
    lineno: event.lineno,
    colno: event.colno,
  })
})

window.addEventListener('unhandledrejection', (event) => {
  reportClientError(event.reason, {
    source: 'promise',
  })
})

app.use(createPinia())
app.use(router)

app.mount('#app')
