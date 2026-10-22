// HTTPS dev server untuk uji geolocation.
// Geomifikasi: browser hanya mengizinkan navigator.geolocation pada secure context
// (https:// atau http://localhost). Server ini melayani aplikasi Laravel lewat HTTPS
// dan meneruskan request ke `php artisan serve` di 127.0.0.1:8000.
//
// Jalankan: node tools/dev-https.js
// Buka   : https://localhost:8443

const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const https = require('node:https');

const LISTEN_PORT = Number(process.env.HTTPS_PORT || 8443);
const TARGET_HOST = process.env.TARGET_HOST || '127.0.0.1';
const TARGET_PORT = Number(process.env.TARGET_PORT || 8000);

const certDir = process.env.CERT_DIR || 'D:\\laragon\\etc\\ssl\\pancor';
const keyPath = path.join(certDir, 'localhost.key');
const certPath = path.join(certDir, 'localhost.crt');

for (const file of [keyPath, certPath]) {
    if (!fs.existsSync(file)) {
        console.error(`Sertifikat tidak ditemukan: ${file}`);
        console.error('Buat dulu dengan openssl (lihat docs), atau set CERT_DIR.');
        process.exit(1);
    }
}

const agent = new http.Agent({ keepAlive: true, maxSockets: 64 });

function proxy(req, res) {
    const headers = { ...req.headers, host: `${TARGET_HOST}:${TARGET_PORT}` };
    headers['x-forwarded-proto'] = 'https';
    headers['x-forwarded-for'] = req.socket.remoteAddress || '';
    // X-Forwarded-Host asli supaya Laravel bisa membuat URL https yang benar.
    headers['x-forwarded-host'] = req.headers.host || `localhost:${LISTEN_PORT}`;
    delete headers['x-forwarded-port'];
    headers['x-forwarded-port'] = String(LISTEN_PORT);

    const upstream = http.request(
        { host: TARGET_HOST, port: TARGET_PORT, method: req.method, path: req.url, headers, agent },
        (upRes) => {
            res.writeHead(upRes.statusCode || 502, upRes.headers);
            upRes.pipe(res);
        },
    );

    upstream.on('error', (err) => {
        res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end(`Gagal meneruskan ke ${TARGET_HOST}:${TARGET_PORT} (${err.message}).\n` +
            'Pastikan `php artisan serve --host=127.0.0.1 --port=8000` sedang berjalan.');
    });

    req.pipe(upstream);
}

const BIND_HOST = process.env.HTTPS_HOST || '0.0.0.0';

const server = https.createServer(
    { key: fs.readFileSync(keyPath), cert: fs.readFileSync(certPath) },
    proxy,
);

server.listen(LISTEN_PORT, BIND_HOST, () => {
    console.log(`HTTPS dev server siap: https://localhost:${LISTEN_PORT}`);
    console.log(`Meneruskan ke http://${TARGET_HOST}:${TARGET_PORT}`);
    console.log('Peringatan: sertifikat self-signed. Klik "Advanced > Proceed" di browser.');
});
