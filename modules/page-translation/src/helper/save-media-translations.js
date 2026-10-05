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
 * @param {Object} postContent The original post data returned by the server.
 * @return {Promise<boolean>} Whether media metadata was saved.
 */
const saveMediaTranslations = async ( service, postContent ) => {
    if ( ! lmatPageTranslationGlobal.mediaSupport ) return false;

    const ajaxUrl  = lmatPageTranslationGlobal.ajax_url;
    const nonce    = lmatPageTranslationGlobal.save_media_nonce;
    const action   = lmatPageTranslationGlobal.save_media_translations;
    const postId   = lmatPageTranslationGlobal.current_post_id;
    const parentId = lmatPageTranslationGlobal.parent_post_id;

    if ( ! ajaxUrl || ! nonce || ! action || ! postId || ! parentId ) return false;

    const allEntries = select( 'block-lmatPageTranslation/translate' ).getTranslationEntries();
    const mediaEntries = allEntries.filter(
        ( e ) => e.type === 'content' && typeof e.id === 'string' && e.id.startsWith( MEDIA_KEY_PREFIX )
    );

    if ( mediaEntries.length === 0 ) return false;

    const getTranslated = ( storeKey ) => {
        const entry = mediaEntries.find( ( e ) => e.id === storeKey );
        if ( entry && entry.translatedData && typeof entry.translatedData[ service ] === 'string' ) {
            return entry.translatedData[ service ].trim();
        }
        return '';
    };

    const buildTranslatedFields = ( attachment, keyPrefix ) => {
        const translated = {};
        [ 'title', 'alt', 'caption', 'description' ].forEach( ( field ) => {
            if ( ! attachment[ field ] ) return;
            const value = getTranslated( `${ keyPrefix }${ field }` );
            if ( value ) translated[ field ] = value;
        } );
        return translated;
    };

    // Featured image.
    let featuredImage = null;
    if ( postContent && postContent.featured_image ) {
        const fi = postContent.featured_image;
        const translated = buildTranslatedFields( fi, `${ MEDIA_KEY_PREFIX }featured_` );
        if ( Object.keys( translated ).length > 0 ) featuredImage = translated;
    }

    // Content and Elementor media use the same attachment-key format.
    const sourceMedia = [
        ...( Array.isArray( postContent?.content_media ) ? postContent.content_media : [] ),
        ...( Array.isArray( postContent?.elementor_media ) ? postContent.elementor_media : [] ),
    ];
    const mediaById = new Map();
    sourceMedia.forEach( ( attachment ) => {
        if ( attachment?.id ) mediaById.set( Number( attachment.id ), attachment );
    } );

    const contentMedia = [];
    mediaById.forEach( ( attachment, attachmentId ) => {
        const translated = buildTranslatedFields( attachment, `${ MEDIA_KEY_PREFIX }${ attachmentId }_` );
        if ( Object.keys( translated ).length > 0 ) {
            contentMedia.push( { id: attachmentId, ...translated } );
        }
    } );

    if ( ! featuredImage && contentMedia.length === 0 ) return false;

    const requestBody = {
        action:           action,
        post_id:          postId,
        source_post_id:   parentId,
        lmat_media_nonce: nonce,
    };
    if ( featuredImage ) requestBody.featured_image = JSON.stringify( featuredImage );
    if ( contentMedia.length > 0 ) requestBody.content_media = JSON.stringify( contentMedia );

    try {
        const response = await fetch( ajaxUrl, {
        method:  'POST',
        headers: { 'content-type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json' },
        body:    new URLSearchParams( requestBody ),
        } );
        const result = await response.json();
        if ( ! response.ok || ! result.success || ( result.data && result.data.updated === false ) ) {
            console.error( 'lmat: media translations were not saved', result.data || result );
            return false;
        }
        return true;
    } catch ( error ) {
        console.error( 'lmat: saveMediaTranslations error', error );
        return false;
    }
};

export default saveMediaTranslations;
