import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import { fileURLToPath } from "node:url";
import path from "node:path";

export const BASE_URL = new URL(
    process.env.STUDENTHUB_BASE_URL || "http://127.0.0.1:8091/"
);
export const PROJECT_ROOT = fileURLToPath(new URL("../", import.meta.url));

export class CookieSession {
    #cookies = new Map();

    async fetch(resource, options = {}) {
        const headers = new Headers(options.headers);
        if (this.#cookies.size > 0) {
            headers.set(
                "Cookie",
                [...this.#cookies].map(([name, value]) => `${name}=${value}`).join("; ")
            );
        }

        const response = await fetch(new URL(resource, BASE_URL), {
            ...options,
            headers,
            redirect: "manual",
        });
        for (const cookie of response.headers.getSetCookie()) {
            const firstPart = cookie.split(";", 1)[0];
            const separator = firstPart.indexOf("=");
            if (separator > 0) {
                this.#cookies.set(firstPart.slice(0, separator), firstPart.slice(separator + 1));
            }
        }
        return response;
    }
}

export function csrfToken(html, fieldName = "csrf_token") {
    const escapedName = fieldName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    const pattern = new RegExp(
        `<input\\b(?=[^>]*\\bname=["']${escapedName}["'])(?=[^>]*\\bvalue=["']([^"']+)["'])[^>]*>`,
        "i"
    );
    const match = html.match(pattern);
    assert.ok(match, `Could not find the ${fieldName} form token`);
    return match[1];
}

export function createTestIdentity(prefix) {
    const suffix = randomBytes(6).toString("hex");
    return {
        username: `test_${prefix}_${suffix}`,
        password: `Test-${randomBytes(18).toString("hex")}aA1!`,
    };
}

export function runDatabaseFixture(action, username, extraEnv = {}, extraArguments = []) {
    const result = spawnSync(
        process.env.PHP_BINARY || "php",
        [path.join(PROJECT_ROOT, "tests/db_fixture.php"), action, username, ...extraArguments],
        {
            cwd: PROJECT_ROOT,
            encoding: "utf8",
            env: { ...process.env, ...extraEnv },
        }
    );
    assert.equal(
        result.status,
        0,
        `PHP integration fixture failed: ${result.stderr || result.stdout}`
    );
}
