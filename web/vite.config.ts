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
    // Un test qui échoue par TIMEOUT plutôt que par une vraie assertion n'apprend rien, et les
    // tests d'interaction (modales Ant Design dans jsdom) sont LENTS, pas bloqués : mesuré sur
    // cette machine, la suite réévalue 273 modules 6 811 fois, soit ~1 220 s rien qu'en imports,
    // la moitié du temps total. Les mêmes fichiers passent en 63 s lancés seuls et dépassaient
    // les 30 s uniquement en concurrence avec les 34 autres.
    //
    // La cause racine est le coût d'import par fichier, mais `isolate: false` — la parenthèse
    // que vitest suggère lui-même — casse 49 tests : plusieurs fichiers s'appuient sur un état
    // de module réinitialisé à chaque fichier. Mesuré, pas supposé. On garde donc l'isolation,
    // qui est correcte, et on donne au délai la marge que la machine réclame.
    testTimeout: 60000,
  },
})
