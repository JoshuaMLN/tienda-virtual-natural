import { defineConfig } from '@playwright/test';
import { existsSync } from 'node:fs';

if (existsSync('.env.e2e')) {
    process.loadEnvFile('.env.e2e');
}

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8011',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /.*\.setup\.ts/,
            use: { browserName: 'chromium' },
        },
        {
            name: 'public',
            testDir: './e2e/public',
            use: { browserName: 'chromium' },
        },
        {
            name: 'customer',
            testDir: './e2e/customer',
            dependencies: ['setup'],
            use: {
                browserName: 'chromium',
                storageState: 'playwright/.auth/customer.json',
            },
        },
        {
            name: 'admin',
            testDir: './e2e/admin',
            dependencies: ['setup'],
            use: {
                browserName: 'chromium',
                storageState: 'playwright/.auth/admin.json',
            },
        },
    ],
    webServer: {
        command: 'php artisan serve --env=e2e --host=127.0.0.1 --port=8011',
        url: 'http://127.0.0.1:8011',
        reuseExistingServer: false,
        timeout: 30_000,
    },
});
