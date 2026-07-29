import { __ } from "@wordpress/i18n";

const SettingModalHeader = ({ setSettingVisibility, prefix, hasProviders }) => {
    const title = hasProviders
        ? __("Step 2 - Select Translation Provider", 'translate-words')
        : __("Translation Provider Not Configured", 'translate-words');
    return (
        <div className={`${prefix}-setting-modal-header`}>
            <h2>{title}</h2>
            <span className={`${prefix}-setting-modal-close`} onClick={(e) => setSettingVisibility(e)}>&times;</span>
        </div>
    );
}

export default SettingModalHeader;
