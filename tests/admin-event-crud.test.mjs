import assert from "node:assert/strict";
import test from "node:test";
import {
    CookieSession,
    createTestIdentity,
    csrfToken,
    runDatabaseFixture,
} from "./helpers.mjs";

test("administrator can create, read, update, and delete events over Fetch API", async () => {
    const session = new CookieSession();
    const identity = createTestIdentity("admin");
    const titlePrefix = `TEST-P15-${identity.username.slice(-12)}`;
    const title = `${titlePrefix} create`;
    const updatedTitle = `${titlePrefix} updated`;
    const passwordEnv = { STUDENTHUB_TEST_PASSWORD: identity.password };

    runDatabaseFixture("create-admin", identity.username, passwordEnv);
    try {
        const loginPage = await session.fetch("login.php");
        const loginToken = csrfToken(await loginPage.text());
        const login = await session.fetch("login.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                csrf_token: loginToken,
                identity: identity.username,
                password: identity.password,
            }),
        });
        const loginHtml = await login.text();
        assert.equal(login.status, 302, loginHtml.match(/<p class="error"[^>]*>(.*?)<\/p>/)?.[1] || loginHtml);
        assert.equal(login.headers.get("location"), "admin_dashboard.php");

        const adminPage = await session.fetch("admin_events.php");
        assert.equal(adminPage.status, 200);
        const eventToken = csrfToken(await adminPage.text());

        const initialList = await session.fetch("admin_events_api.php");
        assert.equal(initialList.status, 200);
        const initialPayload = await initialList.json();
        assert.equal(initialPayload.success, true);
        assert.ok(Array.isArray(initialPayload.data));

        const fields = {
            csrf_token: eventToken,
            event_id: "0",
            title,
            category: "Technical",
            event_date: "2027-06-15",
            description: "Temporary Practical 15 integration-test event.",
            status: "open",
        };
        const created = await session.fetch("admin_events_api.php", {
            method: "POST",
            body: new URLSearchParams({ ...fields, action: "create" }),
        });
        assert.equal(created.status, 201);
        const createdPayload = await created.json();
        assert.equal(createdPayload.success, true);
        const eventId = Number(createdPayload.data.id);
        assert.ok(eventId > 0);

        const readCreated = await session.fetch(
            `admin_events_api.php?q=${encodeURIComponent(title)}`
        );
        const createdRows = (await readCreated.json()).data;
        assert.equal(createdRows.length, 1);
        assert.equal(createdRows[0].id, eventId);

        const updated = await session.fetch("admin_events_api.php", {
            method: "POST",
            body: new URLSearchParams({
                ...fields,
                action: "update",
                event_id: String(eventId),
                title: updatedTitle,
                status: "closed",
            }),
        });
        assert.equal(updated.status, 200);
        assert.equal((await updated.json()).data.title, updatedTitle);

        const readUpdated = await session.fetch(
            `admin_events_api.php?q=${encodeURIComponent(updatedTitle)}`
        );
        const updatedRows = (await readUpdated.json()).data;
        assert.equal(updatedRows.length, 1);
        assert.equal(updatedRows[0].status, "closed");

        const deleted = await session.fetch("admin_events_api.php", {
            method: "POST",
            body: new URLSearchParams({
                csrf_token: eventToken,
                action: "delete",
                event_id: String(eventId),
            }),
        });
        assert.equal(deleted.status, 200);
        assert.equal((await deleted.json()).success, true);

        const readDeleted = await session.fetch(
            `admin_events_api.php?q=${encodeURIComponent(titlePrefix)}`
        );
        assert.equal((await readDeleted.json()).data.length, 0);

        const invalidCsrf = await session.fetch("admin_events_api.php", {
            method: "POST",
            body: new URLSearchParams({
                csrf_token: "invalid",
                action: "delete",
                event_id: "1",
            }),
        });
        assert.equal(invalidCsrf.status, 403);
    } finally {
        runDatabaseFixture("cleanup-admin", identity.username, {}, [titlePrefix]);
    }
});
