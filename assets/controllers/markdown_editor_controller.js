/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

/*
 * A small Markdown editor (EasyMDE) on a textarea: bold, italic, lists, links and a
 * preview. The textarea stays the form field (forceSync); its editor is available
 * as element.easymde, e.g. for the note dialog that fills it per row.
 */
export default class extends Controller {
    connect() {
        // text instead of icons: EasyMDE would load Font Awesome from a CDN
        const button = (name, action, label, title) => ({ name, action, title, className: `easymde-${name}`, icon: label });
        this.editor = new EasyMDE({
            element: this.element,
            forceSync: true,
            spellChecker: false,
            status: false,
            autoDownloadFontAwesome: false,
            minHeight: this.element.rows > 3 ? '12rem' : '4rem',
            toolbar: [
                button('bold', EasyMDE.toggleBold, '<b>B</b>', 'Bold'),
                button('italic', EasyMDE.toggleItalic, '<i>I</i>', 'Italic'),
                '|',
                button('unordered-list', EasyMDE.toggleUnorderedList, '•&nbsp;List', 'Bulleted list'),
                button('ordered-list', EasyMDE.toggleOrderedList, '1.&nbsp;List', 'Numbered list'),
                button('link', EasyMDE.drawLink, 'Link', 'Link'),
                '|',
                button('preview', EasyMDE.togglePreview, 'Preview', 'Preview'),
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
