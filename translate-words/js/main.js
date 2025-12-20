jQuery( document ).ready(
	function( $ ) {

		$( 'a.submitDeleteTranslation' ).on(
			'click',
			function( e ) {
				e.preventDefault();
				var rowID = $( this ).attr( 'id' ) + '_translate';
				$( '#' + rowID ).remove();
			}
		);

		$( '#addTranslation' ).on(
			'click',
			addRowTranslate
		);

	}
);

function addRowTranslate( e ) {

	e.preventDefault();

	jQuery( '#rowsTranslations' ).append( tww_properties.template );

	return false;

}

