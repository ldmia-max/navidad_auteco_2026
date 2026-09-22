import { defineConfig } from 'vite';

/*
 * El bundle sale a ../assets/game/ con nombres fijos, sin hash: el plugin de
 * WordPress lo encola por ruta y usa NAVIDAD_TVS_VERSION como cache buster.
 * Un hash obligaría a leer un manifiesto desde PHP sin ganar nada.
 */
export default defineConfig({
  base: './',
  build: {
    outDir: '../assets/game',
    emptyOutDir: true,
    target: 'es2020',
    sourcemap: false,
    rollupOptions: {
      input: 'src/main.ts',
      output: {
        entryFileNames: 'juego.js',
        chunkFileNames: 'juego-[name].js',
        assetFileNames: 'juego.[ext]',
      },
    },
  },
});
