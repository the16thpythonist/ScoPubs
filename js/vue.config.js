const devPort = 8001;

// All these settings are required to support the hot reloading of the Vue frontend.
// These were taken from a Medium article:
// https://devs-group.medium.com/wordpress-vue-js-with-webpack-and-hot-reload-7c4faea9d0d9
module.exports = {
    devServer: {
        hot: true,
        writeToDisk: true,
        liveReload: false,
        sockPort: devPort,
        port: devPort,
        headers: { "Access-Control-Allow-Origin": "*" }
    },
    publicPath:
        process.env.NODE_ENV === "production"
        ? process.env.ASSET_PATH || "/"
        : `http:localhost:${devPort}/`,
    configureWebpack: {
        output: {
            filename: "scopubs-frontend.js",
            hotUpdateChunkFilename: "hot/hot-update.js",
            hotUpdateMainFilename: "hot/hot-update.json",
        },
        optimization: {
            splitChunks: false
        }
    },
    filenameHashing: true,
    css: {
        extract: {
            filename: "scopubs-frontend.css"
        }
    }
}