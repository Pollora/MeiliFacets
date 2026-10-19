export class Environment {
    static required(name: string): string {
        const value = process.env[name]

        if (value === undefined || value === '') {
            throw new Error(`Set ${name}: the browser tests read it from the environment.`)
        }

        return value
    }
}
