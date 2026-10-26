import React, { useState } from 'react'
import { __, _n, sprintf } from '@wordpress/i18n'

export const STATUS = {
    critical: { label: __('Critical', 'translate-words'), tone: 'crit', icon: 'dismiss' },
    warning: { label: __('Warning', 'translate-words'), tone: 'warn', icon: 'warning' },
    info: { label: __('Notice', 'translate-words'), tone: 'info', icon: 'info' },
    pass: { label: __('Passed', 'translate-words'), tone: 'pass', icon: 'yes-alt' },
}
export const Icon = ({ name, className = '' }) => <span aria-hidden='true' className={`dashicons dashicons-${name} ${className}`} />
export const LanguageFlag = ({ language }) => language?.flag_url ? <img className='lmat-scan-lang-flag' src={language.flag_url} alt='' width={18} height={13} /> : null
export const languageForRow = (row, languages) => languages.find((language) =>
    row.language === language.slug || row.label === `${language.name} (${language.slug})` ||
    row.ignore_key === `lang_homepage:${language.slug}` || row.ignore_key?.startsWith(`string:${language.slug}:`)
)
export const LanguageChip = ({ language, href, showName = false }) => {
    if (!language) return null
    const content = <><span className='lmat-scan-lang-flag-wrap'><LanguageFlag language={language} /></span><span className='lmat-scan-content-lang-chip-code'>{showName ? language.name : language.slug}</span></>
    return href ? <a className='lmat-scan-content-lang-chip lmat-scan-content-lang-chip-link' href={href} title={language.name} target='_blank' rel='noopener noreferrer'>{content}</a> : <span className='lmat-scan-content-lang-chip' title={language.name}>{content}</span>
}

// The toolkit shows one card per issue type, with languages collected in rows.
export const groupResults = (results) => {
    const groups = new Map()
    for (const result of results) {
        const family = result.id === 'term_no_language' && result.status !== 'pass' ? 'content_no_language' : result.id.startsWith('content_identical_') ? 'content_identical' : result.id.startsWith('content_link_') ? 'content_links' : result.id.startsWith('content_placeholder_') ? 'content_placeholder' : result.id.startsWith('content_acf_') ? 'content_acf' : result.id.startsWith('term_missing_') ? 'term_missing' : result.id.startsWith('url_config_') && result.status !== 'pass' ? 'url_configuration' : result.id.startsWith('nav_') ? 'navigation' : result.id.startsWith('url_route_') && result.status !== 'pass' && result.id !== 'url_route_untestable' && result.id !== 'url_route_site_gated' ? 'routing_health' : result.id === 'translated_permalink_review' ? 'translated_permalinks' : result.status === 'pass' ? result.id : result.id.startsWith('string_') ? 'strings_missing' : result.id.startsWith('menu_') && !['menu_none', 'menu_dead_links'].includes(result.id) ? 'menu_assignments' : result.id.startsWith('content_unpublished_') ? 'content_unpublished' : result.section === 'content' && !['content_no_language', 'content_source_updated'].includes(result.id) ? 'content_missing' : result.id
        const key = `${result.section}:${result.status}:${family}`
        if (!groups.has(key)) groups.set(key, { ...result, id: family, members: [], details: [] })
        const group = groups.get(key)
        group.members.push(result)
        group.details.push(...(result.details || []).map(row => ({ ...row, source_id: result.id, source_label: result.label })))
        group.details_truncated ||= result.details_truncated
        if (family === 'strings_missing') {
            group.label = result.status === 'pass' ? __('Site strings translated', 'translate-words') : __('Text String Translation', 'translate-words')
            group.message = result.status === 'pass' ? __('All registered strings are translated.', 'translate-words') : __('Some registered strings are not translated. Review the affected strings and languages below.', 'translate-words')
        } else if (family === 'menu_assignments') {
            group.label = result.status === 'pass' ? __('Menu locations assigned', 'translate-words') : __('Navigation Menus', 'translate-words')
            group.message = result.status === 'pass' ? __('A menu is assigned in every language.', 'translate-words') : __('Some menu locations do not have a menu assigned for every language.', 'translate-words')
        } else if (family === 'content_missing' && result.status !== 'pass') {
            group.label = __('Missing Translations', 'translate-words')
            group.message = __('Some posts or pages are not yet translated. Review the table below to complete coverage.', 'translate-words')
        } else if (family === 'content_identical') {
            group.label = result.status === 'pass' ? __('Translated content differs', 'translate-words') : __('Identical Content', 'translate-words')
            group.message = result.status === 'pass' ? __('Linked translations use different title and body text.', 'translate-words') : __('Some translated pages still contain the exact same title or body text as the original language.', 'translate-words')
        } else if (family === 'content_no_language' && result.status !== 'pass') {
            group.label = __('Content without a language', 'translate-words')
            group.message = __('Some content has no language and will not show on translated pages. Assign the default language to it.', 'translate-words')
        } else if (family === 'routing_health') {
            group.label = __('Language URLs', 'translate-words')
            group.message = __('Some language URLs did not load as expected. Open each one to confirm.', 'translate-words')
        } else if (family === 'translated_permalinks') {
            group.label = __('Translated URL Slugs', 'translate-words')
        } else if (family === 'navigation' && result.status !== 'pass') {
            group.label = __('Navigation', 'translate-words')
        } else if (family === 'url_configuration') {
            group.label = __('URL Settings', 'translate-words')
            group.message = __('Some URL settings may make language URLs fail or look wrong.', 'translate-words')
        } else if (family === 'term_missing') {
            group.label = result.status === 'pass' ? __('Terms translated', 'translate-words') : __('Categories & Tags', 'translate-words')
            group.message = result.status === 'pass' ? __('All categories and tags have a translation.', 'translate-words') : __('Some categories or tags are not yet translated. Review the table below to complete coverage.', 'translate-words')
        } else if (family === 'content_acf') {
            group.label = result.status === 'pass' ? __('ACF fields translated', 'translate-words') : __('ACF & Custom Fields', 'translate-words')
            group.message = result.status === 'pass' ? __('Linked translations use translated custom fields.', 'translate-words') : __('Some translated custom fields still contain the exact same text as the original language.', 'translate-words')
        } else if (family === 'content_placeholder') {
            group.label = result.status === 'pass' ? __('Shortcodes and placeholders match', 'translate-words') : __('Shortcode & Placeholder Mismatch', 'translate-words')
            group.message = result.status === 'pass' ? __('Shortcodes and placeholders match in every compared translation.', 'translate-words') : __('Some translated pages have shortcodes or placeholders that differ from the original.', 'translate-words')
        } else if (family === 'content_links') {
            group.label = result.status === 'pass' ? __('Content links work', 'translate-words') : __('Content Links', 'translate-words')
            group.message = result.status === 'pass' ? __('No link in the content points to a missing page or the wrong language.', 'translate-words') : __('Some links in your content point to a missing page or to the wrong language.', 'translate-words')
        } else if (family === 'content_source_updated' && result.status !== 'pass') {
            group.label = __('Default Language Updated', 'translate-words')
            group.message = __('The default language was updated. Linked translations may need a matching update.', 'translate-words')
        } else if (family === 'content_unpublished' && result.status !== 'pass') {
            group.label = __('Unpublished Translations', 'translate-words')
            group.message = __('Some translations exist but are not published yet, so visitors cannot see them. Review the table below and publish them.', 'translate-words')
        } else if (result.id === 'lang_switcher' && result.status !== 'pass') group.label = __('Language Switcher', 'translate-words')
    }
    return [...groups.values()].map(group => ({ ...group, details: [...new Map(group.details.map((row, index) => [row.ignore_key || `${row.label}:${row.message}:${index}`, row])).values()] }))
}

export const Pill = ({ active, onClick, children, count }) => <button type='button' aria-pressed={active} onClick={onClick} className={`lmat-scan-filter-pill${active ? ' is-active' : ''}`}>{children}{count !== undefined && <span className='lmat-scan-filter-count'>{count}</span>}</button>

export const rowIdOf = row => row.targets.map(target => target.ignore_key).filter(Boolean).join(',')

export const IssueCard = ({ rowStatus = {}, result, filters, busy, onIgnore, onIgnoreMany, onRecheck, onRecheckRow, onDiff, onAction, whyHow, languages = [], initialOpen = false }) => {
    const [open, setOpen] = useState(initialOpen || !!whyHow)
    const [page, setPage] = useState(0)
    const status = STATUS[result.status] || STATUS.info
    const rows = (result.details || []).filter(row => (!filters?.type || row.post_type === filters.type) && (!filters?.search || String(row.label).toLowerCase().includes(filters.search.toLowerCase())))
    const grouped = new Map()
    for (const row of rows) {
        const contentId = row.ignore_key?.match(/^(?:content(?:_updated)?|term):(\d+):/)
        const stringId = row.ignore_key?.match(/^string:[^:]+:(.+)$/)
        const templateId = row.ignore_key?.match(/^elementor:[^:]+:(\d+):/)
        const key = result.id === 'menu_assignments' ? row.source_id : templateId ? `elementor:${templateId[1]}` : contentId ? `${result.id}:${contentId[1]}` : stringId ? `string:${stringId[1]}` : row.ignore_key || `${row.label}:${row.message}`
        if (!grouped.has(key)) grouped.set(key, { ...row, label: result.id === 'menu_assignments' ? row.location_label || row.source_label : row.label, targets: [] })
        grouped.get(key).targets.push(row)
    }
    const items = [...grouped.values()]
    const currentPage = Math.min(page, Math.max(0, Math.ceil(items.length / 10) - 1))
    const pageRows = items.slice(currentPage * 10, currentPage * 10 + 10)
    const hasLanguageColumn = items.some(row => row.targets.some(target => languageForRow(target, languages)))
    const hasTypes = items.some(row => row.post_type)
    const isSwitcher = result.id === 'lang_switcher'
    const isList = ['content_identical', 'content_links', 'content_placeholder', 'content_acf'].includes(result.id)
    const isElementor = result.section === 'elementor'
    return <details open={open} onToggle={event => setOpen(event.currentTarget.open)} className={`lmat-scan-issue-card lmat-scan-issue-${status.tone}`}>
        <summary className='lmat-scan-issue-summary'>
            <Icon name={status.icon} className='lmat-scan-issue-icon' />
            <span className='lmat-scan-issue-main'><strong>{result.label}{result.status !== 'pass' && ` (${items.length || 1} ${(items.length || 1) === 1 ? __('Issue', 'translate-words') : __('Issues', 'translate-words')})`}</strong><span>{result.message}</span></span>
            <span className='lmat-scan-issue-meta'><span className={`lmat-scan-badge lmat-scan-badge-${status.tone}`}><Icon name='shield' />{status.label}</span><Icon name='arrow-down-alt2' className='lmat-scan-accordion-arrow' /></span>
        </summary>
        <div className='lmat-scan-issue-body'>
            {result.recently_fixed && <p className='lmat-inspector-fixed'>{__('Fixed since last scan', 'translate-words')}</p>}
            <div className={isSwitcher ? 'lmat-scan-card-actions' : 'lmat-inspector-actions'} data-track-id={result.members?.[0]?.id || result.id} data-track-section={result.section}>
                {result.action && <button className='button button-primary' disabled={busy} onClick={() => onAction(result.action)}>{result.action_label}</button>}
                {result.fix_url && !['content_missing', 'term_missing', 'url_configuration', 'navigation', 'lang_untranslated_types'].includes(result.id) && <a className={`button ${isSwitcher ? 'button-primary' : 'button-secondary'}`} href={result.fix_url} target='_blank' rel='noopener noreferrer'>{isSwitcher ? __('Set up Language Switcher', 'translate-words') : result.id === 'menu_assignments' ? __('Manage Menu Locations', 'translate-words') : result.id === 'strings_missing' ? __('Translate Strings', 'translate-words') : result.fix_label || __('Open settings', 'translate-words')}</a>}
                {!isSwitcher && result.status !== 'pass' && !rows.length && <button className='button button-small' disabled={busy} onClick={() => onRecheck(result)}><Icon name='update' />{__('Rescan issue', 'translate-words')}</button>}
                {!isSwitcher && result.status !== 'pass' && !rows.length && <button className='button button-small' disabled={busy} onClick={() => onIgnore(result.members?.[0]?.id || result.id)}>{__('Ignore', 'translate-words')}</button>}
            </div>
            {whyHow && <div className='lmat-inspector-guidance'>{result.why && <p><strong>{__('Why this matters', 'translate-words')}</strong><br />{result.why}</p>}{result.how && <p><strong>{__('How to fix', 'translate-words')}</strong><br />{result.how}</p>}</div>}
            {items.length > 0 && isList && <div className='lmat-scan-detail-list'>{pageRows.map((row, index) => <div key={row.ignore_key || index} className='lmat-scan-detail-row' data-track-id={row.source_id || result.members?.[0]?.id || result.id} data-track-section={result.section} data-track-keys={JSON.stringify(row.targets.map(target => target.ignore_key).filter(Boolean))}>
                <div className='lmat-scan-detail-title'><strong>{row.label}</strong>{row.context && <p>{__('Found in:', 'translate-words')} <em>"{row.context}"</em></p>}</div>
                <div className='lmat-scan-detail-message'>{row.message}</div>
                <div className='lmat-scan-content-actions'>
                    {row.action_url && <a className='button button-small' href={row.action_url} target='_blank' rel='noopener noreferrer'>{row.action_label || __('Edit', 'translate-words')}</a>}
                    {row.page_fix_url && <a className='button button-small' href={row.page_fix_url} target='_blank' rel='noopener noreferrer'>{row.page_fix_label || __('Fix page', 'translate-words')}</a>}
                    {row.ai_url && <a className='button button-small button-primary' href={row.ai_url} target='_blank' rel='noopener noreferrer'>{__('AI Translation', 'translate-words')}</a>}
                    {row.ignore_key && <button className={`button button-small${rowStatus[rowIdOf(row)]?.state === 'checking' ? ' is-loading' : ''}`} disabled={busy || rowStatus[rowIdOf(row)]?.state === 'checking'} onClick={() => onRecheckRow(result, row)}><Icon name='update' />{__('Rescan issue', 'translate-words')}</button>}
                    {row.ignore_key && <button className='button button-small' disabled={busy} onClick={() => onIgnore(row.ignore_key)}>{__('Ignore', 'translate-words')}</button>}
                    {rowStatus[rowIdOf(row)] && <span className={`lmat-scan-issue-check-status is-${rowStatus[rowIdOf(row)].state}`} role='status' aria-live='polite'><Icon name={rowStatus[rowIdOf(row)].state === 'checking' ? 'update' : rowStatus[rowIdOf(row)].state === 'resolved' ? 'yes-alt' : 'warning'} /> {rowStatus[rowIdOf(row)].message}</span>}
                </div>
            </div>)}</div>}
            {items.length > 0 && !isList && <div className='lmat-scan-table-wrap'><table className='widefat striped lmat-scan-content-pending-table'>
                <thead><tr><th scope='col'>{result.id === 'menu_assignments' ? __('Theme location', 'translate-words') : result.id === 'strings_missing' ? __('String', 'translate-words') : __('Title', 'translate-words')}</th>{hasTypes && <th scope='col'>{__('Type', 'translate-words')}</th>}{hasLanguageColumn && <th scope='col'>{result.id === 'content_source_updated' ? __('Outdated languages', 'translate-words') : (isElementor && result.id !== 'elementor_missing' || ['content_identical', 'content_links', 'navigation'].includes(result.id)) ? __('Language', 'translate-words') : result.id === 'content_unpublished' ? __('Unpublished languages', 'translate-words') : __('Missing languages', 'translate-words')}</th>}<th scope='col'>{__('Actions', 'translate-words')}</th></tr></thead>
                <tbody>{pageRows.map((row, index) => <tr key={row.ignore_key || index} data-track-id={row.source_id || result.members?.[0]?.id || result.id} data-track-section={result.section} data-track-keys={JSON.stringify(row.targets.map(target => target.ignore_key).filter(Boolean))}>
                    <td><strong>{row.label}</strong>{row.posts && <ul className='lmat-scan-post-list'>{row.posts.map((post, postIndex) => <li key={postIndex}><a href={post.action_url} target='_blank' rel='noopener noreferrer'>{post.title}</a> <span>({post.language})</span></li>)}</ul>}{(row.context && result.id === 'content_links') && <p>{__('Found in:', 'translate-words')} <em>"{row.context}"</em></p>}{(!hasLanguageColumn || isElementor || ['content_identical', 'content_links'].includes(result.id)) && <p>{result.id === 'elementor_missing' ? sprintf(_n('%d missing translation.', '%d missing translations.', row.targets.length, 'translate-words'), row.targets.length) : row.message}</p>}</td>
                    {hasTypes && <td>{row.post_type === 'post' ? __('Post', 'translate-words') : row.post_type === 'page' ? __('Page', 'translate-words') : row.post_type === 'elementor_library' ? __('Template', 'translate-words') : row.post_type === 'elementor_component' ? __('Component', 'translate-words') : row.post_type}</td>}
                    {hasLanguageColumn && <td><div className='lmat-scan-content-lang-chips'>{row.targets.map((target, targetIndex) => <LanguageChip key={target.ignore_key || targetIndex} language={languageForRow(target, languages)} href={target.action_url} showName={result.id === 'strings_missing'} />)}</div></td>}
                    <td><div className='lmat-scan-content-actions'>{(row.edit_url || result.id === 'content_source_updated' && row.action_url) && <a className='button button-small' href={row.edit_url || row.action_url} target='_blank' rel='noopener noreferrer'>{isElementor ? __('Edit in Elementor', 'translate-words') : __('Edit', 'translate-words')}</a>}{['content_missing', 'term_missing'].includes(result.id) && result.fix_url && <a className='button button-small button-primary' href={result.fix_url} target='_blank' rel='noopener noreferrer'>{__('AI Translation', 'translate-words')}</a>}{result.id === 'content_source_updated' && onDiff && row.targets.some(target => target.translation_id) && <button type='button' className='button button-small lmat-scan-view-diff-btn' disabled={busy} onClick={() => onDiff({ ...row.targets.find(target => target.translation_id), options: row.targets.filter(target => target.translation_id) })} title={__('See what changed in the default language since this translation was last up to date', 'translate-words')}><Icon name='visibility' />{__('See what changed', 'translate-words')}</button>}{(result.status !== 'pass' && !isSwitcher && row.targets.some(target => target.ignore_key)) && <button className={`button button-small${rowStatus[rowIdOf(row)]?.state === 'checking' ? ' is-loading' : ''}`} disabled={busy || rowStatus[rowIdOf(row)]?.state === 'checking'} onClick={() => onRecheckRow(result, row)}><Icon name='update' />{__('Rescan issue', 'translate-words')}</button>}{rowStatus[rowIdOf(row)] && <span className={`lmat-scan-issue-check-status is-${rowStatus[rowIdOf(row)].state}`} role='status' aria-live='polite'><Icon name={rowStatus[rowIdOf(row)].state === 'checking' ? 'update' : rowStatus[rowIdOf(row)].state === 'resolved' ? 'yes-alt' : 'warning'} /> {rowStatus[rowIdOf(row)].message}</span>}{!isElementor && !['menu_assignments', 'strings_missing', 'content_missing', 'content_source_updated', 'term_missing'].includes(result.id) && [...new Map(row.targets.filter(target => target.action_url).map(target => [target.action_url, target])).values()].map(target => <a key={target.action_url} className='button button-small' href={target.action_url}>{target.action_label || __('Open', 'translate-words')}</a>)}{row.targets.some(target => target.ignore_key) && <button className='button button-small' disabled={busy} onClick={() => row.targets.length > 1 ? onIgnoreMany(row.targets.map(target => target.ignore_key).filter(Boolean)) : onIgnore(row.ignore_key)} aria-label={sprintf(__('Ignore %s', 'translate-words'), row.label)}>{__('Ignore', 'translate-words')}</button>}</div></td>
                </tr>)}</tbody>
            </table></div>}
            {items.length > 10 && <div className='lmat-inspector-pagination'><span>{sprintf(__('Showing %1$d – %2$d of %3$d items', 'translate-words'), currentPage * 10 + 1, Math.min((currentPage + 1) * 10, items.length), items.length)}</span><button className='button button-small' disabled={currentPage === 0} onClick={() => setPage(currentPage - 1)}>{__('Previous', 'translate-words')}</button>{Array.from({ length: Math.ceil(items.length / 10) }, (_, number) => number).filter(number => number === 0 || number === Math.ceil(items.length / 10) - 1 || Math.abs(number - currentPage) <= 1).map((number, position, list) => <React.Fragment key={number}>{position > 0 && number - list[position - 1] > 1 && <span>…</span>}<button className={`button button-small${number === currentPage ? ' button-primary' : ''}`} onClick={() => setPage(number)}>{number + 1}</button></React.Fragment>)}<span>{sprintf(__('Page %1$d of %2$d', 'translate-words'), currentPage + 1, Math.ceil(items.length / 10))}</span><button className='button button-small' disabled={(currentPage + 1) * 10 >= items.length} onClick={() => setPage(currentPage + 1)}>{__('Next', 'translate-words')}</button></div>}
            {result.details_truncated && <p>{__('Only the first items are listed. Fix these and scan again to see the rest.', 'translate-words')}</p>}
        </div>
    </details>
}

export const IgnoredPanel = ({ items, onRestore, busy, expanded = false, languages = [] }) => !items?.length ? null : (
    <details className='lmat-scan-passed-panel lmat-scan-ignored-panel' open={expanded}>
        <summary className='lmat-scan-passed-summary'>
            <Icon name='hidden' className='lmat-scan-passed-icon' />
            <span className='lmat-scan-passed-main'><strong>{sprintf(__('Ignored Issues (%d)', 'translate-words'), items.length)}</strong><span>{__('Warnings you dismissed. Restore one to show it again after the next scan refresh.', 'translate-words')}</span></span>
            <span className='lmat-scan-issue-meta'><span className='lmat-scan-badge lmat-scan-badge-ignored'><Icon name='hidden' />{__('Ignored', 'translate-words')}</span><Icon name='arrow-down-alt2' className='lmat-scan-accordion-arrow' /></span>
        </summary>
        <div className='lmat-scan-passed-body'><div className='lmat-scan-table-wrap'><table className='widefat striped lmat-scan-ignored-table'>
            <thead><tr>{[__('Title', 'translate-words'), __('Issue', 'translate-words'), __('Type', 'translate-words'), __('Language', 'translate-words'), __('Actions', 'translate-words')].map(label => <th scope='col' key={label}>{label}</th>)}</tr></thead>
            <tbody>{items.map(item => <tr key={item.key}><td><strong>{item.label}</strong></td><td>{item.issue}</td><td>{item.post_type || (item.section === 'site' ? __('Site setup', 'translate-words') : item.section === 'urls' ? __('URLs & Routing', 'translate-words') : __('Content', 'translate-words'))}</td><td><LanguageChip language={languageForRow({ ...item, ignore_key: item.key }, languages)} /></td><td><button className='button button-small' disabled={busy} onClick={() => onRestore(item.key)}>{__('Restore', 'translate-words')}</button></td></tr>)}</tbody>
        </table></div></div>
    </details>
)

export const PassedChecks = ({ results, expanded = false }) => {
    const [search, setSearch] = useState('')
    if (!results.length) return null
    const shown = results.filter(result => `${result.label} ${result.message}`.toLowerCase().includes(search.toLowerCase()))
    return <details className='lmat-scan-passed-panel' open={expanded}><summary className='lmat-scan-passed-summary'><Icon name='yes-alt' className='lmat-scan-passed-icon' /><span className='lmat-scan-passed-main'><strong>{sprintf(__('Passed Checks (%d)', 'translate-words'), results.length)}</strong><span>{__('Checks in this area that passed. Collapsed by default.', 'translate-words')}</span></span><span className='lmat-scan-issue-meta'><span className='lmat-scan-badge lmat-scan-badge-pass'><Icon name='yes' />{__('Passed', 'translate-words')}</span><Icon name='arrow-down-alt2' className='lmat-scan-accordion-arrow' /></span></summary><div className='lmat-scan-passed-body'><div className='lmat-scan-passed-tools'><label className='lmat-scan-passed-search'><Icon name='search' /><input type='search' value={search} onChange={event => setSearch(event.target.value)} aria-label={__('Search passed checks', 'translate-words')} placeholder={__('Search passed checks...', 'translate-words')} /></label></div><div className='lmat-scan-passed-list'>{shown.map(result => <article className='lmat-scan-pass-card' key={`${result.section}:${result.id}`}><span className='lmat-scan-group-tag'>{result.group}</span><strong>{result.label}</strong><p>{result.message}</p></article>)}</div>{!shown.length && <p role='status'>{__('No checks match this search.', 'translate-words')}</p>}</div></details>
}
