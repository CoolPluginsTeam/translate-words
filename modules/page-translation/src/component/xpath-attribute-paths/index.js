/**
 * Attribute keys that hold technical values and are never translated, even when
 * their value equals text selected by an XPath rule.
 */
const NON_TRANSLATABLE_KEYS = [
	'url',
	'href',
	'src',
	'id',
	'type',
	'anchor',
	'className',
	'providerNameSlug',
	'linkDestination',
	'sizeSlug',
];

/**
 * Returns the attribute rule groups of a block rule, leaving out the XPath list.
 *
 * @param {Object} blockRule Block rule, e.g. { attributes: {...}, xpaths: [...] }.
 * @return {Array} Attribute rule objects.
 */
export const getRuleAttributeGroups = ( blockRule ) => {
	if ( ! blockRule || 'object' !== typeof blockRule ) {
		return [];
	}

	return Object.keys( blockRule )
		.filter( ( key ) => 'xpaths' !== key )
		.map( ( key ) => blockRule[ key ] )
		.filter( ( group ) => group && 'object' === typeof group );
};

/**
 * Checks whether a block rule relies on XPath selectors only, without any attribute rule.
 *
 * @param {Object} blockRule Block rule.
 * @return {boolean} True when the rule has XPath selectors and no attribute rules.
 */
export const hasOnlyXpathRules = ( blockRule ) => {
	if (
		! blockRule ||
		! Array.isArray( blockRule.xpaths ) ||
		0 === blockRule.xpaths.length
	) {
		return false;
	}

	return getRuleAttributeGroups( blockRule ).every(
		( group ) => 0 === Object.keys( group ).length
	);
};

/**
 * Converts an HTML fragment to its plain text with entities decoded and whitespace collapsed.
 *
 * @param {string}   html Markup or text.
 * @param {Document} doc  Inert document used to decode the markup.
 * @return {string} Normalized text.
 */
const toPlainText = ( html, doc ) => {
	const container = doc.createElement( 'div' );
	container.innerHTML = html;

	return ( container.textContent || '' ).replace( /\s+/g, ' ' ).trim();
};

/**
 * Returns the string value of an attribute, reading rich text values as HTML.
 *
 * @param {*} value Attribute value.
 * @return {string|null} String value, or null when the value is not textual.
 */
const getStringValue = ( value ) => {
	if ( 'string' === typeof value ) {
		return value;
	}

	if (
		value &&
		'object' === typeof value &&
		'string' === typeof value.originalHTML
	) {
		return value.originalHTML;
	}

	return null;
};

/**
 * Finds the attribute paths of a block whose text matches the nodes selected by XPath rules.
 *
 * The block markup is evaluated against each XPath; every non-empty matched text
 * (element text or attribute value) is compared with the decoded text of each
 * string attribute. All matching attribute paths are returned, so repeated texts
 * in different attributes are each picked up.
 *
 * @param {string} html       Serialized block markup.
 * @param {Array}  xpaths     XPath selectors.
 * @param {Object} attributes Block attributes.
 * @param {Object} win        Window providing DOMParser, XPathResult and Node.
 * @return {Array} List of attribute paths, each an array of keys.
 */
export const getXpathAttributePaths = (
	html,
	xpaths,
	attributes,
	win = window
) => {
	const paths = [];

	if (
		! html ||
		'string' !== typeof html ||
		! Array.isArray( xpaths ) ||
		! attributes ||
		'object' !== typeof attributes
	) {
		return paths;
	}

	const doc = new win.DOMParser().parseFromString( html, 'text/html' );
	const matchedTexts = new Set();

	xpaths.forEach( ( xpath ) => {
		let result = null;

		try {
			result = doc.evaluate(
				xpath,
				doc,
				null,
				win.XPathResult.ORDERED_NODE_SNAPSHOT_TYPE,
				null
			);
		} catch ( error ) {
			return;
		}

		for ( let index = 0; index < result.snapshotLength; index++ ) {
			const node = result.snapshotItem( index );
			const rawText =
				win.Node.ATTRIBUTE_NODE === node.nodeType
					? node.value
					: node.textContent;
			const text = ( rawText || '' ).replace( /\s+/g, ' ' ).trim();

			if ( '' !== text ) {
				matchedTexts.add( text );
			}
		}
	} );

	if ( 0 === matchedTexts.size ) {
		return paths;
	}

	const walk = ( value, currentPath ) => {
		const stringValue = getStringValue( value );

		if ( null !== stringValue ) {
			if (
				'' !== stringValue.trim() &&
				matchedTexts.has( toPlainText( stringValue, doc ) )
			) {
				paths.push( currentPath );
			}
			return;
		}

		if ( ! value || 'object' !== typeof value ) {
			return;
		}

		Object.keys( value ).forEach( ( key ) => {
			if ( NON_TRANSLATABLE_KEYS.includes( key ) ) {
				return;
			}

			const childKey = Array.isArray( value ) ? Number( key ) : key;
			walk( value[ key ], [ ...currentPath, childKey ] );
		} );
	};

	walk( attributes, [] );

	return paths;
};
