import { select, dispatch } from '@wordpress/data';

const KEY_PREFIX = '_lmat_term_name_';

/** Keep the editor form in agreement with the term IDs saved on the server. */
export const applyTermAssignments = (data) => {
    if (data.rest_terms && Object.keys(data.rest_terms).length) {
        try {
            dispatch('core/editor').editPost(data.rest_terms);
        } catch (error) {
            // Classic, WPBakery and Elementor have no block editor store.
        }
    }

    Object.entries(data.taxonomies || {}).forEach(([taxonomy, assignment]) => {
        const terms = assignment.terms || [];
        const ids = terms.map(term => Number(term.id));
        [taxonomy + 'checklist', taxonomy + 'checklist-pop'].forEach(listId => {
            const list = document.getElementById(listId);
            if (!list) return;
            list.querySelectorAll('input[type="checkbox"]').forEach(input => {
                input.checked = ids.includes(Number(input.value));
            });
            terms.forEach(term => {
                const existing = Array.from(list.querySelectorAll('input[type="checkbox"]'))
                    .find(input => Number(input.value) === Number(term.id));
                if (existing) {
                    const label = existing.closest('label');
                    if (label) {
                        Array.from(label.childNodes).filter(node => node.nodeType === 3).forEach(node => node.remove());
                        label.append(document.createTextNode(' ' + term.name));
                    }
                    return;
                }
                const item = document.createElement('li');
                const label = document.createElement('label');
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = `tax_input[${taxonomy}][]`;
                input.value = term.id;
                input.checked = true;
                label.append(input, document.createTextNode(' ' + term.name));
                item.append(label);
                list.append(item);
            });
        });

        if (!assignment.hierarchical) {
            Array.from(document.getElementsByName(`tax_input[${taxonomy}]`)).forEach(input => {
                input.value = terms.map(term => term.name).join(', ');
            });
            const box = document.getElementById('tagsdiv-' + taxonomy);
            if (box && window.tagBox?.quickClicks) window.tagBox.quickClicks(box);
        }
        Array.from(document.getElementsByName(`tax_input[${taxonomy}][]`))
            .filter(input => input.tagName === 'SELECT').forEach(input => {
                terms.forEach(term => {
                    if (!Array.from(input.options).some(option => Number(option.value) === Number(term.id))) {
                        input.add(new Option(term.name, term.id));
                    }
                });
                Array.from(input.options).forEach(option => { option.selected = ids.includes(Number(option.value)); });
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
    });
};

/** Only send actual provider results, never the translation store's source fallback. */
const saveTermTranslations = async (service) => {
    const config = window.lmatPageTranslationGlobal || {};
    const sourceId = Number(config.parent_post_id);
    const targetId = Number(config.current_post_id);
    if (!config.save_term_nonce || !sourceId || !targetId || sourceId === targetId) return;

    const names = {};
    select('block-lmatPageTranslation/translate').getTranslationEntries().forEach(entry => {
        const value = entry.translatedData?.[service];
        if (entry.type === 'metaFields' && entry.id.startsWith(KEY_PREFIX) && typeof value === 'string' && value.trim()) {
            names[entry.id] = value;
        }
    });
    const response = await fetch(config.ajax_url, {
        method: 'POST',
        headers: { 'content-type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json' },
        body: new URLSearchParams({
            action: config.save_term_translations,
            term_nonce: config.save_term_nonce,
            source_post_id: sourceId,
            post_id: targetId,
            term_names: JSON.stringify(names),
        }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.data || 'Term translations could not be saved.');
    }
    applyTermAssignments(result.data);
};

export default saveTermTranslations;
