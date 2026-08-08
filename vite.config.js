import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig(({ mode }) => {
  const isProduction = mode === 'production';

  return {
    plugins: [react()],
    clearScreen: false,
    build: {
      outDir: 'assets',
      emptyOutDir: false,
      sourcemap: !isProduction,
      minify: isProduction ? 'esbuild' : false,
      rollupOptions: {
        input: {
          builder: resolve(__dirname, 'builder/main.jsx'),
        },
        output: {
          entryFileNames: 'js/[name].js',
          chunkFileNames: 'js/[name].js',
          assetFileNames: (assetInfo) => {
            if (assetInfo.name.endsWith('.css')) {
              return 'css/[name][extname]';
            }

            return '[name][extname]';
          }
        },
      },
    },
    resolve: {
      alias: {
        '@': resolve(__dirname, 'builder'),
      },
    },
    server: {
      port: 3000,
      strictPort: false,
    },
  };
});
