import { useState } from 'react'
import { Container, Button } from "@bsf/force-ui"
import { __ } from '@wordpress/i18n';
import apiFetch from "@wordpress/api-fetch"
import { getNonce } from '../utils'

const Sidebar = () => {
  // LocoAI card state from localized script data: hidden | install | activate | activated
  const [locoaiState, setLocoaiState] = useState(window.lmat_settings?.locoai_plugin_status?.state || 'hidden');
  const [locoaiBusy, setLocoaiBusy] = useState(false);
  const [locoaiError, setLocoaiError] = useState('');

  const handleLocoai = async () => {
    setLocoaiBusy(true);
    setLocoaiError('');
    try {
      const response = await apiFetch({
        path: 'lmat/v1/settings/locoai',
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': getNonce()
        }
      });
      if (response?.state === 'activated') {
        // Reload so the LocoAI menu shows up in the admin sidebar right away.
        setLocoaiState('activated');
        window.location.reload();
        return;
      }
      if (response?.state === 'activate') {
        setLocoaiState(response.state);
      } else {
        setLocoaiError(__('LocoAI could not be activated. Please try again.', 'translate-words'));
      }
    } catch (error) {
      setLocoaiError(error?.message || __('Something went wrong. Please try again.', 'translate-words'));
    } finally {
      setLocoaiBusy(false);
    }
  };

  const locoaiButtonLabel = locoaiState === 'activate'
    ? (locoaiBusy ? __('Activating...', 'translate-words') : __('Activate', 'translate-words'))
    : (locoaiBusy ? __('Installing...', 'translate-words') : __('Install', 'translate-words'));

  return (
    <>
      <div className='w-full'>
        <div className='w-full flex flex-col gap-8 rounded-lg'>
          <Container className='flex flex-col p-6  bg-white border border-gray-200 rounded-lg shadow-sm'>
            <Container.Item>
              <h2 className='text-lg font-semibold text-gray-900 mb-2'>{__('Auto Translation Status', 'translate-words')}</h2>
              <Container.Item className=''>
                <h1 className='text-3xl font-bold text-gray-900 m-0'>{window.lmat_settings?.translations_data?.total_character || 0}</h1>
                <p className='text-sm text-gray-600 m-0'>{__('Total Characters Translated!', 'translate-words')}</p>
              </Container.Item>
            </Container.Item>
            <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle my-1" />
            <Container.Item className='w-full'>
              <Container.Item className='flex flex-col gap-1'>
                <div className='flex justify-between items-center'>
                  <h4 className='text-sm text-gray-700 m-0'>{__('Total Strings', 'translate-words')}</h4>
                  <p className='text-sm font-medium text-gray-900 m-0'>{window.lmat_settings?.translations_data?.total_string || 0}</p>
                </div>
                <div className='flex justify-between items-center'>
                  <h4 className='text-sm text-gray-700 m-0'>{__('Total Pages / Posts', 'translate-words')}</h4>
                  <p className='text-sm font-medium text-gray-900 m-0'>{window.lmat_settings?.translations_data?.total_pages || 0}</p>
                </div>
                <div className='flex justify-between items-center'>
                  <h4 className='text-sm text-gray-700 m-0'>{__('Time Taken', 'translate-words')}</h4>
                  <p className='text-sm font-medium text-gray-900 m-0'>{window.lmat_settings?.translations_data?.total_time || 0}</p>
                </div>
                <div className='flex flex-col gap-2'>
                  <div className='flex flex-col gap-1'>
                    <h4 className='text-sm text-gray-700 m-0 text-nowrap'>{__('Service Providers', 'translate-words')}</h4>
                  </div>
                  <div className='flex flex-wrap gap-2'>
                  {window.lmat_settings?.translations_data?.service_providers?.map((provider, index) => (
                    <span className='text-sm font-medium text-gray-900 m-0 bg-gray-200 px-2 py-1 rounded-md' key={index}>{provider}</span>
                  ))}
                  </div>
                </div>
              </Container.Item>
            </Container.Item>
          </Container>
          {
            locoaiState !== 'hidden' ? (

              <div className=' p-6 bg-white border border-gray-200 rounded-lg shadow-sm'>
                <h2>{__('Automatically Translate Plugins & Themes', 'translate-words')}</h2>
                <hr className="w-full border-b-0 border-x-0 border-t border-solid border-t-border-subtle my-1" />
                <Container.Item className='flex'>
                  <div className='w-[70%]'>
                    <h4>{__('LocoAI - Auto Translation for Loco Translate', 'translate-words')}</h4>
                    {locoaiState === 'activated' ? (
                      <span className='inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-900 bg-gray-200 border border-solid border-gray-300 rounded-md cursor-default'>{__('Activated', 'translate-words')}</span>
                    ) : (
                      <>
                        <Button
                          className=""
                          iconPosition="left"
                          size="md"
                          tag="button"
                          type="button"
                          variant="primary"
                          onClick={handleLocoai}
                          disabled={locoaiBusy}
                        >
                          {locoaiButtonLabel}
                        </Button>
                        {locoaiError && (
                          <p className='text-sm text-black mt-2 mb-0' role='alert'>{locoaiError}</p>
                        )}
                      </>
                    )}
                  </div>
                  <div className='w-[30%] flex items-center object-contain p-2'>
                    <img className="w-auto max-h-24  " src={`${window.lmat_settings_logo_data.logoUrl}loco.png`} alt="Loco translate logo" />
                  </div>
                  <div></div>
                </Container.Item>
              </div>

            ) : null
          }
          <Container className='bg-white flex flex-col gap-4 p-6 shadow-sm rounded-lg'>
            <div>
              <h2><a className="no-underline text-black" target="_blank" href="https://wordpress.org/support/plugin/translate-words/reviews/#new-post">{__('Rate Us ⭐⭐⭐⭐⭐', 'translate-words')}</a></h2>
              <p>{__("We'd love your feedback! Hope this addon made auto-translations easier for you.", 'translate-words')}</p>
              <a target="_blank" href="https://wordpress.org/support/plugin/translate-words/reviews/#new-post">{__('Submit a Review →', 'translate-words')}</a>
            </div>
          </Container>
        </div>

      </div>
    </>
  )
}

export default Sidebar