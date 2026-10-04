export function resultsUrl(action, entries, location) {
    const url = new URL(action, location.href);
    if (url.origin !== location.origin || url.pathname !== location.pathname)
        return null;
    url.search = new URLSearchParams(entries).toString();
    url.searchParams.delete("page");
    url.hash = "";
    return url;
}

// Invalidate immediately, including during debounce: an older response must never
// render while the worker is already typing the next query.
export function createLatestResults({
    fetch,
    render,
    commit,
    busy,
    error,
    pending = () => {},
    timers = globalThis,
}) {
    let revision = 0;
    let abort = null;
    let timer = null;
    const cancel = () => {
        revision++;
        timers.clearTimeout(timer);
        abort?.abort();
        busy(false);
        pending(false);
    };
    const schedule = (url, delay = 0, historyMode = "replace") => {
        cancel();
        const current = revision;
        pending(true);
        const run = async () => {
            abort = new AbortController();
            busy(true);
            error("");
            try {
                const response = await fetch(url.toString(), {
                    signal: abort.signal,
                    credentials: "same-origin",
                    headers: {
                        Accept: "text/html",
                        "X-HealthLink-Live-Results": "1",
                    },
                });
                if (
                    !response.ok ||
                    response.redirected ||
                    !response.headers.get("content-type")?.includes("text/html")
                )
                    throw new Error("Unavailable results");
                const html = await response.text();
                if (current !== revision) return;
                render(html);
                commit(url, historyMode);
            } catch (failure) {
                if (current === revision && failure.name !== "AbortError")
                    error(
                        "Results could not be updated. Try searching or changing a filter again.",
                    );
            } finally {
                if (current === revision) {
                    busy(false);
                    pending(false);
                }
            }
        };
        if (delay) timer = timers.setTimeout(run, delay);
        else return run();
    };
    return { schedule, cancel };
}

export function ordinaryResultsLink(event, link, location) {
    if (
        !link ||
        event.defaultPrevented ||
        event.button !== 0 ||
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        event.altKey ||
        link.hasAttribute("download")
    )
        return null;
    if (
        link.getAttribute("target") &&
        link.getAttribute("target").toLowerCase() !== "_self"
    )
        return null;
    const url = new URL(link.href, location.href);
    return url.origin === location.origin &&
        url.pathname === location.pathname &&
        !url.hash
        ? url
        : null;
}

function setFormQuery(form, url) {
    const parameters = url.searchParams;
    for (const control of form.elements) {
        if (!control.name || ["submit", "button"].includes(control.type))
            continue;
        const value = parameters.get(control.name);
        if (["checkbox", "radio"].includes(control.type))
            control.checked = parameters
                .getAll(control.name)
                .includes(control.value);
        else control.value = value ?? control.dataset.liveDefault ?? "";
        // Alpine record selectors keep their label and selected identity together.
        control.dispatchEvent(new Event("input", { bubbles: true }));
    }
    form.dispatchEvent(new Event("live-results:restore"));
}

export function initializeLiveResults(window, document) {
    if (!window.fetch || !window.AbortController || !window.DOMParser) return;
    const groups = new Map();
    document
        .querySelectorAll("form[data-live-results-form]")
        .forEach((form) => {
            if (form.method.toLowerCase() !== "get") return;
            const key = form.dataset.liveResultsForm;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(form);
        });
    groups.forEach((forms, key) => {
        const selector = `[data-live-results="${CSS.escape(key)}"]`;
        const regions = [...document.querySelectorAll(selector)];
        if (!regions.length) return;
        const source = forms[0].liveResultsSource ?? forms[0];
        if (!resultsUrl(source.action, [], window.location)) return;
        const status = document.createElement("p");
        status.className = "live-results-status";
        status.setAttribute("role", "status");
        status.setAttribute("aria-live", "polite");
        regions[0].before(status);
        let restoring = false;
        let composing = false;
        let isPending = false;
        let restoreControlsOnResponse = false;
        const exports = () => [
            ...document.querySelectorAll(
                '[data-export-control] a, a[href*="/export"]',
            ),
        ];
        const controller = createLatestResults({
            fetch: window.fetch.bind(window),
            timers: window,
            busy: (loading) => {
                regions.forEach((region) => {
                    region.classList.toggle("is-results-loading", loading);
                    region.setAttribute("aria-busy", String(loading));
                });
                if (loading) status.textContent = "Updating results...";
            },
            pending: (value) => {
                isPending = value;
                document
                    .querySelectorAll("[data-export-control] button")
                    .forEach((button) => {
                        button.disabled = value;
                    });
            },
            error: (message) => {
                status.textContent = message;
                status.classList.toggle("is-error", Boolean(message));
            },
            render: (html) => {
                const incoming = new window.DOMParser().parseFromString(
                    html,
                    "text/html",
                );
                const replacements = [...incoming.querySelectorAll(selector)];
                if (
                    replacements.length !== regions.length ||
                    replacements.some((region) =>
                        region.querySelector('script, form[method="GET" i]'),
                    )
                )
                    throw new Error("Unexpected results");
                const focusedRegion = regions.find((region) =>
                    region.contains(document.activeElement),
                );
                // Validate every region before changing any current results.
                regions.forEach((region, index) => {
                    region.innerHTML = replacements[index].innerHTML;
                });
                if (focusedRegion) {
                    focusedRegion.setAttribute("tabindex", "-1");
                    focusedRegion.focus({ preventScroll: true });
                }
                if (restoreControlsOnResponse) {
                    const serverForm = incoming.querySelector(
                        `form[data-live-results-form="${CSS.escape(key)}"]`,
                    );
                    if (serverForm) {
                        restoring = true;
                        for (const control of source.elements) {
                            if (
                                !control.name ||
                                (control.type === "hidden" &&
                                    control.name !== "search")
                            )
                                continue;
                            const serverControl = [...serverForm.elements].find(
                                (candidate) => candidate.name === control.name,
                            );
                            if (
                                !serverControl ||
                                serverControl.type === "hidden"
                            )
                                continue;
                            if (["checkbox", "radio"].includes(control.type))
                                control.checked = serverControl.checked;
                            else control.value = serverControl.value;
                        }
                        source.dispatchEvent(new Event("live-results:restore"));
                        restoring = false;
                    }
                    restoreControlsOnResponse = false;
                }
                const newExports = [
                    ...incoming.querySelectorAll(
                        '[data-export-control] a, a[href*="/export"]',
                    ),
                ];
                if (newExports.length === exports().length)
                    exports().forEach((link, index) => {
                        link.href = newExports[index].href;
                    });
                status.textContent = "Results updated.";
            },
            commit: (url, mode) => {
                if (mode !== "none")
                    window.history[
                        mode === "push" ? "pushState" : "replaceState"
                    ](window.history.state, "", url);
                document.dispatchEvent(
                    new CustomEvent("live-results:updated", {
                        detail: { key, source },
                    }),
                );
            },
        });
        const search = (delay = 0) => {
            restoreControlsOnResponse = false;
            if (!source.checkValidity()) {
                controller.cancel();
                return;
            }
            const url = resultsUrl(
                source.action,
                new FormData(source).entries(),
                window.location,
            );
            if (url) controller.schedule(url, delay);
        };
        forms.forEach((form) => {
            for (const control of form.elements) {
                if (
                    control.name &&
                    ![
                        "hidden",
                        "submit",
                        "button",
                        "checkbox",
                        "radio",
                    ].includes(control.type)
                ) {
                    control.dataset.liveDefault =
                        control.tagName === "SELECT"
                            ? (control.options[0]?.value ?? "")
                            : "";
                }
            }
            form.addEventListener("submit", (event) => {
                if (
                    event.defaultPrevented ||
                    (event.submitter?.formMethod &&
                        event.submitter.formMethod.toLowerCase() !== "get") ||
                    event.submitter?.hasAttribute("formaction")
                )
                    return;
                event.preventDefault();
                search();
                form.dispatchEvent(new Event("live-results:submitted"));
            });
            form.addEventListener("input", (event) => {
                if (
                    restoring ||
                    !event.target.name ||
                    event.target.type === "hidden" ||
                    event.target.tagName === "SELECT"
                )
                    return;
                if (composing || event.isComposing) {
                    controller.cancel();
                    return;
                }
                search(300);
            });
            form.addEventListener("change", (event) => {
                if (!restoring && event.target.name) search();
            });
            form.addEventListener("live-results:filter-change", () => {
                if (!restoring) search(0);
            });
            form.addEventListener("compositionstart", () => {
                composing = true;
                controller.cancel();
            });
            form.addEventListener("compositionend", () => {
                composing = false;
                search(300);
            });
        });
        document.addEventListener("click", (event) => {
            const link = event.target.closest?.("a[href]");
            if (!link) return;
            if (isPending && exports().includes(link)) {
                event.preventDefault();
                return;
            }
            const pagination =
                regions.some((region) => region.contains(link)) &&
                new URL(link.href).searchParams.has("page");
            const filterLink =
                link.dataset.liveResultsLink === key ||
                forms.some((form) => form.contains(link));
            if (!pagination && !filterLink) return;
            const url = ordinaryResultsLink(event, link, window.location);
            if (!url) return;
            event.preventDefault();
            if (pagination) {
                const currentQuery = resultsUrl(
                    source.action,
                    new FormData(source).entries(),
                    window.location,
                );
                if (currentQuery) {
                    currentQuery.searchParams.set(
                        "page",
                        url.searchParams.get("page"),
                    );
                    url.search = currentQuery.search;
                }
            }
            if (filterLink) {
                restoreControlsOnResponse = true;
                restoring = true;
                setFormQuery(source, url);
                forms
                    .filter((form) => form !== source)
                    .forEach((form) => setFormQuery(form, url));
                restoring = false;
            }
            controller.schedule(url, 0, pagination ? "push" : "replace");
        });
        const restore = () => {
            restoreControlsOnResponse = true;
            restoring = true;
            const url = new URL(window.location.href);
            setFormQuery(source, url);
            forms
                .filter((form) => form !== source)
                .forEach((form) => setFormQuery(form, url));
            restoring = false;
            controller.schedule(url, 0, "none");
        };
        window.addEventListener("popstate", restore);
        window.addEventListener("pagehide", () => controller.cancel());
        window.addEventListener("pageshow", (event) => {
            if (event.persisted) restore();
        });
    });
}
