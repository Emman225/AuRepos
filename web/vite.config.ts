/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  server: { port: 5173, strictPort: true },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/tests/preparation.ts'],
    css: false,
    // La suite entière (rendu Ant Design + jsdom) sature parfois les 5 s par défaut sur cette
    // machine ; un test qui échoue par TIMEOUT plutôt que par une vraie assertion n'apprend rien.
    testTimeout: 30000,
  },
})
