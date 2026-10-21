import assert from "node:assert/strict";
import test from "node:test";
import { BASE_URL, CookieSession } from "./helpers.mjs";

const publicPages = [
    ["home.html", "Student Hub"],
    ["login.php", "Login | Student Hub"],
    ["register.php", "Register Page"],
    ["course_registration.html", "Course Registration"],
    ["fees_payment.html", "Fees Payment Portal"],
    ["exam_schedule.html", "Exam Schedule"],
    ["event_calendar.html", "Event Calendar"],
    ["about_faculty.html", "Faculty Details"],
    ["feedback.html", "Feedback"],
    ["profile.html", "Profile Page"],
];

test("public portal pages render and their navigation targets resolve", async () => {
    const session = new CookieSession();

    for (const [route, expectedTitle] of publicPages) {
        const response = await session.fetch(route);
        assert.equal(response.status, 200, `${route} should render`);
        const html = await response.text();
        assert.match(html, new RegExp(`<title>${expectedTitle}</title>`, "i"));
        assert.match(html, /style\.css\?v=/, `${route} should load the shared stylesheet`);

        const links = [...html.matchAll(/<a\b[^>]*\bhref=["']([^"']*)["'][^>]*>/gi)]
            .map((match) => match[1])
            .filter((href) => href && !href.startsWith("#") && !/^(?:https?:|mailto:|tel:|javascript:)/i.test(href));
        for (const href of links) {
            const target = new URL(href, new URL(route, BASE_URL));
            assert.equal(target.origin, BASE_URL.origin, `${route} link should stay local: ${href}`);
            const targetResponse = await session.fetch(target);
            assert.ok(
                [200, 302].includes(targetResponse.status),
                `${route} link ${href} returned HTTP ${targetResponse.status}`
            );
        }
    }
});
