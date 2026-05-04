import React, { useMemo } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Chrome Translator API compatibility notice (shared by settings + setup wizard).
 *
 * @param {Object} [props]
 * @param {string} [props.className] Extra classes on the outer card.
 * @param {Object} [props.style]     Extra inline styles (merged after base card styles).
 */
export function ChromeLocalAINotice({ className = '', style: extraStyle = {} }) {
	const noticeType = useMemo(() => {
		const isHttps = window?.location?.protocol === 'https:';
		const isSecureContext = Boolean(window?.isSecureContext);

		const apiAvailable =
			('translation' in window?.self && 'createTranslator' in window?.self?.translation) ||
			('ai' in window?.self && 'translator' in window?.self?.ai) ||
			('Translator' in window?.self && 'create' in window?.self?.Translator);

		const hasChromeObject = Object.prototype.hasOwnProperty.call(window ?? {}, 'chrome');
		const userAgent = navigator?.userAgent ?? '';
		const isChrome = hasChromeObject && userAgent.includes('Chrome') && !userAgent.includes('Edg');

		if (!isChrome) {
			return 'browser';
		}

		if (!apiAvailable && !isHttps && !isSecureContext) {
			return 'secure';
		}

		if (!apiAvailable) {
			return 'api';
		}

		return null;
	}, []);

	if (!noticeType) {
		return null;
	}

	const outerClass = ['flex', 'flex-col', 'gap-4', 'p-6', 'rounded-lg', className].filter(Boolean).join(' ');

	return (
		<div
			className={outerClass}
			style={{ border: '1px solid #e5e7eb', background: '#fff5f5', ...extraStyle }}
		>
			<div className="text-red-600 text-sm leading-6">
				<h3 className="font-semibold">
					{noticeType === 'browser' && __('Important Notice: Browser Compatibility', 'translate-words')}
					{noticeType === 'secure' && __('Important Notice: Secure Connection Required', 'translate-words')}
					{noticeType === 'api' && __('Important Notice: API Availability', 'translate-words')}
				</h3>

				{noticeType === 'browser' && (
					<ul className="list-disc ml-5 mt-2">
						<li>
							{__('The Translator API (Chrome Local AI Models) is designed for the Chrome browser.', 'translate-words')}
						</li>
						<li>
							{__(
								'If you are using a different browser (such as Edge, Firefox, or Safari), the API may not function correctly.',
								'translate-words'
							)}
						</li>
						<li>
							<a
								className="underline text-blue-600"
								href="https://developer.chrome.com/docs/ai/translator-api"
								rel="noreferrer noopener"
								target="_blank"
							>
								{__('Learn more in the official documentation.', 'translate-words')}
							</a>
						</li>
					</ul>
				)}

				{noticeType === 'secure' && (
					<>
						<ul className="list-disc ml-5 mt-2">
							<li>
								{__('The Translator API requires a secure (HTTPS) connection to function properly.', 'translate-words')}
							</li>
							<li>{__('If you are on an insecure connection (HTTP), the API will not work.', 'translate-words')}</li>
						</ul>

						<p className="mt-2">{__('How to fix this:', 'translate-words')}</p>
						<ol className="list-decimal ml-5 mt-2">
							<li>
								{__('Use a secure connection (https://).', 'translate-words')}
							</li>
							<li>
								{__('Or add your site to Chrome’s “insecure origins treated as secure”.', 'translate-words')}{' '}
								<code>chrome://flags/#unsafely-treat-insecure-origin-as-secure</code>
							</li>
						</ol>
					</>
				)}

				{noticeType === 'api' && (
					<>
						<ol className="list-decimal ml-5 mt-2">
							<li>
								{__('Open this URL in a new Chrome tab:', 'translate-words')}{' '}
								<code>chrome://flags/#translation-api</code>
							</li>
							<li>
								{__('Set “Experimental translation API” to Enabled.', 'translate-words')}
							</li>
							<li>
								{__('Click Relaunch to apply changes.', 'translate-words')}
							</li>
							<li>
								{__('The Translator AI option should now be enabled.', 'translate-words')}
							</li>
						</ol>
						<p className="mt-2">
							<a
								className="underline text-blue-600"
								href="https://developer.chrome.com/docs/ai/translator-api"
								rel="noreferrer noopener"
								target="_blank"
							>
								{__('Documentation', 'translate-words')}
							</a>
						</p>
						<p>
							{__('If the issue persists, please ensure Chrome is up to date and restart the browser.', 'translate-words')}
						</p>
						<p>
							<a
								className="underline text-blue-600"
								href="https://my.coolplugins.net/account/support-tickets/"
								rel="noreferrer noopener"
								target="_blank"
							>
								{__('Open a support ticket', 'translate-words')}
							</a>
						</p>
					</>
				)}
			</div>
		</div>
	);
}
