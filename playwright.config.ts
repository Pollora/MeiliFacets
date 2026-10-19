import { defineConfig, devices } from '@playwright/test'
import { Environment } from './tests/playwright/environment.ts'

export default defineConfig({
    testDir: 'tests/playwright',
    fullyParallel: true,
    // More browsers at once than a local engine answers in time make the waits fail, not the module.
    workers: 3,
    forbidOnly: true,
    retries: 0,
    timeout: 15_000,
    expect: { timeout: 4_000 },
    reporter: [['list']],
    use: {
        baseURL: Environment.required('MEILIFACETS_PLAYWRIGHT_URL'),
        ignoreHTTPSErrors: true,
        actionTimeout: 4_000,
        navigationTimeout: 8_000,
        trace: 'retain-on-failure',
    },
    outputDir: 'tests/playwright/.results',
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] }, testIgnore: /phone\.spec\.ts/ },
        { name: 'phone', use: { ...devices['Pixel 7'] }, testMatch: /phone\.spec\.ts/ },
    ],
})
