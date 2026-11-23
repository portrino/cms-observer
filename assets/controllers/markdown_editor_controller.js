/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

/*
 * A small Markdown editor (EasyMDE) on a textarea: bold, italic, lists, links and a
 * preview. The textarea stays the form field (forceSync); its editor is available
 * as element.easymde, e.g. for the note dialog that fills it per row.
 * The toolbar shows the project's Lucide icons, rendered by Twig into the
 * <template id="markdown-editor-icons"> of the page, instead of Font Awesome from a CDN.
 */
export default class extends Controller {
    connect() {
        const icons = document.getElementById('markdown-editor-icons')?.content;
        const icon = (name) => icons?.querySelector(`[data-icon="${name}"]`)?.innerHTML ?? name;
        // the page's language: titles come with the icons, the English ones are the fallback
        const title = (name, fallback) => icons?.querySelector(`[data-icon="${name}"]`)?.dataset.title ?? fallback;
        const button = (name, action, iconName, fallback, extra = {}) => ({ name, action, title: title(iconName, fallback), className: `easymde-${name}`, icon: icon(iconName), ...extra });
        this.editor = new EasyMDE({
            element: this.element,
            forceSync: true,
            spellChecker: false,
            status: false,
            autoDownloadFontAwesome: false,
            minHeight: this.element.rows > 3 ? '12rem' : '4rem',
            toolbar: [
                button('bold', EasyMDE.toggleBold, 'bold', 'Bold'),
                button('italic', EasyMDE.toggleItalic, 'italic', 'Italic'),
                '|',
                button('unordered-list', EasyMDE.toggleUnorderedList, 'list', 'Bulleted list'),
                button('ordered-list', EasyMDE.toggleOrderedList, 'list-ordered', 'Numbered list'),
                button('link', EasyMDE.drawLink, 'link', 'Link'),
                '|',
                // noDisable: the preview disables the other buttons, not the one that ends it
                button('preview', EasyMDE.togglePreview, 'eye', 'Preview', { noDisable: true }),
            ],
        });
        this.element.easymde = this.editor;
    }

    disconnect() {
        this.editor?.toTextArea();
        delete this.element.easymde;
        this.editor = null;
    }
}
