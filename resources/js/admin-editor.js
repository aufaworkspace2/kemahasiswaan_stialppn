import Quill from 'quill';
import { createIcons, Bold, Italic, List, ListOrdered, Quote, Link, RemoveFormatting, ChevronDown } from 'lucide';
import DOMPurify from 'dompurify';
import 'quill/dist/quill.snow.css';

const allowedTags = ['p', 'br', 'strong', 'em', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a'];
const clean = html => DOMPurify.sanitize(html, { ALLOWED_TAGS: allowedTags, ALLOWED_ATTR: ['href', 'title', 'rel'] });

export function initializeEditors(root = document) {
    root.querySelectorAll('[data-rich-text]:not([data-editor-ready])').forEach(field => {
        const textarea = field.querySelector('textarea');
        const surface = field.querySelector('[data-editor-surface]');
        const format = field.querySelector('input[type="hidden"]');
        const status = field.querySelector('[data-editor-status]');
        const required = field.dataset.required === 'true';
        const maxLength = Number(field.dataset.maxLength);
        const quill = new Quill(surface, {
            theme: 'snow',
            bounds: field,
            placeholder: 'Tulis konten di sini...',
            formats: ['header', 'bold', 'italic', 'blockquote', 'list', 'link'],
            modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']], history: { delay: 700, maxStack: 60, userOnly: true } },
        });
        if (field.dataset.format === 'html') {
            quill.setContents(quill.clipboard.convert({ html: clean(textarea.value) }), 'silent');
        } else {
            quill.setText(textarea.value, 'silent');
        }
        surface.hidden = false;
        textarea.hidden = true;
        textarea.required = false;
        format.value = 'html';
        quill.root.setAttribute('role', 'textbox');
        quill.root.setAttribute('aria-multiline', 'true');
        quill.root.setAttribute('aria-labelledby', `${textarea.id}-label`);
        quill.root.setAttribute('aria-required', String(required));
        const labels = { 'ql-bold': 'Tebal', 'ql-italic': 'Miring', 'ql-blockquote': 'Kutipan', 'ql-link': 'Tautan', 'ql-clean': 'Hapus format', 'ql-header': 'Jenis judul' };
        field.querySelectorAll('.ql-toolbar button, .ql-toolbar select').forEach(button => {
            const key = Object.keys(labels).find(key => button.classList.contains(key));
            const label = button.classList.contains('ql-list') ? (button.value === 'ordered' ? 'Daftar bernomor' : 'Daftar poin') : (labels[key] || 'Format teks');
            button.setAttribute('aria-label', label);
            button.title = label;
            if (button.tagName === 'BUTTON') {
                const iconName = button.classList.contains('ql-list')
                    ? (button.value === 'ordered' ? 'list-ordered' : 'list')
                    : ({ 'ql-bold': 'bold', 'ql-italic': 'italic', 'ql-blockquote': 'quote', 'ql-link': 'link', 'ql-clean': 'remove-formatting' })[key];
                if (iconName) {
                    const icon = document.createElement('i');
                    icon.setAttribute('data-editor-icon', iconName);
                    button.replaceChildren(icon);
                }
            }
        });
        field.querySelectorAll('.ql-picker-label > svg').forEach(arrow => {
            const icon = document.createElement('i');
            icon.setAttribute('data-editor-icon', 'chevron-down');
            arrow.replaceWith(icon);
        });
        createIcons({ root: field, nameAttr: 'data-editor-icon', icons: { Bold, Italic, List, ListOrdered, Quote, Link, RemoveFormatting, ChevronDown }, attrs: { 'aria-hidden': 'true', focusable: 'false', 'stroke-width': 1.75 } });
        const sync = () => {
            const length = quill.getText().trim().length;
            textarea.value = length ? clean(quill.getSemanticHTML()) : '';
            status.textContent = length > maxLength ? `Isi melebihi batas ${maxLength} karakter.` : '';
            status.classList.toggle('field-error', length > maxLength);
            return length;
        };
        sync();
        quill.on('text-change', sync);
        field.closest('form').addEventListener('submit', event => {
            if (!field.isConnected) return;
            const length = sync();
            if ((required && length === 0) || length > maxLength || textarea.value.length > maxLength) {
                event.preventDefault();
                event.stopImmediatePropagation();
                status.textContent = length === 0 ? 'Isi konten wajib diisi.' : `Isi melebihi batas ${maxLength} karakter.`;
                status.classList.add('field-error');
                quill.root.setAttribute('aria-invalid', 'true');
                quill.focus();
            }
        }, true);
        const serverError = field.querySelector('.field-error');
        if (serverError) {
            quill.root.setAttribute('aria-invalid', 'true');
            if (serverError.id) quill.root.setAttribute('aria-describedby', serverError.id);
            if (document.querySelector('.field-error') === serverError) quill.focus();
        }
        field.dataset.editorReady = 'true';
    });
}
