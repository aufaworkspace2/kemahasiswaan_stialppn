import { renderIcons } from './icons';
export function initializeSections(editor) {
    const list = editor.querySelector('[data-section-items]');
    const add = editor.querySelector('[data-section-add]');
    const status = editor.querySelector('[data-section-item-status]');
    const rows = () => [...list.querySelectorAll('[data-section-item]')];
    function links(root) { root.querySelectorAll('[data-section-link]').forEach(link => { const select = link.querySelector('[data-section-target]'); link.querySelector('[data-section-url]').hidden = select.value !== 'url'; }); }
    function typeFields() { editor.querySelector('[data-section-source]').hidden = !['services','news'].includes(editor.querySelector('[data-section-type]').value); }
    function refresh() {
        rows().forEach((row,index)=>{
            row.querySelector('[data-item-heading]').textContent = `Item ${index+1}`;
            row.querySelectorAll('[name]').forEach(input=>input.name=input.name.replace(/\[items\]\[[^\]]+\]/,`[items][${index}]`));
            row.querySelector('[data-section-item-move="up"]').disabled = index===0;
            row.querySelector('[data-section-item-move="down"]').disabled = index===rows().length-1;
        });
        add.disabled = rows().length >= 20; status.textContent = `${rows().length}/20 item`; links(editor); typeFields();
    }
    let serial = 0;
    add.addEventListener('click',()=>{ if(rows().length>=20)return; const fragment=editor.querySelector('[data-section-item-template]').content.cloneNode(true);fragment.querySelectorAll('[id],[for],[aria-labelledby]').forEach(element=>{['id','for','aria-labelledby'].forEach(attribute=>{if(element.hasAttribute(attribute))element.setAttribute(attribute,element.getAttribute(attribute).replaceAll('__INDEX__',`new-${serial}`));});});serial++;list.append(fragment);renderIcons(list);refresh();import('./admin-editor').then(module=>module.initializeEditors(list));rows().at(-1).querySelector('input').focus(); });
    list.addEventListener('click',event=>{const button=event.target.closest('[data-section-item-remove],[data-section-item-move]');if(!button)return;const row=button.closest('[data-section-item]');if(button.hasAttribute('data-section-item-remove'))row.remove();else if(button.dataset.sectionItemMove==='up'&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);else if(button.dataset.sectionItemMove==='down'&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);refresh();});
    editor.addEventListener('change',event=>{if(event.target.matches('[data-section-target]'))links(editor);if(event.target.matches('[data-section-type]'))typeFields();});
    refresh();editor.dataset.sectionReady='true';
}
