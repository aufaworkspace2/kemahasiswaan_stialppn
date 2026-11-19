import { renderIcons } from './icons';

renderIcons();

document.querySelectorAll('[data-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.toggle);
        const open = target.classList.toggle('is-open');
        button.setAttribute('aria-expanded', String(open));
    });
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        document.querySelectorAll('details[open]').forEach(details => details.open = false);
        document.querySelectorAll('[data-toggle][aria-expanded="true"]').forEach(button => {
            document.getElementById(button.dataset.toggle)?.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            button.focus();
        });
    }
});
document.querySelectorAll('[data-submit-form]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        if (event.submitter?.name) {
            const value = document.createElement('input');
            value.type = 'hidden'; value.name = event.submitter.name; value.value = event.submitter.value;
            form.append(value);
        }
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => button.disabled = true);
        const status = form.querySelector('[data-submit-status]');
        if (status) status.textContent = 'Sedang menyimpan. Mohon tunggu…';
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-submitting]').forEach(form => {
        form.dataset.submitting = 'false';
        form.querySelectorAll('button:disabled').forEach(button => button.disabled = false);
    });
});
const channel = document.querySelector('[data-reply-channel]');
function updateChannel() {
    document.querySelectorAll('[data-channel]').forEach(group => {
        const active = group.dataset.channel === channel.value;
        group.hidden = !active;
        group.querySelector('input').required = active;
        group.querySelector('input').disabled = !active;
    });
}
if (channel) { channel.addEventListener('change', updateChannel); updateChannel(); }
document.querySelectorAll('[data-max-file]').forEach(input => {
    input.addEventListener('change', () => {
        const oversized = input.files[0]?.size > Number(input.dataset.maxFile);
        input.setCustomValidity(oversized ? 'Ukuran file maksimal 5 MB.' : '');
        if (oversized) input.reportValidity();
    });
});
document.querySelectorAll('[data-copy]').forEach(button => {
    button.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(document.getElementById(button.dataset.copy).textContent); const icon = document.createElement('i'); icon.setAttribute('data-lucide', 'check'); icon.className = 'h-4 w-4'; button.replaceChildren(document.createTextNode('Nomor disalin '), icon); renderIcons(button); }
        catch { button.textContent = 'Pilih nomor untuk menyalin'; }
    });
});
const builder = document.querySelector('[data-builder]');
if (builder) {
    const list = builder.querySelector('[data-field-list]');
    function renameFields() {
        list?.querySelectorAll('[data-builder-field]').forEach((field, index) => {
            field.querySelectorAll('[data-prop]').forEach(input => input.name = `fields[${index}][${input.dataset.prop}]`);
        });
    }
    builder.querySelector('[data-add-field]')?.addEventListener('click', () => {
        const template = document.getElementById('builder-field-template');
        const fragment = template.content.cloneNode(true);
        const field = fragment.querySelector('[data-builder-field]');
        list.append(fragment); renderIcons(field); renameFields(); field.querySelector('input')?.focus();
    });
    list?.addEventListener('click', event => {
        const button = event.target.closest('button'); if (!button) return;
        const field = button.closest('[data-builder-field]');
        if (button.hasAttribute('data-field-remove')) field.remove();
        else if (button.hasAttribute('data-field-up') && field.previousElementSibling) list.insertBefore(field, field.previousElementSibling);
        else if (button.hasAttribute('data-field-down') && field.nextElementSibling) list.insertBefore(field.nextElementSibling, field);
        renameFields();
    });
}
const categoryService = document.querySelector('[data-category-service]');
if (categoryService) {
    const categorySelect = document.querySelector('[data-category-select]');
    const options = [...categorySelect.options].map(option => option.cloneNode(true));
    const update = () => { categorySelect.replaceChildren(...options.filter(option => option.dataset.service === categoryService.value).map(option => option.cloneNode(true))); };
    categoryService.addEventListener('change', update); update();
}
const errors = document.getElementById('error-summary');
if (errors) {
    errors.focus();
    document.querySelectorAll('.field-error').forEach((error, index) => {
        const input = error.closest('div')?.querySelector('input,select,textarea');
        if (input) {
            error.id = `field-error-${index}`;
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', error.id);
            if (index === 0) input.focus();
        }
    });
}

const publicNavigation = document.querySelector('[data-page-active]');
if (publicNavigation) {
    const updateActiveNavigation = () => {
        const current = new URL(window.location.href);
        const links = [...publicNavigation.querySelectorAll('a[href]')];
        links.forEach(link => {
            const target = new URL(link.href, current);
            const sameOrigin = target.origin === current.origin;
            const samePath = target.pathname.replace(/\/$/, '') === current.pathname.replace(/\/$/, '');
            const nestedPath = target.pathname !== '/' && current.pathname.startsWith(`${target.pathname}/`);
            const active = sameOrigin && (samePath || nestedPath) && (target.hash ? target.hash === current.hash : !current.hash);
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', target.hash ? 'location' : 'page');
            else link.removeAttribute('aria-current');
        });
        publicNavigation.querySelectorAll('.nav-dropdown').forEach(dropdown => {
            dropdown.querySelector('summary').classList.toggle('active', Boolean(dropdown.querySelector('a.active')));
        });
    };
    window.addEventListener('hashchange', updateActiveNavigation);
    updateActiveNavigation();
    publicNavigation.addEventListener('click', event => {
        if (!event.target.closest('a')) return;
        publicNavigation.querySelectorAll('details[open]').forEach(item => item.open = false);
        publicNavigation.classList.remove('is-open');
        document.querySelector('[data-toggle="main-nav"]')?.setAttribute('aria-expanded', 'false');
    });
}

const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
if (publicNavigation && !motionPreference.matches && 'IntersectionObserver' in window && 'animate' in Element.prototype) {
    const runningReveals = new Set();
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            revealObserver.unobserve(entry.target);
            if (motionPreference.matches) return;
            const siblings = [...entry.target.parentElement.children];
            const index = siblings.indexOf(entry.target);
            const staggered = entry.target.matches('.service-card, .news-card, .steps-list li');
            const animation = entry.target.animate(
                [{ opacity: 0, transform: 'translateY(18px)' }, { opacity: 1, transform: 'translateY(0)' }],
                { duration: 520, delay: staggered ? Math.min(index, 3) * 65 : 0, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'backwards' },
            );
            runningReveals.add(animation);
            animation.finished.then(() => runningReveals.delete(animation), () => runningReveals.delete(animation));
        });
    }, { threshold: 0.08 });
    document.querySelectorAll('#main .section-heading, #main .service-card, #main .steps-layout > div, #main .steps-list li, #main .news-card, #main .faq-layout > div:first-child, #main .faq-list details, #main .contact-box').forEach(element => {
        if (element.getBoundingClientRect().top >= window.innerHeight) revealObserver.observe(element);
    });
    motionPreference.addEventListener('change', event => {
        if (!event.matches) return;
        revealObserver.disconnect();
        runningReveals.forEach(animation => animation.cancel());
        runningReveals.clear();
    });
}

if (document.querySelector('[data-rich-text]')) {
    import('./admin-editor').then(module => module.initializeEditors()).catch(() => {
        document.querySelectorAll('[data-editor-status]').forEach(status => status.textContent = 'Editor tidak tersedia. Gunakan kolom teks untuk melanjutkan.');
    });
}
const menuTarget = document.querySelector('[data-menu-target]');
if (menuTarget) {
    const updateMenuTarget = () => {
        document.querySelectorAll('[data-menu-for]').forEach(group => {
            const active = group.dataset.menuFor === menuTarget.value;
            group.hidden = !active;
            group.querySelectorAll('input,select').forEach(input => {
                input.disabled = !active;
                if (input.hasAttribute('data-menu-id')) input.name = active ? 'target_id' : `${group.dataset.menuFor}_target_id`;
            });
        });
    };
    menuTarget.addEventListener('change', updateMenuTarget);
    updateMenuTarget();
}

const navigationEditor = document.querySelector('[data-navigation-editor]');
if (navigationEditor) {
    import('./admin-navigation').then(module => module.initializeNavigation(navigationEditor)).catch(() => {
        navigationEditor.querySelector('[data-navigation-status]').textContent = 'Pengatur susunan belum tersedia. Muat ulang atau gunakan halaman Edit menu.';
    });
}

const sectionEditor = document.querySelector('[data-section-editor]');
if (sectionEditor) {
    import('./admin-sections').then(module => module.initializeSections(sectionEditor));
}
