/**
 * Turn literal \n, \r, \t (backslash + letter) into real control characters after DOM / API reads.
 * Matches server-side `Linguator\Modules\REST\V1\Bulk_Translation::ai_normalize_translation_string()`.
 * Repeats until stable so doubled escapes from the model (e.g. "\\n") collapse correctly.
 *
 * @param {string} value
 * @returns {string}
 */
export default function normalizeBulkTranslationEscapes(value) {
	if (typeof value !== "string" || value === "") {
		return value;
	}
	let out = value;
	for (let i = 0; i < 24; i++) {
		const next = out.replace(/\\n/g, "\n").replace(/\\r/g, "\r").replace(/\\t/g, "\t");
		if (next === out) {
			break;
		}
		out = next;
	}
	return out;
}
