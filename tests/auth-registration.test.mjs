import assert from "node:assert/strict";
import test from "node:test";
import {
    CookieSession,
    createTestIdentity,
    csrfToken,
    runDatabaseFixture,
} from "./helpers.mjs";

test("student registration persists data, supports login, and protects access", async () => {
    const session = new CookieSession();
    const identity = createTestIdentity("student");
    const email = `${identity.username}@example.test`;
    const mobile = `8${Date.now().toString().slice(-9)}`;

    try {
        const registerPage = await session.fetch("register.php");
        assert.equal(registerPage.status, 200);
        const registrationToken = csrfToken(await registerPage.text());
        const registration = await session.fetch("register.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                csrf_token: registrationToken,
                name: "Practical Fifteen Test Student",
                username: identity.username,
                email,
                mobile,
                password: identity.password,
                confirmPassword: identity.password,
                course: "CSE",
                year: "2",
                gender: "Other",
                terms: "on",
            }),
        });
        assert.equal(registration.status, 200);
        assert.match(await registration.text(), /Registration successful!/);

        const loginPage = await session.fetch("login.php");
        const loginToken = csrfToken(await loginPage.text(), "csrf_token");
        const login = await session.fetch("login.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                csrf_token: loginToken,
                identity: identity.username,
                password: identity.password,
            }),
        });
        assert.equal(login.status, 302);
        assert.equal(login.headers.get("location"), "dashboard.php");

        const dashboard = await session.fetch("dashboard.php");
        assert.equal(dashboard.status, 200);
        const dashboardHtml = await dashboard.text();
        assert.match(dashboardHtml, /Practical Fifteen Test Student/);
        assert.match(dashboardHtml, /CSE/);

        const adminApi = await session.fetch("admin_events_api.php");
        assert.equal(adminApi.status, 403, "students must not access the administrator event API");

        const logoutToken = csrfToken(dashboardHtml);
        const logout = await session.fetch("logout.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ csrf_token: logoutToken }),
        });
        assert.equal(logout.status, 302);

        const protectedPage = await session.fetch("dashboard.php");
        assert.equal(protectedPage.status, 302);
        assert.equal(protectedPage.headers.get("location"), "login.php");
    } finally {
        runDatabaseFixture("delete-test-user", identity.username);
    }
});

test("invalid credentials and unauthenticated API calls are rejected", async () => {
    const session = new CookieSession();
    const loginPage = await session.fetch("login.php");
    const loginToken = csrfToken(await loginPage.text());

    const login = await session.fetch("login.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            csrf_token: loginToken,
            identity: "not-a-real-student",
            password: "NotARealPassword1!",
        }),
    });
    assert.equal(login.status, 200);
    assert.match(await login.text(), /Invalid username\/email or password\./);

    const protectedPage = await session.fetch("admin_dashboard.php");
    assert.equal(protectedPage.status, 302);
    assert.equal(protectedPage.headers.get("location"), "login.php");

    const apiResponse = await session.fetch("admin_events_api.php");
    assert.equal(apiResponse.status, 401);
    assert.equal((await apiResponse.json()).success, false);
});
