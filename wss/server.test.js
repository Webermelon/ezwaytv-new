'use strict';

const assert = require('node:assert/strict');
const http = require('node:http');
const { after, before, test } = require('node:test');
const { WebSocket } = require('ws');
const { createChatServer } = require('./server');

const SECRET = 'test-publish-secret';
const silentLogger = { error() {}, warn() {}, log() {} };
let chatServer;
let port;

function request(path, { method = 'GET', headers = {}, body } = {}) {
    return new Promise((resolve, reject) => {
        const request = http.request({ host: '127.0.0.1', port, path, method, headers }, (response) => {
            const chunks = [];
            response.on('data', (chunk) => chunks.push(chunk));
            response.on('end', () => {
                const text = Buffer.concat(chunks).toString('utf8');
                resolve({ status: response.statusCode, body: text ? JSON.parse(text) : null });
            });
        });
        request.on('error', reject);
        if (body) request.write(body);
        request.end();
    });
}

function connect(channelId, origin = 'https://ezwaytv.webermelon.dev') {
    return new Promise((resolve, reject) => {
        const socket = new WebSocket(`ws://127.0.0.1:${port}/ws?channel_id=${channelId}`, { origin });
        const timeout = setTimeout(() => reject(new Error('WebSocket connection timed out.')), 2000);
        socket.once('message', (data) => {
            clearTimeout(timeout);
            try {
                const ready = JSON.parse(data.toString('utf8'));
                assert.deepEqual(ready, { type: 'ready', channel_id: String(channelId) });
                resolve(socket);
            } catch (error) {
                reject(error);
            }
        });
        socket.once('error', reject);
    });
}

function nextJson(socket) {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('WebSocket message timed out.')), 2000);
        socket.once('message', (data) => {
            clearTimeout(timeout);
            try {
                resolve(JSON.parse(data.toString('utf8')));
            } catch (error) {
                reject(error);
            }
        });
    });
}

function waitForNoMessage(socket, milliseconds = 150) {
    return new Promise((resolve, reject) => {
        const onMessage = (data) => reject(new Error(`Unexpected message: ${data}`));
        socket.once('message', onMessage);
        setTimeout(() => {
            socket.off('message', onMessage);
            resolve();
        }, milliseconds);
    });
}

async function publish(channelId, message, secret = SECRET) {
    const body = JSON.stringify({ channel_id: channelId, message });
    return request('/publish', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(body),
            'X-Chat-Publish-Secret': secret,
        },
        body,
    });
}

before(async () => {
    chatServer = createChatServer({
        host: '127.0.0.1',
        port: 0,
        publishSecret: SECRET,
        allowedOrigins: ['https://ezwaytv.webermelon.dev'],
        heartbeatIntervalMs: 1000,
        logger: silentLogger,
    });
    const address = await chatServer.listen();
    port = address.port;
});

after(async () => {
    await chatServer.close();
});

test('health endpoint reports server state', async () => {
    const response = await request('/health');
    assert.equal(response.status, 200);
    assert.equal(response.body.status, 'ok');
    assert.equal(typeof response.body.connections, 'number');
});

test('rejects invalid channels and unexpected browser origins', async () => {
    await assert.rejects(connect('invalid'));
    await assert.rejects(connect('12', 'https://attacker.example'));
});

test('requires the internal publish secret', async () => {
    const response = await publish('12', { id: 1, message: 'hello' }, 'wrong-secret');
    assert.equal(response.status, 401);
});

test('broadcasts persisted messages only to the matching channel', async () => {
    const channel12 = await connect('12');
    const channel13 = await connect('13');

    const incoming = nextJson(channel12);
    const noCrossChannelMessage = waitForNoMessage(channel13);
    const message = {
        id: 99,
        user_id: 4,
        guest_name: 'Test User',
        avatar_url: null,
        message: 'hello',
        promotion_packages: [],
        time: 'just now',
        created_at: '2026-10-19T10:00:00Z',
    };
    const response = await publish('12', message);

    assert.equal(response.status, 202);
    assert.equal(response.body.delivered, 1);
    assert.deepEqual(await incoming, { type: 'chat.message', channel_id: '12', message });
    await noCrossChannelMessage;
    channel12.close();
    channel13.close();
});

test('does not allow browser clients to publish messages', async () => {
    const socket = await connect('14');
    const errorEvent = nextJson(socket);
    socket.send(JSON.stringify({ type: 'chat.message', message: { id: 1, message: 'forged' } }));
    assert.deepEqual(await errorEvent, { type: 'error', message: 'Client publishing is not allowed.' });
    socket.close();
});

test('rejects malformed internal events', async () => {
    const response = await publish('12', { id: 1, message: 123 });
    assert.equal(response.status, 422);

    const tooManyPromotions = await publish('12', {
        id: 2,
        message: 'too many packages',
        promotion_packages: [{ id: 1 }, { id: 2 }],
    });
    assert.equal(tooManyPromotions.status, 422);
});
