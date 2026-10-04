// Shared interaction only: callers retain their own open/close and submission rules.
export function createModalInteraction(window, document) {
    const active = new Map();
    const isolated = new Map();
    let savedStyles = null;
    const top = () => [...active.keys()].at(-1);
    const focusables = root => [...root.querySelectorAll(
        'a[href], button, input:not([type="hidden"]), textarea, select, summary, [contenteditable="true"], [tabindex]',
    )].filter(element => !element.matches(':disabled') && element.tabIndex >= 0
        && element.getClientRects().length && window.getComputedStyle(element).visibility !== 'hidden'
        && !element.closest('[inert]'));
    const focus = root => {
        const target = focusables(root)[0] ?? root.querySelector('[role="dialog"], [role="alertdialog"]') ?? root;
        if (!target.hasAttribute('tabindex') && target.tabIndex < 0) target.setAttribute('tabindex', '-1');
        target.focus();
    };
    const restoreIsolation = () => {
        for (const [element, inert] of isolated) element.inert = inert;
        isolated.clear();
    };
    const isolate = () => {
        restoreIsolation();
        let branch = top();
        while (branch && branch !== document.body) {
            for (const sibling of branch.parentElement?.children ?? []) {
                if (sibling === branch || ['SCRIPT', 'STYLE', 'LINK'].includes(sibling.tagName)) continue;
                isolated.set(sibling, sibling.inert);
                sibling.inert = true;
            }
            branch = branch.parentElement;
        }
    };
    const observer = new window.MutationObserver(() => { if (active.size) isolate(); });
    const keydown = event => {
        const root = top();
        if (!root || event.key !== 'Tab') return;
        const controls = focusables(root);
        const first = controls[0];
        const last = controls.at(-1);
        if (!first) { event.preventDefault(); focus(root); }
        else if (!root.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) {
            event.preventDefault(); (event.shiftKey ? last : first).focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first.focus();
        }
    };
    const focusin = event => {
        const root = top();
        if (root && !root.contains(event.target)) focus(root);
    };
    document.addEventListener('keydown', keydown, true);
    document.addEventListener('focusin', focusin, true);

    return {
        focus(root) {
            if (top() === root && !root.contains(document.activeElement)) focus(root);
        },
        open(root, trigger = document.activeElement) {
            if (active.has(root)) return;
            active.set(root, trigger);
            if (!savedStyles) {
                const html = document.documentElement;
                const body = document.body;
                const width = html.getBoundingClientRect().width;
                savedStyles = [html, body].map(element => ({
                    element,
                    overflow: element.style.getPropertyValue('overflow'),
                    priority: element.style.getPropertyPriority('overflow'),
                    padding: element.style.getPropertyValue('padding-right'),
                    paddingPriority: element.style.getPropertyPriority('padding-right'),
                }));
                const padding = Number.parseFloat(window.getComputedStyle(html).paddingRight) || 0;
                html.style.setProperty('overflow', 'hidden', 'important');
                body.style.setProperty('overflow', 'hidden', 'important');
                // Stable gutter is preferred. Compensate only if this browser actually lost width.
                const lostGutter = Math.max(0, html.getBoundingClientRect().width - width);
                if (lostGutter) html.style.setProperty('padding-right', `${padding + lostGutter}px`);
                observer.observe(body, { childList: true, subtree: true });
            }
            isolate();
            window.queueMicrotask(() => {
                if (top() === root && !root.contains(document.activeElement)) focus(root);
            });
        },
        close(root) {
            if (!active.has(root)) return;
            const trigger = active.get(root);
            const wasTop = top() === root;
            active.delete(root);
            if (active.size) isolate();
            else {
                observer.disconnect();
                restoreIsolation();
                for (const { element, overflow, priority, padding, paddingPriority } of savedStyles) {
                    if (overflow) element.style.setProperty('overflow', overflow, priority);
                    else element.style.removeProperty('overflow');
                    if (padding) element.style.setProperty('padding-right', padding, paddingPriority);
                    else element.style.removeProperty('padding-right');
                }
                savedStyles = null;
            }
            if (wasTop && trigger?.isConnected && !trigger.closest('[inert]')) trigger.focus({ preventScroll: true });
            else if (wasTop && top()) focus(top());
        },
    };
}

export function registerModalInteraction(Alpine, interaction) {
    Alpine.directive('modal-layer', (element, { expression }, { evaluateLater, effect, cleanup }) => {
        const evaluate = evaluateLater(expression);
        effect(() => evaluate(open => {
            if (open) {
                interaction.open(element);
                // Alpine's x-show finishes mounting visibility on its next tick.
                Alpine.nextTick(() => interaction.focus(element));
            } else interaction.close(element);
        }));
        cleanup(() => interaction.close(element));
    });
}
