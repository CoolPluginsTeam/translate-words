import { select } from '@wordpress/data';

/**
 * Media string key prefix used when saving media strings into the translation store.
 * Must match the prefix used in store-source-string/gutenberg/index.js.
 */
const MEDIA_KEY_PREFIX = 'lmat_media_';

/**
 * Sends translated attachment metadata to the server via AJAX.
 *
 * Reads every translation entry whose id starts with MEDIA_KEY_PREFIX,
 * reconstructs the featured_image and content_media payload structures
 * expected by save_media_translations(), and POSTs them.
 *
 * @param {string} service     The active AI provider key (e.g. 'gemini', 'openai').
 * @param {Object} postContent The original post_data returned by fetch_post_content.
 */
const saveMediaTranslations = ( service, postContent ) => {
    if ( ! lmatPageTranslationGlobal.mediaSupport ) return;

    const ajaxUrl  = lmatPageTranslationGlobal.ajax_url;
    const nonce    = lmatPageTranslationGlobal.save_media_nonce;
    const action   = lmatPageTranslationGlobal.save_media_translations;
    const postId   = lmatPageTranslationGlobal.current_post_id;
    const parentId = lmatPageTranslationGlobal.parent_post_id;

    if ( ! ajaxUrl || ! nonce || ! action || ! postId ) return;

    const allEntries = select( 'block-lmatPageTranslation/translate' ).getTranslationEntries();
    const mediaEntries = allEntries.filter(
        ( e ) => e.type === 'content' && e.id.startsWith( MEDIA_KEY_PREFIX )
    );

    if ( mediaEntries.length === 0 ) return;

    const getTranslated = ( storeKey, fallback ) => {
        const entry = mediaEntries.find( ( e ) => e.id === storeKey );
        if ( entry ) {
            return ( entry.translatedData && entry.translatedData[ service ] ) || fallback || '';
        }
        return fallback || '';
    };

    // Featured image.
    let featuredImage = null;
    if ( postContent && postContent.featured_image ) {
        const fi = postContent.featured_image;
        const rebuilt = { id: fi.id };
        [ 'title', 'alt', 'caption', 'description' ].forEach( ( f ) => {
            rebuilt[ f ] = getTranslated( `${ MEDIA_KEY_PREFIX }featured_${ f }`, fi[ f ] );
        } );
        featuredImage = rebuilt;
    }

    // Content media.
    let contentMedia = null;
    if ( postContent && Array.isArray( postContent.content_media ) ) {
        contentMedia = postContent.content_media.map( ( a ) => {
            const r = { id: a.id };
            [ 'title', 'alt', 'caption', 'description' ].forEach( ( f ) => {
                r[ f ] = getTranslated( `${ MEDIA_KEY_PREFIX }${ a.id }_${ f }`, a[ f ] );
            } );
            return r;
        } );
    }

    if ( ! featuredImage && ! contentMedia ) return;

    const requestBody = {
        action:           action,
        post_id:          postId,
        source_post_id:   parentId || postId,
        lmat_media_nonce: nonce,
    };
    if ( featuredImage ) requestBody.featured_image = JSON.stringify( featuredImage );
    if ( contentMedia )  requestBody.content_media  = JSON.stringify( contentMedia );

    fetch( ajaxUrl, {
        method:  'POST',
        headers: { 'content-type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json' },
        body:    new URLSearchParams( requestBody ),
    } ).catch( ( err ) => console.error( 'lmat: saveMediaTranslations error', err ) );
};

export default saveMediaTranslations;
