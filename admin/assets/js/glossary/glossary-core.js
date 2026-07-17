(function($, G) {
    'use strict';

    G.escapeHtml = function(text) {
        if (typeof text !== 'string') return text;
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

    G.sanitizeInput = function(input) {
        return input.replace(/[<>]/g, ''); // Remove < and > characters
    };

    G.initCache = function() {
        if (G._cacheReady) return;

        G.$glossaryTable = $('.lmat-glossary-table');
        G.$glossaryTableWrapper = $('.lmat-glossary-table-wrapper');
        G.$languageFilters = $('.lmat-language-filters');
        G.$alphabet = $('.lmat-alphabet');
        G.$addGlossaryForm = $('#lmat-add-glossary-form');
        G.$addBtn = $('.lmat-add-btn');
        G.$importBtn = $('.lmat-import-btn');
        G.$noResults = $('#lmat-no-results');
        G.$addGlossarySuccess = $('#add-glossary-success');
        G.$glossaryType = $('.lmat-glossary-type');
        G.$glossarySearch = $('.lmat-search');
        G.$glossaryRows = G.$glossaryTable.find('tbody tr');
        G.searchDebounceTimer = null;

        G._cacheReady = true;
    };

    G.refreshGlossaryRows = function() {
        G.$glossaryRows = G.$glossaryTable.find('tbody tr');
        return G.$glossaryRows;
    };

    G.getActiveLangFilterBtn = function() {
        return G.$languageFilters.find('.lmat-lang-filter-btn.active');
    };

    G.getActiveAlphabetBtn = function() {
        return G.$alphabet.find('.lmat-alphabet-btn.active:not([disabled])');
    };

    G.applyZebraStriping = function() {
        G.refreshGlossaryRows().removeClass('lmat-row-striped');
        G.$glossaryRows.filter(':visible:not(.lmat-glossary-edit-row)').each(function(i) {
            if (i % 2 === 1) {
                $(this).addClass('lmat-row-striped');
            }
        });
    };

    G.updateGlossaryTableVisibility = function() {
        var $visibleRows = G.refreshGlossaryRows().filter(':visible');
        if ($visibleRows.length === 0) {
            G.$glossaryTableWrapper.hide();
            if (!G.$noResults.length) {
                G.$glossaryTableWrapper.after('<div id="lmat-no-results" style="text-align:center; margin: 32px 0; color: #888; font-size: 1.2em;">No glossary entries found.</div>');
                G.$noResults = $('#lmat-no-results');
            }
        } else {
            G.$glossaryTableWrapper.show();
            G.$noResults.remove();
            G.$noResults = $();
        }
        G.applyZebraStriping();
    };

    G.updateAlphabetButtonStates = function() {
        G.$alphabet.find('.lmat-alphabet-btn').prop('disabled', false);
        var visibleLetters = {};
        G.refreshGlossaryRows().filter(':visible').each(function() {
            var letter = $(this).data('letter');
            if (letter) visibleLetters[letter] = true;
        });
        G.$alphabet.find('.lmat-alphabet-btn').each(function() {
            var $btn = $(this);
            var letter = $btn.data('letter');
            if (!visibleLetters[letter]) {
                $btn.prop('disabled', true);
                $btn.removeClass('active');
            }
        });

        // After updating the table and language filter buttons
        var $alphabetBtns = G.$alphabet.find('.lmat-alphabet-btn');
        var $visibleRows = G.$glossaryRows.filter(':visible');

        // If there are no visible rows, remove active state from all alphabet buttons
        if ($visibleRows.length === 0) {
            $alphabetBtns.removeClass('active');
        }
    };

    G.filterGlossaryRows = function() {
        var selectedLang = G.getActiveLangFilterBtn().data('lang') || '';
        var selectedType = G.$glossaryType.val() || '';
        var selectedLetter = G.getActiveAlphabetBtn().data('letter') || '';
        var search = (G.$glossarySearch.val() || '').toLowerCase();

        G.refreshGlossaryRows().each(function() {
            var $row = $(this);
            var rowOriginalLang = $row.data('original-language');
            var rowType = $row.data('type');
            var rowLetter = $row.data('letter');
            var term = $row.find('.lmat-entry-title').text().toLowerCase();
            var desc = $row.find('.lmat-entry-desc').text().toLowerCase();

            // Start with strict language filter
            var show = (!selectedLang || rowOriginalLang === selectedLang);

            // Apply other filters only if row passes language filter
            if (show && selectedType) {
                show = (rowType === selectedType);
            }

            if (show && selectedLetter) {
                show = (rowLetter === selectedLetter);
            }

            if (show && search) {
                show = (term.indexOf(search) !== -1 || desc.indexOf(search) !== -1);
            }

            // Handle edit rows visibility
            if ($row.hasClass('lmat-glossary-edit-row')) {
                show = $row.prev('tr').is(':visible');
            }

            $row.toggle(show);
        });

        G.updateGlossaryTableVisibility();
        G.applyZebraStriping();
    };

    G.updateLanguageFilterButtons = function(originalLang) {
        if (!originalLang) return;

        const $filters = $('.lmat-language-filters');
        // Check if a button for this language already exists in the filter
        if ($filters.find('.lmat-lang-filter-btn[data-lang="' + originalLang + '"]').length === 0) {
            // Find the language object for label and flag
            let langObj = (lmat_glossary.lmat_languages || []).find(l => l.code === originalLang);
            let label = langObj ? (langObj.alt + ' Terms') : (originalLang + ' Terms');
            let flag = langObj && langObj.img ? `<img src="${langObj.img}" alt="${langObj.alt}" /> ` : '';

            $filters.append(`
                <button class="lmat-lang-filter-btn" data-lang="${originalLang}">
                    ${flag}${label}
                </button>
            `);
        }

        // After append, check total number of filter buttons
        const totalBtns = $filters.find('.lmat-lang-filter-btn').length;
        if (totalBtns <= 1) {
            // If only one, remove all (hide filter bar)
            $filters.empty();
        }
    };

    // Cache selectors as soon as DOM is ready (before other module inits).
    $(function() {
        G.initCache();
    });

})(jQuery, window.LmatGlossary = window.LmatGlossary || {});
