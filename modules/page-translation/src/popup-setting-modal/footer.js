import { __, sprintf } from "@wordpress/i18n";

const SettingModalFooter = (props) => {

    const { targetLangName, postType, sourceLangName, setSettingVisibility } = props;

    return (
        <div className="modal-footer">
            <p className="lmat-page-translation-error-message" style={{ marginBottom: '.5rem' }}>
                {sprintf(
                    __("This will replace your current %(postType)s with a %(target)s translation of the original %(source)s content.", 'translate-words'),
                    { postType: postType, source: sourceLangName, target: targetLangName }
                )}
            </p>
            <button className="lmat-page-translation-setting-close button button-primary" onClick={() => setSettingVisibility(false)}>{__("Close", 'translate-words')}</button>
        </div>
    );
}

export default SettingModalFooter;
