const { defineConfig, devices } = require('@playwright/test');

const baseURL = process.env.ADZERCMS_URL;
if (!baseURL) {
    throw new Error('ADZERCMS_URL is not set: the runner gives the instance address');
}

module.exports = defineConfig({
    testDir: './specs',
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        actionTimeout: 15000,
        navigationTimeout: 30000,
    },
    projects: [
        { name: 'login', testMatch: /login\.setup\.js/, teardown: 'logout' },
        { name: 'logout', testMatch: /logout\.teardown\.js/, use: { storageState: '.auth/operator.json' } },
        {
            name: 'phone',
            dependencies: ['login'],
            use: { ...devices['Pixel 7'], storageState: '.auth/operator.json' },
        },
        {
            name: 'desktop',
            dependencies: ['login'],
            use: { ...devices['Desktop Chrome'], storageState: '.auth/operator.json' },
        },
    ],
});
