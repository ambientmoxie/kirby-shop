import { defineConfig } from "vite";
import fullReload from "vite-plugin-full-reload";

export default defineConfig({
    plugins: [fullReload(["{site,content,public}/**/*.php"])],
    server: {
        host: "0.0.0.0",
        cors: true,
    },
    publicDir: false,
    build: {
        outDir: "public/build",
        assetsDir: "",
        rolldownOptions: {
            input: "assets/js/main.js",
        },
        manifest: true,
        emptyOutDir: true,
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: "modern-compiler",
            },
        },
    },
});
