
if ( tww_translations.length > 0 ) {

	var pluginGettextFilter = function( translation ) {

		var newTranslation = translation;

		jQuery.each(
			tww_translations,
			function( key, object ) {
				if ( translation === object.original ) {

					newTranslation = object.overwrite;

					// Quit the jquery loop.
					return false;

				}
			}
		);

		return newTranslation;

	};

	wp.hooks.addFilter(
		'i18n.gettext',
		'tww/translate-words-filter',
		pluginGettextFilter,
		99
	);

	wp.hooks.addFilter(
		'i18n.ngettext',
		'tww/translate-words-filter',
		pluginGettextFilter,
		99
	);

}
