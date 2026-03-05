/**
 * Site Editor sidebar bootstrap
 */

import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-site';

const SIDEBAR_NAME = 'lmat-site-sidebar';

const Sidebar = () => {
    return (
        <>
            <PluginSidebarMoreMenuItem target={ SIDEBAR_NAME }>
                { __( 'Languages', 'translate-words' ) }
            </PluginSidebarMoreMenuItem>
            <PluginSidebar name={ SIDEBAR_NAME } title={ __( 'Languages', 'translate-words' ) }>
                <div className="lmat-sidebar-section">
                    <p>{ __( 'Linguator sidebar (Site Editor)', 'translate-words' ) }</p>
                </div>
            </PluginSidebar>
        </>
    );
};

registerPlugin( SIDEBAR_NAME, { render: Sidebar } );


