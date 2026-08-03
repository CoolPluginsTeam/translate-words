import { __ } from "@wordpress/i18n";

const SettingModalHeader = ({ setSettingVisibility, hasProviders = true }) => {
    const title = hasProviders
        ? __("Step 1 - Select Translation Provider", 'translate-words')
        : __("Translation Provider Not Configured", 'translate-words');

    return (
        <div className="modal-header">
            <h2>{title}</h2>
            <span className="close" onClick={() => setSettingVisibility(false)}>&times;</span>
        </div>
    );
}

export default SettingModalHeader;
