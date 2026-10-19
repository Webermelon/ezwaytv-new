'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { WebSocket, WebSocketServer } = require('ws');

const DEFAULT_HOST = '127.0.0.1';
const DEFAULT_PORT = 3213;
const MAX_CONNECTIONS = 5000;
const MAX_CONNECTIONS_PER_IP = 30;
const MAX_PUBLISH_BYTES = 64 * 1024;
const MAX_CLIENT_MESSAGE_BYTES = 1024;
const MAX_BUFFERED_BYTES = 1024 * 1024;
const HEARTBEAT_INTERVAL_MS = 30_000;

function readEnvFile(filePath) {
    let contents;
    try {
        contents = fs.readFileSync(filePath, 'utf8');
    } catch (error) {
        if (error.code === 'ENOENT') return {};
        throw error;
    }

    const values = {};
    for (const rawLine of contents.split(/\r?\n/)) {
        const line = rawLine.trim();
        if (!line || line.startsWith('#')) continue;

        const match = line.match(/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);
        if (!match) continue;

        let value = match[2].trim();
        if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
            const quote = value[0];
            value = value.slice(1, -1);
            if (quote === '"') {
                value = value.replace(/\\n/g, '\n').replace(/\\r/g, '\r').replace(/\\"/g, '"').replace(/\\\\/g, '\\');
            }
        } else {
            value = value.replace(/\s+#.*$/, '').trim();
        }
        values[match[1]] = value;
    }

    return values;
}

function integerSetting(value, fallback, minimum, maximum) {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isSafeInteger(parsed) && parsed >= minimum && parsed <= maximum ? parsed : fallback;
}

function normalizeOrigin(value) {
    try {
        return new URL(value).origin.toLowerCase();
    } catch {
        return null;
    }
}

function loadConfiguration(overrides = {}) {
    const fileValues = readEnvFile(path.resolve(__dirname, '..', '.env'));
    const environment = { ...fileValues, ...process.env, ...(overrides.environment ?? {}) };
    const appOrigin = normalizeOrigin(environment.APP_URL || '');
    const configuredOrigins = String(environment.LIVE_TV_CHAT_WS_ALLOWED_ORIGINS || '')
        .split(',')
        .map((origin) => normalizeOrigin(origin.trim()))
        .filter(Boolean);
    if (appOrigin) configuredOrigins.push(appOrigin);

    return {
        host: overrides.host ?? environment.LIVE_TV_CHAT_WS_HOST ?? DEFAULT_HOST,
        port: overrides.port ?? integerSetting(environment.LIVE_TV_CHAT_WS_PORT, DEFAULT_PORT, 0, 65535),
        publishSecret: overrides.publishSecret ?? (environment.LIVE_TV_CHAT_WS_SECRET || environment.APP_KEY || ''),
        allowedOrigins: new Set(overrides.allowedOrigins ?? configuredOrigins),
        maxConnections: overrides.maxConnections ?? integerSetting(environment.LIVE_TV_CHAT_WS_MAX_CONNECTIONS, MAX_CONNECTIONS, 1, 100_000),
        maxConnectionsPerIp: overrides.maxConnectionsPerIp ?? integerSetting(environment.LIVE_TV_CHAT_WS_MAX_CONNECTIONS_PER_IP, MAX_CONNECTIONS_PER_IP, 1, 1000),
        heartbeatIntervalMs: overrides.heartbeatIntervalMs ?? HEARTBEAT_INTERVAL_MS,
        logger: overrides.logger ?? console,
    };
}

function writeJson(response, statusCode, payload) {
    const body = JSON.stringify(payload);
    response.writeHead(statusCode, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(body),
        'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff',
    });
    response.end(body);
}

function secureEqual(left, right) {
    if (!left || !right) return false;
    const leftDigest = crypto.createHash('sha256').update(String(left)).digest();
    const rightDigest = crypto.createHash('sha256').update(String(right)).digest();
    return crypto.timingSafeEqual(leftDigest, rightDigest);
}

function channelIdFrom(value) {
    if (!/^\d{1,19}$/.test(String(value ?? ''))) return null;
    const id = String(value).replace(/^0+(?=\d)/, '');
    return id === '0' ? null : id;
}

function requestIp(request) {
    const forwarded = request.headers['x-forwarded-for'];
    if (typeof forwarded === 'string' && forwarded.trim()) {
        return forwarded.split(',')[0].trim().slice(0, 64);
    }
    return String(request.socket.remoteAddress || 'unknown').slice(0, 64);
}

function validPublishedMessage(message) {
    if (!message || typeof message !== 'object' || Array.isArray(message)) return false;
    if (!['string', 'number'].includes(typeof message.id)) return false;
    if (typeof message.message !== 'string' || message.message.length > 1000) return false;
    if (message.guest_name != null && (typeof message.guest_name !== 'string' || message.guest_name.length > 80)) return false;
    if (message.avatar_url != null && (typeof message.avatar_url !== 'string' || message.avatar_url.length > 2048)) return false;
    if (message.promotion_packages != null && (!Array.isArray(message.promotion_packages) || message.promotion_packages.length > 1)) return false;
    return true;
}

function createChatServer(overrides = {}) {
    const config = loadConfiguration(overrides);
    if (!config.publishSecret) {
        throw new Error('LIVE_TV_CHAT_WS_SECRET or APP_KEY must be configured.');
    }

    const channels = new Map();
    const connectionsByIp = new Map();
    let shuttingDown = false;

    const server = http.createServer((request, response) => {
        const requestUrl = new URL(request.url || '/', 'http://localhost');

        if (request.method === 'GET' && requestUrl.pathname === '/health') {
            writeJson(response, shuttingDown ? 503 : 200, {
                status: shuttingDown ? 'stopping' : 'ok',
                connections: wss.clients.size,
                channels: channels.size,
                uptime_seconds: Math.floor(process.uptime()),
            });
            return;
        }

        if (request.method !== 'POST' || requestUrl.pathname !== '/publish') {
            writeJson(response, 404, { message: 'Not found.' });
            return;
        }

        if (!secureEqual(request.headers['x-chat-publish-secret'], config.publishSecret)) {
            request.resume();
            writeJson(response, 401, { message: 'Unauthorized.' });
            return;
        }

        const chunks = [];
        let receivedBytes = 0;
        let rejected = false;

        request.on('data', (chunk) => {
            if (rejected) return;
            receivedBytes += chunk.length;
            if (receivedBytes > MAX_PUBLISH_BYTES) {
                rejected = true;
                writeJson(response, 413, { message: 'Payload too large.' });
                request.destroy();
                return;
            }
            chunks.push(chunk);
        });

        request.on('end', () => {
            if (rejected) return;

            let payload;
            try {
                payload = JSON.parse(Buffer.concat(chunks).toString('utf8'));
            } catch {
                writeJson(response, 400, { message: 'Invalid JSON.' });
                return;
            }

            const channelId = channelIdFrom(payload?.channel_id);
            if (!channelId || !validPublishedMessage(payload?.message)) {
                writeJson(response, 422, { message: 'Invalid chat event.' });
                return;
            }

            const event = JSON.stringify({ type: 'chat.message', channel_id: channelId, message: payload.message });
            let delivered = 0;

            for (const client of channels.get(channelId) ?? []) {
                if (client.readyState !== WebSocket.OPEN) continue;
                if (client.bufferedAmount > MAX_BUFFERED_BYTES) {
                    client.terminate();
                    continue;
                }
                client.send(event);
                delivered += 1;
            }

            writeJson(response, 202, { delivered });
        });

        request.on('error', (error) => {
            if (!rejected) config.logger.error('publish request failed', { error: error.message });
        });
    });

    const wss = new WebSocketServer({ noServer: true, clientTracking: true, maxPayload: MAX_CLIENT_MESSAGE_BYTES, perMessageDeflate: false });

    server.on('upgrade', (request, socket, head) => {
        if (shuttingDown) {
            socket.destroy();
            return;
        }

        const requestUrl = new URL(request.url || '/', 'http://localhost');
        if (!['/ws', '/ws/'].includes(requestUrl.pathname)) {
            socket.write('HTTP/1.1 404 Not Found\r\nConnection: close\r\n\r\n');
            socket.destroy();
            return;
        }

        const channelId = channelIdFrom(requestUrl.searchParams.get('channel_id'));
        if (!channelId) {
            socket.write('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
            socket.destroy();
            return;
        }

        const origin = normalizeOrigin(String(request.headers.origin || ''));
        if (origin && config.allowedOrigins.size > 0 && !config.allowedOrigins.has(origin)) {
            socket.write('HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n');
            socket.destroy();
            return;
        }

        const ip = requestIp(request);
        const ipConnections = connectionsByIp.get(ip) ?? 0;
        if (wss.clients.size >= config.maxConnections || ipConnections >= config.maxConnectionsPerIp) {
            socket.write('HTTP/1.1 503 Service Unavailable\r\nRetry-After: 10\r\nConnection: close\r\n\r\n');
            socket.destroy();
            return;
        }

        request.chatChannelId = channelId;
        request.chatClientIp = ip;
        wss.handleUpgrade(request, socket, head, (client) => wss.emit('connection', client, request));
    });

    wss.on('connection', (client, request) => {
        const channelId = request.chatChannelId;
        const ip = request.chatClientIp;
        client.isAlive = true;

        if (!channels.has(channelId)) channels.set(channelId, new Set());
        channels.get(channelId).add(client);
        connectionsByIp.set(ip, (connectionsByIp.get(ip) ?? 0) + 1);

        client.on('pong', () => {
            client.isAlive = true;
        });

        client.on('message', (data, isBinary) => {
            if (isBinary || data.length > MAX_CLIENT_MESSAGE_BYTES) {
                client.close(1009, 'Message too large');
                return;
            }

            let payload;
            try {
                payload = JSON.parse(data.toString('utf8'));
            } catch {
                client.send(JSON.stringify({ type: 'error', message: 'Invalid JSON.' }));
                return;
            }

            if (payload?.type === 'ping') {
                client.send(JSON.stringify({ type: 'pong', timestamp: Date.now() }));
                return;
            }

            client.send(JSON.stringify({ type: 'error', message: 'Client publishing is not allowed.' }));
        });

        client.on('close', () => {
            const subscribers = channels.get(channelId);
            subscribers?.delete(client);
            if (subscribers?.size === 0) channels.delete(channelId);

            const remaining = (connectionsByIp.get(ip) ?? 1) - 1;
            if (remaining > 0) connectionsByIp.set(ip, remaining);
            else connectionsByIp.delete(ip);
        });

        client.on('error', (error) => config.logger.warn('websocket client error', { channel_id: channelId, error: error.message }));
        client.send(JSON.stringify({ type: 'ready', channel_id: channelId }));
    });

    const heartbeat = setInterval(() => {
        for (const client of wss.clients) {
            if (!client.isAlive) {
                client.terminate();
                continue;
            }
            client.isAlive = false;
            client.ping();
        }
    }, config.heartbeatIntervalMs);
    heartbeat.unref();

    server.on('clientError', (_error, socket) => {
        if (socket.writable) socket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
    });
    server.on('error', (error) => config.logger.error('websocket server error', { error: error.message }));

    async function listen() {
        await new Promise((resolve, reject) => {
            server.once('error', reject);
            server.listen(config.port, config.host, () => {
                server.off('error', reject);
                resolve();
            });
        });
        return server.address();
    }

    async function close() {
        if (shuttingDown) return;
        shuttingDown = true;
        clearInterval(heartbeat);

        for (const client of wss.clients) client.close(1012, 'Server restarting');

        await new Promise((resolve) => {
            const forceClose = setTimeout(() => {
                for (const client of wss.clients) client.terminate();
                resolve();
            }, 5000);
            forceClose.unref();

            wss.close(() => {
                clearTimeout(forceClose);
                resolve();
            });
        });

        await new Promise((resolve) => server.close(resolve));
    }

    return { server, wss, config, listen, close };
}

if (require.main === module) {
    let chatServer;
    try {
        chatServer = createChatServer();
    } catch (error) {
        console.error(error.message);
        process.exit(1);
    }

    chatServer.listen()
        .then((address) => console.log(`Live TV chat WebSocket server listening on ${address.address}:${address.port}`))
        .catch((error) => {
            console.error('Unable to start WebSocket server:', error);
            process.exit(1);
        });

    const shutdown = async (signal) => {
        console.log(`Received ${signal}; shutting down WebSocket server.`);
        try {
            await chatServer.close();
            process.exit(0);
        } catch (error) {
            console.error('WebSocket shutdown failed:', error);
            process.exit(1);
        }
    };

    process.once('SIGTERM', () => shutdown('SIGTERM'));
    process.once('SIGINT', () => shutdown('SIGINT'));
}

module.exports = { createChatServer, loadConfiguration };
