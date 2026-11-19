export function initializeNavigation(editor) {
    const tree = editor.querySelector('.navigation-tree');
    const save = editor.querySelector('[data-navigation-save]');
    const status = editor.querySelector('[data-navigation-status]');
    const search = editor.querySelector('[data-navigation-search]');
    const visibility = editor.querySelector('[data-navigation-visibility]');
    const rows = () => [...tree.querySelectorAll('[data-menu-item]')];
    const roots = () => rows().filter(item => !item.dataset.parent);
    const children = item => rows().filter(child => child.dataset.parent === item.dataset.menuItem);
    let dirty = false;
    let busy = false;
    let dragging = null;
    let destination = null;
    const filtered = () => Boolean(search.value.trim() || visibility.value);
    const announce = message => { status.textContent = message; };
    function refresh() {
        const parentMenus = roots();
        rows().forEach(item => {
            const parent = item.dataset.parent;
            item.classList.toggle('navigation-submenu', Boolean(parent));
            item.querySelector('[data-menu-level]').textContent = parent ? `Submenu dari ${tree.querySelector(`[data-menu-item="${parent}"]`).dataset.menuLabel}` : 'Menu utama';
            const select = item.querySelector('[data-menu-parent]');
            select.replaceChildren(new Option('Menu utama', ''));
            parentMenus.filter(root => root !== item).forEach(root => select.add(new Option(root.dataset.menuLabel, root.dataset.menuItem)));
            select.value = parent || '';
            select.disabled = busy || filtered() || item.dataset.menuType === 'group' || children(item).length > 0;
            item.querySelector('[data-menu-handle]').disabled = busy || filtered();
            item.querySelectorAll('[data-menu-move]').forEach(button => button.disabled = busy || filtered());
            // Reordering can change deletion eligibility; re-evaluate it on the server after saving.
            item.querySelector('[data-menu-delete] button').disabled = dirty || busy || Boolean(item.dataset.deleteBlock);
        });
        save.disabled = !dirty || busy || filtered();
    }
    function changed() { dirty = true; refresh(); announce('Susunan berubah. Klik Simpan Susunan untuk menerapkannya.'); }
    function move(item, parent, before = null) {
        if (parent && (item.dataset.menuType === 'group' || children(item).length || parent === item.dataset.menuItem)) {
            announce('Pindahkan submenu anaknya terlebih dahulu. Grup harus tetap menjadi menu utama.'); refresh(); return;
        }
        if (before === item) return;
        const block = [item, ...children(item)];
        const ordered = rows().filter(row => !block.includes(row));
        const siblings = ordered.filter(row => row.dataset.parent === parent);
        let index = before ? ordered.indexOf(before) : ordered.length;
        if (!before && parent) {
            const last = siblings.at(-1) || ordered.find(row => row.dataset.menuItem === parent);
            index = ordered.indexOf(last) + 1;
        }
        item.dataset.parent = parent;
        ordered.splice(index, 0, ...block);
        const normalized = ordered.filter(row => !row.dataset.parent).flatMap(root => [root, ...ordered.filter(row => row.dataset.parent === root.dataset.menuItem)]);
        tree.append(...normalized, editor.querySelector('[data-navigation-empty]'));
        changed();
    }
    editor.addEventListener('change', event => {
        if (!event.target.matches('[data-menu-parent]')) return;
        const parent = event.target.value;
        move(event.target.closest('[data-menu-item]'), parent);
    });
    editor.addEventListener('click', event => {
        const button = event.target.closest('[data-menu-move]'); if (!button) return;
        const item = button.closest('[data-menu-item]');
        const siblings = rows().filter(row => row.dataset.parent === item.dataset.parent);
        const index = siblings.indexOf(item);
        if (button.dataset.menuMove === 'up' && index > 0) move(item, item.dataset.parent, siblings[index - 1]);
        if (button.dataset.menuMove === 'down' && index < siblings.length - 1) move(item, item.dataset.parent, siblings[index + 2] || null);
    });
    editor.addEventListener('pointerdown', event => {
        const handle = event.target.closest('[data-menu-handle]');
        if (!handle || handle.disabled || event.button !== 0) return;
        dragging = handle.closest('[data-menu-item]');
        handle.setPointerCapture(event.pointerId); dragging.classList.add('is-dragging'); event.preventDefault();
    });
    editor.addEventListener('pointermove', event => {
        if (!dragging) return;
        destination?.list.classList.remove('navigation-drop-target'); destination = null;
        const hit = document.elementFromPoint(event.clientX, event.clientY);
        const target = hit?.closest('[data-menu-item]');
        if (!target || !tree.contains(target) || target === dragging || children(dragging).includes(target)) return;
        const siblings = rows().filter(row => row.dataset.parent === target.dataset.parent && row !== dragging);
        const below = event.clientY > target.getBoundingClientRect().top + target.getBoundingClientRect().height / 2;
        const before = below ? siblings[siblings.indexOf(target) + 1] || null : target;
        destination = {list:target,parent:target.dataset.parent,before}; target.classList.add('navigation-drop-target');
        if (event.clientY > innerHeight - 65) window.scrollBy(0, 14);
        if (event.clientY < 65) window.scrollBy(0, -14);
    });
    function finish(cancelled = false) {
        if (!dragging) return;
        destination?.list.classList.remove('navigation-drop-target'); dragging.classList.remove('is-dragging');
        if (!cancelled && destination) move(dragging, destination.parent, destination.before);
        dragging = null; destination = null;
    }
    editor.addEventListener('pointerup', () => finish());
    editor.addEventListener('pointercancel', () => finish(true));
    editor.addEventListener('keydown', event => { if (event.key === 'Escape') finish(true); });
    function filter() {
        const query = search.value.trim().toLocaleLowerCase();
        const matches = item => item.dataset.menuLabel.toLocaleLowerCase().includes(query) && (!visibility.value || item.dataset.visible === visibility.value);
        roots().forEach(root => {
            const descendants = children(root); descendants.forEach(child => child.hidden = !matches(child));
            root.hidden = !matches(root) && !descendants.some(child => !child.hidden);
        });
        editor.querySelector('[data-navigation-empty]').hidden = roots().some(root => !root.hidden);
        refresh(); announce(filtered() ? 'Hapus pencarian dan filter untuk mengubah susunan.' : (dirty ? 'Susunan belum disimpan.' : `${rows().length} menu.`));
    }
    search.addEventListener('input', filter); visibility.addEventListener('change', filter);
    editor.querySelectorAll('[data-menu-delete]').forEach(form => form.addEventListener('submit', event => {
        if (dirty || !window.confirm('Hapus menu ini? Halaman dan layanan tujuannya tetap tersimpan.')) event.preventDefault();
    }));
    save.addEventListener('click', async () => {
        if (busy || !dirty || filtered()) return;
        busy = true; refresh(); announce('Menyimpan susunan...');
        const items = [];
        roots().forEach(root => { items.push({id:Number(root.dataset.menuItem),parent_id:null}); children(root).forEach(child => items.push({id:Number(child.dataset.menuItem),parent_id:Number(root.dataset.menuItem)})); });
        try {
            const response = await fetch(editor.dataset.orderUrl, {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':editor.dataset.token},body:JSON.stringify({revision:editor.dataset.revision,items})});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Susunan belum tersimpan. Coba lagi.');
            dirty = false; window.location.reload();
        } catch(error) { announce(error.message); busy = false; refresh(); }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    refresh();
    editor.dataset.navigationReady = 'true';
}
