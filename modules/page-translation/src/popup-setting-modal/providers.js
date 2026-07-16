import TranslateService from "../component/translate-provider/index.js";
import ChromeLocalAiTranslator from "../component/translate-provider/local-ai-translator/local-ai-translator.js";
import { __ } from "@wordpress/i18n";
import { useEffect } from "@wordpress/element";

const Providers = (props) => {
    const service = props.Service;
    const buttonDisable = props[service + "Disabled"];
    const ActiveService = TranslateService({ Service: service, [service + "ButtonDisabled"]: buttonDisable, openErrorModalHandler: props.openErrorModalHandler });

    if (!ActiveService) {
        return null;
    }

    const serviceId = service.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/[^a-z0-9-]/g, '');
    const btnId = `lmat-page-translation-${serviceId}-btn`;

    const downloadState = service === 'localAiTranslator' ? props.chromeAiDownload : (service === 'edgeLocalAiTranslator' ? props.edgeAiDownload : null);
    const setDownloadState = service === 'localAiTranslator' ? props.setChromeAiDownload : (service === 'edgeLocalAiTranslator' ? props.setEdgeAiDownload : null);

    const handleDownload = async (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        if (!downloadState || downloadState.status === 'downloading') return;

        setDownloadState(prev => ({ ...prev, status: 'downloading', progress: 0 }));

        try {
            const result = await ChromeLocalAiTranslator.startLanguagePackDownload(
                downloadState.source,
                downloadState.target,
                (evt) => {
                    const pct = Math.round((evt && evt.loaded ? evt.loaded : 0) * 100);
                    setDownloadState(prev => prev ? ({ ...prev, progress: pct }) : null);
                }
            );

            if (result && result.ok) {
                // Download successful - reset download state so button shows "Translate"
                setDownloadState(null);
            } else {
                setDownloadState(prev => prev ? ({ ...prev, status: 'failed' }) : null);
            }
        } catch (err) {
            console.error("Language pack download failed:", err);
            setDownloadState(prev => prev ? ({ ...prev, status: 'failed' }) : null);
        }
    };

    useEffect(() => {
        if (downloadState && downloadState.status === 'downloadable') {
            handleDownload();
        }
    }, [downloadState]);

    const renderDownloadButton = () => {
        if (downloadState.status === 'downloading') {
            return (
                <div
                    className="lmat-page-translation-service-btn button button-primary disabled lmat-page-translation-btn-downloading"
                    style={{ pointerEvents: 'none', opacity: 0.85, display: 'inline-flex', alignItems: 'center', gap: '8px' }}
                >
                    <span className="lmat-loading-spinner-mini" style={{
                        width: '12px',
                        height: '12px',
                        border: '2px solid rgba(255,255,255,0.3)',
                        borderTop: '2px solid #fff',
                        borderRadius: '50%',
                        display: 'inline-block'
                    }}></span>
                    {__('Downloading…', 'translate-words')} {downloadState.progress}%
                </div>
            );
        }

        const btnText = downloadState.status === 'failed' ? __('Retry Download', 'translate-words') : `${__('Download', 'translate-words')} ${props.targetLangName}`;

        return (
            <div
                onClick={handleDownload}
                className="lmat-page-translation-service-btn button button-primary lmat-page-translation-btn-downloadable"
                style={{ cursor: 'pointer' }}
            >
                {btnText}
            </div>
        );
    };

    return (
        <tr>
            <td className="lmat-page-translation-provider-name">
                {ActiveService.Logo}
                <span>{ActiveService.title}</span>
            </td>
            <td>
                {downloadState ? renderDownloadButton() : (
                    ActiveService.ButtonDisabled ? (
                        <div
                            onClick={() => props.openErrorModalHandler(service)}
                            style={{ cursor: 'pointer' }}
                        >
                            {ActiveService.ErrorMessage}
                        </div>
                    ) : (
                        <div
                            id={btnId}
                            onClick={props.fetchContent}
                            className="lmat-page-translation-service-btn button button-primary"
                            data-service={service}
                            data-service-label={ActiveService.serviceLabel}
                        >
                            {ActiveService.SettingBtnText}
                        </div>
                    )
                )}
            </td>
            <td>
                <a href={ActiveService.Docs} target="_blank" rel="noopener noreferrer" className="lmat-page-translation-doc-icon">
                    <svg width="9" height="12" viewBox="0 0 9 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.17607 6.20533H6.82393V5.53867H2.17607V6.20533ZM2.17607 8.05133H6.82393V7.38467H2.17607V8.05133ZM2.17607 9.898H4.89536V9.23133H2.17607V9.898ZM1.03821 12C0.7425 12 0.495643 11.8973 0.297643 11.692C0.0996427 11.4867 0.000428571 11.2304 0 10.9233V1.07667C0 0.77 0.0992142 0.514 0.297643 0.308667C0.496071 0.103333 0.743143 0.000444444 1.03886 0H6.10714L9 3V10.9233C9 11.23 8.901 11.4862 8.703 11.692C8.505 11.8978 8.25771 12.0004 7.96114 12H1.03821ZM5.78571 3.33333V0.666667H1.03886C0.939857 0.666667 0.849 0.709333 0.766286 0.794666C0.683571 0.88 0.642429 0.974 0.642857 1.07667V10.9233C0.642857 11.0256 0.684 11.1196 0.766286 11.2053C0.848571 11.2911 0.939214 11.3338 1.03821 11.3333H7.96179C8.06036 11.3333 8.151 11.2907 8.23371 11.2053C8.31643 11.12 8.35757 11.0258 8.35714 10.9227V3.33333H5.78571Z" fill="#6F6F6F" />
                    </svg>
                </a>
            </td>
        </tr>
    );
}

export default Providers;
