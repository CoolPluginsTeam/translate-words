import { applyFilters } from '@wordpress/hooks';

/**
 * Runs the `lmat.bulkTranslation.sourceString` filter on a string before it is sent to the translation provider.
 *
 * @param {string} content Source string, after the HTML filtering.
 * @param {Object} context { service, sourceLanguage, postId, key }.
 * @returns {string} The filtered string, or the original string if the filter does not return a string.
 */
const filterSourceString = (content, context = {}) => {
    if (typeof content !== 'string' || '' === content) {
        return content;
    }

    const filtered = applyFilters('lmat.bulkTranslation.sourceString', content, context);

    return typeof filtered === 'string' ? filtered : content;
};

export default filterSourceString;
