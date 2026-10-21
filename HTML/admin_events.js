"use strict";

const eventForm = document.getElementById("eventForm");
const eventList = document.getElementById("adminEventList");
const eventMessage = document.getElementById("eventAdminMessage");
const searchInput = document.getElementById("eventAdminSearch");
const categoryFilter = document.getElementById("eventAdminCategory");
const statusFilter = document.getElementById("eventAdminStatus");
const eventIdInput = document.getElementById("eventId");
const actionInput = document.getElementById("eventAction");
const formHeading = document.getElementById("eventFormHeading");
const saveButton = document.getElementById("saveEventButton");
const cancelEditButton = document.getElementById("cancelEditButton");
const currentPoster = document.getElementById("currentPoster");

let searchTimer = null;
let listRequestId = 0;

async function readApiResponse(response) {
    let payload;
    try {
        payload = await response.json();
    } catch (error) {
        throw new Error("The server returned an invalid response. Please try again.");
    }

    if (!response.ok || payload.success !== true) {
        throw new Error(payload.message || "The request could not be completed.");
    }
    return payload;
}

function setEventMessage(message, isError = false) {
    eventMessage.textContent = message;
    eventMessage.classList.toggle("event-error", isError);
}

function safePosterUrl(path) {
    return typeof path === "string"
        && /^uploads\/event-posters\/event_[a-f0-9]{32}\.(jpg|png|webp)$/.test(path)
        ? path
        : null;
}

async function loadAdminEvents({ quiet = false } = {}) {
    const requestId = ++listRequestId;
    const query = new URLSearchParams();
    if (searchInput.value.trim()) query.set("q", searchInput.value.trim());
    if (categoryFilter.value) query.set("category", categoryFilter.value);
    if (statusFilter.value) query.set("status", statusFilter.value);

    eventList.setAttribute("aria-busy", "true");
    if (!quiet) {
        eventList.replaceChildren();
        const loading = document.createElement("p");
        loading.textContent = "Loading events...";
        eventList.appendChild(loading);
        setEventMessage("Loading events...");
    }

    try {
        const response = await fetch("admin_events_api.php?" + query.toString(), {
            headers: { Accept: "application/json" }
        });
        const payload = await readApiResponse(response);
        if (requestId !== listRequestId) return;
        renderAdminEvents(payload.data);
        if (!quiet) {
            const count = payload.data.length;
            setEventMessage(count + (count === 1 ? " event loaded." : " events loaded."));
        }
    } catch (error) {
        if (requestId !== listRequestId) return;
        eventList.replaceChildren();
        const failure = document.createElement("p");
        failure.textContent = error.message;
        eventList.appendChild(failure);
        setEventMessage(error.message, true);
    } finally {
        if (requestId === listRequestId) {
            eventList.setAttribute("aria-busy", "false");
        }
    }
}

function renderAdminEvents(events) {
    eventList.replaceChildren();
    if (!Array.isArray(events) || events.length === 0) {
        const empty = document.createElement("p");
        empty.textContent = "No events match these filters.";
        eventList.appendChild(empty);
        eventList.setAttribute("aria-busy", "false");
        return;
    }

    events.forEach(function(event) {
        const card = document.createElement("article");
        card.className = "card";

        const title = document.createElement("h3");
        title.textContent = event.title;
        card.appendChild(title);

        const details = document.createElement("p");
        details.textContent = event.category + " · " + event.date + " · "
            + (event.status === "closed" ? "Closed" : "Open");
        card.appendChild(details);

        const description = document.createElement("p");
        description.textContent = event.description;
        card.appendChild(description);

        const posterPath = safePosterUrl(event.poster_path);
        if (posterPath !== null) {
            const poster = document.createElement("img");
            poster.className = "admin-event-poster";
            poster.src = posterPath;
            poster.alt = "Poster for " + event.title;
            card.appendChild(poster);
        }

        const actions = document.createElement("div");
        actions.className = "admin-event-actions";
        const editButton = document.createElement("button");
        editButton.type = "button";
        editButton.textContent = "Edit";
        editButton.addEventListener("click", function() {
            beginEventEdit(event);
        });
        actions.appendChild(editButton);

        const deleteButton = document.createElement("button");
        deleteButton.type = "button";
        deleteButton.textContent = "Delete";
        deleteButton.addEventListener("click", function() {
            deleteEvent(event);
        });
        actions.appendChild(deleteButton);
        card.appendChild(actions);
        eventList.appendChild(card);
    });
    eventList.setAttribute("aria-busy", "false");
}

function beginEventEdit(event) {
    eventIdInput.value = String(event.id);
    actionInput.value = "update";
    document.getElementById("title").value = event.title;
    document.getElementById("category").value = event.category;
    document.getElementById("eventDate").value = event.date;
    document.getElementById("description").value = event.description;
    document.getElementById("eventStatus").value = event.status;
    document.getElementById("poster").value = "";
    formHeading.textContent = "Edit Event";
    saveButton.textContent = "Save Changes";
    cancelEditButton.hidden = false;
    currentPoster.replaceChildren();

    const posterPath = safePosterUrl(event.poster_path);
    if (posterPath !== null) {
        const link = document.createElement("a");
        link.href = posterPath;
        link.target = "_blank";
        link.rel = "noopener";
        link.textContent = "View current poster";
        currentPoster.appendChild(link);
        currentPoster.hidden = false;
    } else {
        currentPoster.hidden = true;
    }
    formHeading.scrollIntoView({ behavior: "smooth", block: "center" });
}

function resetEventForm() {
    eventForm.reset();
    eventIdInput.value = "0";
    actionInput.value = "create";
    formHeading.textContent = "Add Event";
    saveButton.textContent = "Create Event";
    cancelEditButton.hidden = true;
    currentPoster.hidden = true;
    currentPoster.replaceChildren();
}

async function deleteEvent(event) {
    if (!window.confirm('Delete "' + event.title + '"? This cannot be undone.')) {
        return;
    }

    const formData = new FormData();
    formData.set("csrf_token", eventForm.querySelector('[name="csrf_token"]').value);
    formData.set("action", "delete");
    formData.set("event_id", String(event.id));

    try {
        const response = await fetch("admin_events_api.php", {
            method: "POST",
            body: formData,
            headers: { Accept: "application/json" }
        });
        const payload = await readApiResponse(response);
        setEventMessage(payload.message);
        if (String(event.id) === eventIdInput.value) resetEventForm();
        await loadAdminEvents({ quiet: true });
    } catch (error) {
        setEventMessage(error.message, true);
    }
}

eventForm.addEventListener("submit", async function(event) {
    event.preventDefault();
    if (!eventForm.reportValidity()) return;

    const isUpdate = actionInput.value === "update";
    saveButton.disabled = true;
    setEventMessage(isUpdate ? "Saving event changes..." : "Creating event...");
    try {
        const response = await fetch("admin_events_api.php", {
            method: "POST",
            body: new FormData(eventForm),
            headers: { Accept: "application/json" }
        });
        const payload = await readApiResponse(response);
        resetEventForm();
        setEventMessage(payload.message);
        await loadAdminEvents({ quiet: true });
    } catch (error) {
        setEventMessage(error.message, true);
    } finally {
        saveButton.disabled = false;
    }
});

cancelEditButton.addEventListener("click", resetEventForm);

searchInput.addEventListener("input", function() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function() {
        loadAdminEvents();
    }, 300);
});

categoryFilter.addEventListener("change", function() {
    loadAdminEvents();
});

statusFilter.addEventListener("change", function() {
    loadAdminEvents();
});

loadAdminEvents();
