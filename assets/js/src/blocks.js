/* global wp */
(function () {
    const { __, _x } = wp.i18n;
    const { registerBlockType, createBlock } = wp.blocks;
    const { Fragment } = wp.element;
    const {
      InspectorControls,
      BlockControls,
      useBlockProps,
    } = wp.blockEditor || wp.editor; // fallback for older WP
    const {
      PanelBody,
      ToggleControl,
      SelectControl,
      RadioControl,
      RangeControl,
      ToolbarGroup,
      ToolbarButton,
      ToolbarDropdownMenu,
      Disabled,
    } = wp.components;
    const UnitControl = wp.components.UnitControl || wp.components.__experimentalUnitControl;
    const el = wp.element.createElement;
    const ServerSideRender = (wp.serverSideRender && wp.serverSideRender.default) || wp.serverSideRender;
    const { addFilter } = wp.hooks;

    // ---------------------------------------------------------------------------
    // Icon: translation (simple inline SVG)
    // ---------------------------------------------------------------------------
    const TranslationIcon = function () {
      return wp.element.createElement('span', {
        className: 'linguator-block-icon',
        style: {
          fontFamily: 'linguator',
          fontSize: '20px',
          lineHeight: '1',
          display: 'inline-block'
        }
      }, '\ue900');
    };
  
    // ---------------------------------------------------------------------------
    // Icon: submenu chevron (used in nav dropdown)
    // ---------------------------------------------------------------------------
    const SubmenuChevron = function () {
      return wp.element.createElement(
        'svg',
        { width: 12, height: 12, viewBox: '0 0 12 12', xmlns: 'http://www.w3.org/2000/svg', fill: 'none' },
        wp.element.createElement('path', { d: 'M1.5 4L6 8l4.5-4', strokeWidth: 1.5, stroke: 'currentColor' })
      );
    };
  
    // ---------------------------------------------------------------------------
    // Shared attributes
    // ---------------------------------------------------------------------------
    const sharedAttributes = {
      dropdown: { type: 'boolean', default: false },
      show_names: { type: 'boolean', default: true },
      show_flags: { type: 'boolean', default: false },
      force_home: { type: 'boolean', default: false },
      hide_current: { type: 'boolean', default: false },
      hide_if_no_translation: { type: 'boolean', default: false },
    };
  
    // ---------------------------------------------------------------------------
    // Helper: ensure at least one of show_names/show_flags is true
    // ---------------------------------------------------------------------------
    function enforceNamesOrFlags(nextAttrs, currentAttrs) {
      const result = { ...currentAttrs, ...nextAttrs };
      if (result.show_names === false && result.show_flags === false) {
        // If the user just turned one off and both are now false, re-enable the other.
        // Prefer re-enabling the one that did NOT change in this update.
        if (typeof nextAttrs.show_names !== 'undefined') {
          result.show_flags = true;
        } else {
          result.show_names = true;
        }
      }
      return result;
    }
  
    // ---------------------------------------------------------------------------
    // Reusable inspector panel for both blocks
    // ---------------------------------------------------------------------------
    function SwitcherInspector({ attributes, setAttributes, showHideCurrentEvenInDropdown = false }) {
      const { dropdown, show_names, show_flags, force_home, hide_current, hide_if_no_translation } = attributes;
  
      const update = (patch) => {
        setAttributes(enforceNamesOrFlags(patch, attributes));
      };
  
      return wp.element.createElement(
        InspectorControls,
        {},
        wp.element.createElement(
          PanelBody,
          { title: __('Language switcher settings', 'translate-words') },
          wp.element.createElement(ToggleControl, {
            label: __('Display as dropdown', 'translate-words'),
            checked: !!dropdown,
            onChange: (v) => update({ dropdown: !!v }),
          }),
          (!dropdown || showHideCurrentEvenInDropdown) &&
            wp.element.createElement(ToggleControl, {
              label: __('Show language names', 'translate-words'),
              checked: !!show_names,
              onChange: (v) => update({ show_names: !!v }),
            }),
          (!dropdown || showHideCurrentEvenInDropdown) &&
            wp.element.createElement(ToggleControl, {
              label: __('Display flags', 'translate-words'),
              checked: !!show_flags,
              onChange: (v) => update({ show_flags: !!v }),
            }),
          wp.element.createElement(ToggleControl, {
            label: __('Force link to front page', 'translate-words'),
            checked: !!force_home,
            onChange: (v) => update({ force_home: !!v }),
          }),
          !attributes.dropdown &&
            wp.element.createElement(ToggleControl, {
              label: __('Hide the current language', 'translate-words'),
              checked: !!hide_current,
              onChange: (v) => update({ hide_current: !!v }),
            }),
          wp.element.createElement(ToggleControl, {
            label: __('Hide languages with no translation', 'translate-words'),
            checked: !!hide_if_no_translation,
            onChange: (v) => update({ hide_if_no_translation: !!v }),
          })
        )
      );
    }
  
    // ---------------------------------------------------------------------------
    // Regular block: linguator/language-switcher
    // ---------------------------------------------------------------------------
    const CSS_LENGTH_UNITS = [
      { value: 'px', label: 'px' },
      { value: 'em', label: 'em' },
      { value: 'rem', label: 'rem' },
      { value: 'vh', label: 'vh' },
      { value: 'vw', label: 'vw' },
    ];
    const LABEL_SPACING_DEFAULT_VALUE = '0.3em';

    const switcherAttributes = {
      layout: { type: 'string', enum: ['vertical', 'horizontal', 'dropdown', 'select'], default: 'vertical' },
      show_labels: { type: 'string', enum: ['names', 'codes', ''], default: 'names' },
      show_flags: { type: 'boolean', default: false },
      force_home: { type: 'boolean', default: false },
      hide_current: { type: 'boolean', default: false },
      hide_if_no_translation: { type: 'boolean', default: false },
      flag_aspect_ratio: { type: 'string', enum: ['3:2', '1:1'], default: '3:2' },
      flag_border_radius: { type: 'number', default: 0 },
      flag_width: { type: 'string', default: '18px' },
      flag_label_spacing: { type: 'string', default: LABEL_SPACING_DEFAULT_VALUE },
      className: { type: 'string', default: '' },
    };

    const switcherDefaults = Object.keys(switcherAttributes).reduce((defaults, key) => {
      defaults[key] = switcherAttributes[key].default;
      return defaults;
    }, {});

    // Converts blocks saved with the `dropdown` and `show_names` attributes.
    const deprecated = [
      {
        attributes: {
          dropdown: { type: 'boolean', default: false },
          show_names: { type: 'boolean', default: true },
          show_flags: { type: 'boolean', default: false },
          force_home: { type: 'boolean', default: false },
          hide_current: { type: 'boolean', default: false },
          hide_if_no_translation: { type: 'boolean', default: false },
          className: { type: 'string', default: '' },
        },
        isEligible: (attributes) => 'dropdown' in attributes || 'show_names' in attributes,
        migrate: (attributes) => {
          const { dropdown, show_names, show_flags, force_home, hide_current, hide_if_no_translation, className } = attributes;
          return {
            ...switcherDefaults,
            layout: dropdown ? 'select' : 'vertical',
            show_labels: show_names ? 'names' : '',
            show_flags: show_names ? !!show_flags : true,
            force_home: !!force_home,
            hide_current: !!hide_current,
            hide_if_no_translation: !!hide_if_no_translation,
            className: className || '',
          };
        },
        save: () => null,
      },
    ];

    // Toolbar and inspector controls of the language switcher block.
    function SwitcherControls({ attributes, setAttributes }) {
      const {
        layout,
        show_labels,
        show_flags,
        force_home,
        hide_current,
        flag_aspect_ratio,
        flag_border_radius,
        flag_width,
        flag_label_spacing,
        hide_if_no_translation,
      } = attributes;

      const warn = (message) => {
        if (wp.data && wp.notices) {
          wp.data.dispatch(wp.notices.store || 'core/notices').createWarningNotice(message);
        }
      };
      const noLabelsWarning = __('Labels cannot be hidden if flags are not shown.', 'translate-words');
      const noFlagsWarning = __('Flags cannot be hidden if labels are not displayed.', 'translate-words');

      const layoutOptions = [
        { label: _x('Horizontal', 'layout', 'translate-words'), value: 'horizontal' },
        { label: _x('Vertical', 'layout', 'translate-words'), value: 'vertical' },
        { label: __('Dropdown', 'translate-words'), value: 'dropdown' },
        { label: __('Selector', 'translate-words'), value: 'select' },
      ];
      const layoutToolbarControls = layoutOptions
        .filter((option) => 'select' !== option.value)
        .map((option) => ({ title: option.label, onClick: () => setAttributes({ layout: option.value }) }));

      const labelOptions = [
        { label: __('Language names', 'translate-words'), value: 'names' },
        { label: __('Language codes', 'translate-words'), value: 'codes' },
      ];
      const toolbarLabelControls = [
        { title: __('Language names', 'translate-words'), onClick: () => setAttributes({ show_labels: 'names' }) },
        { title: __('Language codes', 'translate-words'), onClick: () => setAttributes({ show_labels: 'codes' }) },
      ];
      if (show_flags) {
        labelOptions.push({ label: _x('None', 'Labels', 'translate-words'), value: '' });
        toolbarLabelControls.push({
          title: _x('None', 'Labels', 'translate-words'),
          onClick: () => setAttributes({ show_labels: '', flag_label_spacing: LABEL_SPACING_DEFAULT_VALUE }),
        });
      }

      const onLayoutChange = (value) => {
        const patch = { layout: value };
        if ('select' === value) {
          patch.show_flags = false;
          if ('' === show_labels) {
            patch.show_labels = 'names';
          }
        }
        setAttributes(patch);
      };

      const onLabelsChange = (value) => {
        if ('' === value) {
          if (!show_flags) {
            warn(noLabelsWarning);
            return;
          }
          setAttributes({ show_labels: value, flag_label_spacing: LABEL_SPACING_DEFAULT_VALUE });
          return;
        }
        setAttributes({ show_labels: value });
      };

      const onFlagsChange = (value) => {
        if ('' === show_labels && !value) {
          warn(noFlagsWarning);
          return;
        }
        setAttributes({ show_flags: !!value });
      };

      const flagControls = show_flags && [
        el(RadioControl, {
          key: 'aspect',
          label: __('Aspect', 'translate-words'),
          selected: flag_aspect_ratio,
          options: [
            { label: __('Landscape (3:2)', 'translate-words'), value: '3:2' },
            { label: __('Square (1:1)', 'translate-words'), value: '1:1' },
          ],
          onChange: (value) => setAttributes({ flag_aspect_ratio: value }),
        }),
        el(RangeControl, {
          key: 'radius',
          label: __('Border radius', 'translate-words'),
          initialPosition: 0,
          value: flag_border_radius,
          min: 0,
          max: 100,
          step: 1,
          allowReset: true,
          resetFallbackValue: 0,
          onChange: (value) => setAttributes({ flag_border_radius: value || 0 }),
          __next40pxDefaultSize: true,
        }),
        UnitControl &&
          el(UnitControl, {
            key: 'width',
            __next40pxDefaultSize: true,
            label: __('Size', 'translate-words'),
            value: flag_width,
            units: CSS_LENGTH_UNITS,
            onChange: (value) => setAttributes({ flag_width: value }),
          }),
        UnitControl &&
          '' !== show_labels &&
          el(UnitControl, {
            key: 'spacing',
            __next40pxDefaultSize: true,
            label: __('Label spacing', 'translate-words'),
            value: flag_label_spacing,
            units: CSS_LENGTH_UNITS,
            onChange: (value) => setAttributes({ flag_label_spacing: value }),
          }),
      ];

      return el(
        Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Display', 'translate-words') },
            el(SelectControl, {
              label: __('Layout', 'translate-words'),
              value: layout,
              options: layoutOptions,
              onChange: onLayoutChange,
              __next40pxDefaultSize: true,
            }),
            'select' !== layout &&
              el(SelectControl, {
                label: __('Labels', 'translate-words'),
                value: show_labels,
                options: labelOptions,
                onChange: onLabelsChange,
                __next40pxDefaultSize: true,
              })
          ),
          'select' !== layout &&
            el(
              PanelBody,
              { title: __('Flags Settings', 'translate-words') },
              el(ToggleControl, {
                label: __('Display flags', 'translate-words'),
                checked: !!show_flags,
                onChange: onFlagsChange,
              }),
              flagControls
            ),
          el(
            PanelBody,
            { title: __('Behavior', 'translate-words') },
            el(ToggleControl, {
              label: __('Force link to front page', 'translate-words'),
              checked: !!force_home,
              onChange: (value) => setAttributes({ force_home: !!value }),
            }),
            el(ToggleControl, {
              label: __('Hide the current language', 'translate-words'),
              checked: !!hide_current,
              onChange: (value) => setAttributes({ hide_current: !!value }),
            }),
            el(ToggleControl, {
              label: __('Hide languages with no translation', 'translate-words'),
              checked: !!hide_if_no_translation,
              onChange: (value) => setAttributes({ hide_if_no_translation: !!value }),
            })
          )
        ),
        el(
          BlockControls,
          {},
          el(
            ToolbarGroup,
            {},
            el(ToolbarDropdownMenu, {
              icon: 'layout',
              label: __('Layout', 'translate-words'),
              controls: layoutToolbarControls,
            }),
            'select' !== layout &&
              el(ToolbarButton, {
                icon: el('span', { className: 'dashicons dashicons-flag' }),
                label: show_flags ? __('Hide flags', 'translate-words') : __('Display flags', 'translate-words'),
                onClick: () => onFlagsChange(!show_flags),
              }),
            'select' !== layout &&
              el(ToolbarDropdownMenu, {
                icon: el('span', { className: 'dashicons dashicons-editor-textcolor' }),
                label: __('Labels', 'translate-words'),
                controls: toolbarLabelControls,
              })
          )
        )
      );
    }

    registerBlockType('linguator/language-switcher', {
      apiVersion: 3,
      title: __('Language switcher', 'translate-words'),
      description: __('Add a language switcher so visitors can select their preferred language.', 'translate-words'),
      icon: TranslationIcon,
      category: 'widgets',
      attributes: switcherAttributes,
      supports: {
        html: false,
        spacing: { margin: true, padding: true, blockGap: true },
        align: true,
        color: { background: true },
        typography: { fontSize: true, lineHeight: true, textAlign: true },
      },
      deprecated,
      edit: (props) => {
        const blockProps = useBlockProps ? useBlockProps() : {};
        return el(
          'div',
          blockProps,
          el(SwitcherControls, { attributes: props.attributes, setAttributes: props.setAttributes }),
          el(
            Disabled,
            {},
            ServerSideRender
              ? el(ServerSideRender, {
                  block: 'linguator/language-switcher',
                  attributes: props.attributes,
                  skipBlockSupportAttributes: true,
                })
              : el('div', {}, __('Language Switcher preview (SSR not available).', 'translate-words'))
          )
        );
      },
      save: () => null, // Rendered via PHP
    });

    // ---------------------------------------------------------------------------
    // Navigation child block: linguator/navigation-language-switcher
    // ---------------------------------------------------------------------------
    const NAV_BLOCK = 'linguator/navigation-language-switcher';
    registerBlockType(NAV_BLOCK, {
      title: __('Language switcher', 'translate-words'),
      description: __('Add a language switcher to the Navigation block.', 'translate-words'),
      icon: TranslationIcon,
      category: 'widgets',
      parent: ['core/navigation'],
      attributes: { ...sharedAttributes },
      usesContext: [
        'textColor',
        'customTextColor',
        'backgroundColor',
        'customBackgroundColor',
        'overlayTextColor',
        'customOverlayTextColor',
        'overlayBackgroundColor',
        'customOverlayBackgroundColor',
        'fontSize',
        'customFontSize',
        'showSubmenuIcon',
        'openSubmenusOnClick',
        'style',
      ],
      transforms: {
        from: [
          {
            type: 'block',
            blocks: ['core/navigation-link'],
            transform: () => createBlock(NAV_BLOCK),
          },
        ],
      },
      edit: (props) => {
        const { attributes, setAttributes, context } = props;
        const { showSubmenuIcon, openSubmenusOnClick } = context || {};
        const { dropdown } = attributes;
  
        const maybeSubmenuIcon =
          (dropdown && (showSubmenuIcon || openSubmenusOnClick)) ?
            wp.element.createElement('span', { className: 'wp-block-navigation__submenu-icon' }, wp.element.createElement(SubmenuChevron)) :
            null;
  
        return wp.element.createElement(
          Fragment,
          {},
          wp.element.createElement(SwitcherInspector, {
            attributes,
            setAttributes,
            // In the nav block we allow toggling names/flags even in dropdown for clarity
            showHideCurrentEvenInDropdown: true,
          }),
          wp.element.createElement(
            Disabled,
            {},
            wp.element.createElement(
              'div',
              { className: 'wp-block-navigation-item' },
              ServerSideRender
                ? wp.element.createElement(ServerSideRender, {
                    block: NAV_BLOCK,
                    attributes,
                    className: 'wp-block-navigation__container block-editor-block-list__layout',
                  })
                : wp.element.createElement('div', {}, __('Language Switcher (Navigation) preview (SSR not available).', 'translate-words')),
              maybeSubmenuIcon
            )
          )
        );
      },
      save: () => null, // Rendered via PHP
    });
  
    // ---------------------------------------------------------------------------
    // Classic Menu → Navigation conversion hook
    // Replaces a menu item with URL "#lmat_switcher" by our NAV_BLOCK with options from meta._lmat_menu_item
    // WARNING: relies on an unstable filter that may change across WP versions.
    // ---------------------------------------------------------------------------
    function mapBlockTree(blocks, menuItems, blocksMapping, mapper) {
      const convert = (block) => {
        const replaced = mapper(block, menuItems, blocksMapping);
        const innerBlocks = (replaced.innerBlocks || []).map((b) => convert(b));
        return { ...replaced, innerBlocks };
      };
      return blocks.map(convert);
    }
  
    function blocksFilter(block, menuItems, blocksMapping) {
      if (block.name === 'core/navigation-link' && block.attributes && block.attributes.url === '#lmat_switcher') {
        const menuItem = (menuItems || []).find((m) => m && m.url === '#lmat_switcher');
        const attrs = (menuItem && menuItem.meta && menuItem.meta._lmat_menu_item) || {};
        const newBlock = createBlock(NAV_BLOCK, attrs);
        if (menuItem && typeof menuItem.id !== 'undefined') {
          blocksMapping[menuItem.id] = newBlock.clientId;
        }
        return newBlock;
      }
      return block;
    }
  
    function menuItemsToBlocksFilter(blocks, menuItems) {
      return {
        ...blocks,
        innerBlocks: mapBlockTree(blocks.innerBlocks || [], menuItems || [], blocks.mapping || {}, blocksFilter),
      };
    }
  
    addFilter(
      'blocks.navigation.__unstableMenuItemsToBlocks',
      'linguator/include-language-switcher',
      menuItemsToBlocksFilter
    );
  })();

