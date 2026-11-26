const frames = (message) => String(message).split('\n').filter(Boolean).map((line) => JSON.parse(line));

const tap = async (page) => {
    const hub = { socket: null, subscribed: [] };

    await page.routeWebSocket(() => true, (socket) => {
        const server = socket.connectToServer();
        const asked = new Map();
        hub.socket = socket;
        hub.subscribed = [];

        socket.onMessage((message) => {
            frames(message).filter(({ subscribe }) => subscribe).forEach(({ id, subscribe }) => asked.set(id, subscribe.channel));
            server.send(message);
        });
        server.onMessage((message) => {
            frames(message).filter(({ id, subscribe }) => subscribe && asked.has(id)).forEach(({ id }) => hub.subscribed.push(asked.get(id)));
            socket.send(message);
        });
    });

    return hub;
};

const publish = (hub, times) => {
    for (let sent = 0; sent < times; sent += 1) {
        hub.socket.send(JSON.stringify({ push: { channel: hub.subscribed[0], pub: { data: {} } } }));
    }
};

module.exports = { tap, publish };
