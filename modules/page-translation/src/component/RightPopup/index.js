import React from "react";

const InfoPopup = React.forwardRef(
  (
    {
      left,
      top,
      onClose,
      onSave,
      onCopy,
    },
    ref
  ) => {
    const imgFolder = lmatPageTranslationGlobal.lmat_url + 'assets/images/';

    React.useEffect(() => {
      const handleTranslateClick = () => onClose();

      const elements = [
        document.getElementById('lmat-page-translation-ai-translate-btn'),
        document.getElementsByClassName('local_ai_translator_btn')[0],
        document.querySelector('.goog-te-combo'),
        document.getElementById('yt-widget')
      ].filter(Boolean);

      elements.forEach(el => {
        const eventType = el.tagName === 'SELECT' ? 'change' : 'click';
        el.addEventListener(eventType, handleTranslateClick);
      });
      return () => {
        elements.forEach(el => {
          const eventType = el.tagName === 'SELECT' ? 'change' : 'click';
          el.removeEventListener(eventType, handleTranslateClick);
        });
      };
    }, [onClose]);

    return (
      <div
        ref={ref}
        className="lmat-page-translation-info-popup"
        style={{ left, top }}
        onClick={e => e.stopPropagation()}
      >
        <button
          className="lmat-page-translation-info-popup__close"
          onClick={onClose}
          aria-label="Close"
          title="Close"
        >
          <img src={imgFolder + 'cross.png'} alt="Close" />
        </button>
        <button
          className="lmat-page-translation-info-popup__copy"
          onClick={onCopy}
          title="Copy"
        >
          <img src={imgFolder + 'copy.png'} alt="Copy" className="lmat-page-translation-copy-icon" />
        </button>
        <button
          className="lmat-page-translation-info-popup__save"
          onClick={onSave}
          aria-label="Save"
          title="Save"
        >
          <img src={imgFolder + 'save.png'} alt="Save" className="lmat-page-translation-save-icon" />
        </button>
      </div>
    );
  }
);

export {
  InfoPopup
};