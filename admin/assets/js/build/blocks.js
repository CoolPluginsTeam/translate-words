/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
/* global wp */
(function () {
  var _wp$i18n = wp.i18n,
    __ = _wp$i18n.__,
    _x = _wp$i18n._x;
  var _wp$blocks = wp.blocks,
    registerBlockType = _wp$blocks.registerBlockType,
    createBlock = _wp$blocks.createBlock;
  var Fragment = wp.element.Fragment;
  var _ref = wp.blockEditor || wp.editor,
    InspectorControls = _ref.InspectorControls,
    BlockControls = _ref.BlockControls,
    useBlockProps = _ref.useBlockProps; // fallback for older WP
  var _wp$components = wp.components,
    PanelBody = _wp$components.PanelBody,
    ToggleControl = _wp$components.ToggleControl,
    SelectControl = _wp$components.SelectControl,
    RadioControl = _wp$components.RadioControl,
    RangeControl = _wp$components.RangeControl,
    ToolbarGroup = _wp$components.ToolbarGroup,
    ToolbarButton = _wp$components.ToolbarButton,
    ToolbarDropdownMenu = _wp$components.ToolbarDropdownMenu,
    Disabled = _wp$components.Disabled;
  var UnitControl = wp.components.UnitControl || wp.components.__experimentalUnitControl;
  var el = wp.element.createElement;
  var ServerSideRender = wp.serverSideRender && wp.serverSideRender.default || wp.serverSideRender;
  var addFilter = wp.hooks.addFilter;

  // ---------------------------------------------------------------------------
  // Icon: translation (simple inline SVG)
  // ---------------------------------------------------------------------------
  var TranslationIcon = function TranslationIcon() {
    return wp.element.createElement('span', {
      className: 'linguator-block-icon',
      style: {
        fontFamily: 'linguator',
        fontSize: '20px',
        lineHeight: '1',
        display: 'inline-block'
      }
    }, "\uE900");
  };

  // ---------------------------------------------------------------------------
  // Icon: submenu chevron (used in nav dropdown)
  // ---------------------------------------------------------------------------
  var SubmenuChevron = function SubmenuChevron() {
    return wp.element.createElement('svg', {
      width: 12,
      height: 12,
      viewBox: '0 0 12 12',
      xmlns: 'http://www.w3.org/2000/svg',
      fill: 'none'
    }, wp.element.createElement('path', {
      d: 'M1.5 4L6 8l4.5-4',
      strokeWidth: 1.5,
      stroke: 'currentColor'
    }));
  };

  // ---------------------------------------------------------------------------
  // Shared attributes
  // ---------------------------------------------------------------------------
  var sharedAttributes = {
    dropdown: {
      type: 'boolean',
      default: false
    },
    show_names: {
      type: 'boolean',
      default: true
    },
    show_flags: {
      type: 'boolean',
      default: false
    },
    force_home: {
      type: 'boolean',
      default: false
    },
    hide_current: {
      type: 'boolean',
      default: false
    },
    hide_if_no_translation: {
      type: 'boolean',
      default: false
    }
  };

  // ---------------------------------------------------------------------------
  // Helper: ensure at least one of show_names/show_flags is true
  // ---------------------------------------------------------------------------
  function enforceNamesOrFlags(nextAttrs, currentAttrs) {
    var result = _objectSpread(_objectSpread({}, currentAttrs), nextAttrs);
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
  function SwitcherInspector(_ref2) {
    var attributes = _ref2.attributes,
      setAttributes = _ref2.setAttributes,
      _ref2$showHideCurrent = _ref2.showHideCurrentEvenInDropdown,
      showHideCurrentEvenInDropdown = _ref2$showHideCurrent === void 0 ? false : _ref2$showHideCurrent;
    var dropdown = attributes.dropdown,
      show_names = attributes.show_names,
      show_flags = attributes.show_flags,
      force_home = attributes.force_home,
      hide_current = attributes.hide_current,
      hide_if_no_translation = attributes.hide_if_no_translation;
    var update = function update(patch) {
      setAttributes(enforceNamesOrFlags(patch, attributes));
    };
    return wp.element.createElement(InspectorControls, {}, wp.element.createElement(PanelBody, {
      title: __('Language switcher settings', 'translate-words')
    }, wp.element.createElement(ToggleControl, {
      label: __('Display as dropdown', 'translate-words'),
      checked: !!dropdown,
      onChange: function onChange(v) {
        return update({
          dropdown: !!v
        });
      }
    }), (!dropdown || showHideCurrentEvenInDropdown) && wp.element.createElement(ToggleControl, {
      label: __('Show language names', 'translate-words'),
      checked: !!show_names,
      onChange: function onChange(v) {
        return update({
          show_names: !!v
        });
      }
    }), (!dropdown || showHideCurrentEvenInDropdown) && wp.element.createElement(ToggleControl, {
      label: __('Display flags', 'translate-words'),
      checked: !!show_flags,
      onChange: function onChange(v) {
        return update({
          show_flags: !!v
        });
      }
    }), wp.element.createElement(ToggleControl, {
      label: __('Force link to front page', 'translate-words'),
      checked: !!force_home,
      onChange: function onChange(v) {
        return update({
          force_home: !!v
        });
      }
    }), !attributes.dropdown && wp.element.createElement(ToggleControl, {
      label: __('Hide the current language', 'translate-words'),
      checked: !!hide_current,
      onChange: function onChange(v) {
        return update({
          hide_current: !!v
        });
      }
    }), wp.element.createElement(ToggleControl, {
      label: __('Hide languages with no translation', 'translate-words'),
      checked: !!hide_if_no_translation,
      onChange: function onChange(v) {
        return update({
          hide_if_no_translation: !!v
        });
      }
    })));
  }

  // ---------------------------------------------------------------------------
  // Regular block: linguator/language-switcher
  // ---------------------------------------------------------------------------
  var CSS_LENGTH_UNITS = [{
    value: 'px',
    label: 'px'
  }, {
    value: 'em',
    label: 'em'
  }, {
    value: 'rem',
    label: 'rem'
  }, {
    value: 'vh',
    label: 'vh'
  }, {
    value: 'vw',
    label: 'vw'
  }];
  var LABEL_SPACING_DEFAULT_VALUE = '0.3em';
  var switcherAttributes = {
    layout: {
      type: 'string',
      enum: ['vertical', 'horizontal', 'dropdown', 'select'],
      default: 'vertical'
    },
    show_labels: {
      type: 'string',
      enum: ['names', 'codes', ''],
      default: 'names'
    },
    show_flags: {
      type: 'boolean',
      default: false
    },
    force_home: {
      type: 'boolean',
      default: false
    },
    hide_current: {
      type: 'boolean',
      default: false
    },
    hide_if_no_translation: {
      type: 'boolean',
      default: false
    },
    flag_aspect_ratio: {
      type: 'string',
      enum: ['3:2', '1:1'],
      default: '3:2'
    },
    flag_border_radius: {
      type: 'number',
      default: 0
    },
    flag_width: {
      type: 'string',
      default: '18px'
    },
    flag_label_spacing: {
      type: 'string',
      default: LABEL_SPACING_DEFAULT_VALUE
    },
    className: {
      type: 'string',
      default: ''
    }
  };
  var switcherDefaults = Object.keys(switcherAttributes).reduce(function (defaults, key) {
    defaults[key] = switcherAttributes[key].default;
    return defaults;
  }, {});

  // Converts blocks saved with the `dropdown` and `show_names` attributes.
  var deprecated = [{
    attributes: {
      dropdown: {
        type: 'boolean',
        default: false
      },
      show_names: {
        type: 'boolean',
        default: true
      },
      show_flags: {
        type: 'boolean',
        default: false
      },
      force_home: {
        type: 'boolean',
        default: false
      },
      hide_current: {
        type: 'boolean',
        default: false
      },
      hide_if_no_translation: {
        type: 'boolean',
        default: false
      },
      className: {
        type: 'string',
        default: ''
      }
    },
    isEligible: function isEligible(attributes) {
      return 'dropdown' in attributes || 'show_names' in attributes;
    },
    migrate: function migrate(attributes) {
      var dropdown = attributes.dropdown,
        show_names = attributes.show_names,
        show_flags = attributes.show_flags,
        force_home = attributes.force_home,
        hide_current = attributes.hide_current,
        hide_if_no_translation = attributes.hide_if_no_translation,
        className = attributes.className;
      return _objectSpread(_objectSpread({}, switcherDefaults), {}, {
        layout: dropdown ? 'select' : 'vertical',
        show_labels: show_names ? 'names' : '',
        show_flags: show_names ? !!show_flags : true,
        force_home: !!force_home,
        hide_current: !!hide_current,
        hide_if_no_translation: !!hide_if_no_translation,
        className: className || ''
      });
    },
    save: function save() {
      return null;
    }
  }];

  // Toolbar and inspector controls of the language switcher block.
  function SwitcherControls(_ref3) {
    var attributes = _ref3.attributes,
      setAttributes = _ref3.setAttributes;
    var layout = attributes.layout,
      show_labels = attributes.show_labels,
      show_flags = attributes.show_flags,
      force_home = attributes.force_home,
      hide_current = attributes.hide_current,
      flag_aspect_ratio = attributes.flag_aspect_ratio,
      flag_border_radius = attributes.flag_border_radius,
      flag_width = attributes.flag_width,
      flag_label_spacing = attributes.flag_label_spacing,
      hide_if_no_translation = attributes.hide_if_no_translation;
    var warn = function warn(message) {
      if (wp.data && wp.notices) {
        wp.data.dispatch(wp.notices.store || 'core/notices').createWarningNotice(message);
      }
    };
    var noLabelsWarning = __('Labels cannot be hidden if flags are not shown.', 'translate-words');
    var noFlagsWarning = __('Flags cannot be hidden if labels are not displayed.', 'translate-words');
    var layoutOptions = [{
      label: _x('Horizontal', 'layout', 'translate-words'),
      value: 'horizontal'
    }, {
      label: _x('Vertical', 'layout', 'translate-words'),
      value: 'vertical'
    }, {
      label: __('Dropdown', 'translate-words'),
      value: 'dropdown'
    }, {
      label: __('Selector', 'translate-words'),
      value: 'select'
    }];
    var layoutToolbarControls = layoutOptions.filter(function (option) {
      return 'select' !== option.value;
    }).map(function (option) {
      return {
        title: option.label,
        onClick: function onClick() {
          return setAttributes({
            layout: option.value
          });
        }
      };
    });
    var labelOptions = [{
      label: __('Language names', 'translate-words'),
      value: 'names'
    }, {
      label: __('Language codes', 'translate-words'),
      value: 'codes'
    }];
    var toolbarLabelControls = [{
      title: __('Language names', 'translate-words'),
      onClick: function onClick() {
        return setAttributes({
          show_labels: 'names'
        });
      }
    }, {
      title: __('Language codes', 'translate-words'),
      onClick: function onClick() {
        return setAttributes({
          show_labels: 'codes'
        });
      }
    }];
    if (show_flags) {
      labelOptions.push({
        label: _x('None', 'Labels', 'translate-words'),
        value: ''
      });
      toolbarLabelControls.push({
        title: _x('None', 'Labels', 'translate-words'),
        onClick: function onClick() {
          return setAttributes({
            show_labels: '',
            flag_label_spacing: LABEL_SPACING_DEFAULT_VALUE
          });
        }
      });
    }
    var onLayoutChange = function onLayoutChange(value) {
      var patch = {
        layout: value
      };
      if ('select' === value) {
        patch.show_flags = false;
        if ('' === show_labels) {
          patch.show_labels = 'names';
        }
      }
      setAttributes(patch);
    };
    var onLabelsChange = function onLabelsChange(value) {
      if ('' === value) {
        if (!show_flags) {
          warn(noLabelsWarning);
          return;
        }
        setAttributes({
          show_labels: value,
          flag_label_spacing: LABEL_SPACING_DEFAULT_VALUE
        });
        return;
      }
      setAttributes({
        show_labels: value
      });
    };
    var onFlagsChange = function onFlagsChange(value) {
      if ('' === show_labels && !value) {
        warn(noFlagsWarning);
        return;
      }
      setAttributes({
        show_flags: !!value
      });
    };
    var flagControls = show_flags && [el(RadioControl, {
      key: 'aspect',
      label: __('Aspect', 'translate-words'),
      selected: flag_aspect_ratio,
      options: [{
        label: __('Landscape (3:2)', 'translate-words'),
        value: '3:2'
      }, {
        label: __('Square (1:1)', 'translate-words'),
        value: '1:1'
      }],
      onChange: function onChange(value) {
        return setAttributes({
          flag_aspect_ratio: value
        });
      }
    }), el(RangeControl, {
      key: 'radius',
      label: __('Border radius', 'translate-words'),
      initialPosition: 0,
      value: flag_border_radius,
      min: 0,
      max: 100,
      step: 1,
      allowReset: true,
      resetFallbackValue: 0,
      onChange: function onChange(value) {
        return setAttributes({
          flag_border_radius: value || 0
        });
      },
      __next40pxDefaultSize: true
    }), UnitControl && el(UnitControl, {
      key: 'width',
      __next40pxDefaultSize: true,
      label: __('Size', 'translate-words'),
      value: flag_width,
      units: CSS_LENGTH_UNITS,
      onChange: function onChange(value) {
        return setAttributes({
          flag_width: value
        });
      }
    }), UnitControl && '' !== show_labels && el(UnitControl, {
      key: 'spacing',
      __next40pxDefaultSize: true,
      label: __('Label spacing', 'translate-words'),
      value: flag_label_spacing,
      units: CSS_LENGTH_UNITS,
      onChange: function onChange(value) {
        return setAttributes({
          flag_label_spacing: value
        });
      }
    })];
    return el(Fragment, {}, el(InspectorControls, {}, el(PanelBody, {
      title: __('Display', 'translate-words')
    }, el(SelectControl, {
      label: __('Layout', 'translate-words'),
      value: layout,
      options: layoutOptions,
      onChange: onLayoutChange,
      __next40pxDefaultSize: true
    }), 'select' !== layout && el(SelectControl, {
      label: __('Labels', 'translate-words'),
      value: show_labels,
      options: labelOptions,
      onChange: onLabelsChange,
      __next40pxDefaultSize: true
    })), 'select' !== layout && el(PanelBody, {
      title: __('Flags Settings', 'translate-words')
    }, el(ToggleControl, {
      label: __('Display flags', 'translate-words'),
      checked: !!show_flags,
      onChange: onFlagsChange
    }), flagControls), el(PanelBody, {
      title: __('Behavior', 'translate-words')
    }, el(ToggleControl, {
      label: __('Force link to front page', 'translate-words'),
      checked: !!force_home,
      onChange: function onChange(value) {
        return setAttributes({
          force_home: !!value
        });
      }
    }), el(ToggleControl, {
      label: __('Hide the current language', 'translate-words'),
      checked: !!hide_current,
      onChange: function onChange(value) {
        return setAttributes({
          hide_current: !!value
        });
      }
    }), el(ToggleControl, {
      label: __('Hide languages with no translation', 'translate-words'),
      checked: !!hide_if_no_translation,
      onChange: function onChange(value) {
        return setAttributes({
          hide_if_no_translation: !!value
        });
      }
    }))), el(BlockControls, {}, el(ToolbarGroup, {}, el(ToolbarDropdownMenu, {
      icon: 'layout',
      label: __('Layout', 'translate-words'),
      controls: layoutToolbarControls
    }), 'select' !== layout && el(ToolbarButton, {
      icon: el('span', {
        className: 'dashicons dashicons-flag'
      }),
      label: show_flags ? __('Hide flags', 'translate-words') : __('Display flags', 'translate-words'),
      onClick: function onClick() {
        return onFlagsChange(!show_flags);
      }
    }), 'select' !== layout && el(ToolbarDropdownMenu, {
      icon: el('span', {
        className: 'dashicons dashicons-editor-textcolor'
      }),
      label: __('Labels', 'translate-words'),
      controls: toolbarLabelControls
    }))));
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
      spacing: {
        margin: true,
        padding: true,
        blockGap: true
      },
      align: true,
      color: {
        background: true
      },
      typography: {
        fontSize: true,
        lineHeight: true,
        textAlign: true
      }
    },
    deprecated: deprecated,
    edit: function edit(props) {
      var blockProps = useBlockProps ? useBlockProps() : {};
      return el('div', blockProps, el(SwitcherControls, {
        attributes: props.attributes,
        setAttributes: props.setAttributes
      }), el(Disabled, {}, ServerSideRender ? el(ServerSideRender, {
        block: 'linguator/language-switcher',
        attributes: props.attributes,
        skipBlockSupportAttributes: true
      }) : el('div', {}, __('Language Switcher preview (SSR not available).', 'translate-words'))));
    },
    save: function save() {
      return null;
    } // Rendered via PHP
  });

  // ---------------------------------------------------------------------------
  // Navigation child block: linguator/navigation-language-switcher
  // ---------------------------------------------------------------------------
  var NAV_BLOCK = 'linguator/navigation-language-switcher';
  registerBlockType(NAV_BLOCK, {
    title: __('Language switcher', 'translate-words'),
    description: __('Add a language switcher to the Navigation block.', 'translate-words'),
    icon: TranslationIcon,
    category: 'widgets',
    parent: ['core/navigation'],
    attributes: _objectSpread({}, sharedAttributes),
    usesContext: ['textColor', 'customTextColor', 'backgroundColor', 'customBackgroundColor', 'overlayTextColor', 'customOverlayTextColor', 'overlayBackgroundColor', 'customOverlayBackgroundColor', 'fontSize', 'customFontSize', 'showSubmenuIcon', 'openSubmenusOnClick', 'style'],
    transforms: {
      from: [{
        type: 'block',
        blocks: ['core/navigation-link'],
        transform: function transform() {
          return createBlock(NAV_BLOCK);
        }
      }]
    },
    edit: function edit(props) {
      var attributes = props.attributes,
        setAttributes = props.setAttributes,
        context = props.context;
      var _ref4 = context || {},
        showSubmenuIcon = _ref4.showSubmenuIcon,
        openSubmenusOnClick = _ref4.openSubmenusOnClick;
      var dropdown = attributes.dropdown;
      var maybeSubmenuIcon = dropdown && (showSubmenuIcon || openSubmenusOnClick) ? wp.element.createElement('span', {
        className: 'wp-block-navigation__submenu-icon'
      }, wp.element.createElement(SubmenuChevron)) : null;
      return wp.element.createElement(Fragment, {}, wp.element.createElement(SwitcherInspector, {
        attributes: attributes,
        setAttributes: setAttributes,
        // In the nav block we allow toggling names/flags even in dropdown for clarity
        showHideCurrentEvenInDropdown: true
      }), wp.element.createElement(Disabled, {}, wp.element.createElement('div', {
        className: 'wp-block-navigation-item'
      }, ServerSideRender ? wp.element.createElement(ServerSideRender, {
        block: NAV_BLOCK,
        attributes: attributes,
        className: 'wp-block-navigation__container block-editor-block-list__layout'
      }) : wp.element.createElement('div', {}, __('Language Switcher (Navigation) preview (SSR not available).', 'translate-words')), maybeSubmenuIcon)));
    },
    save: function save() {
      return null;
    } // Rendered via PHP
  });

  // ---------------------------------------------------------------------------
  // Classic Menu → Navigation conversion hook
  // Replaces a menu item with URL "#lmat_switcher" by our NAV_BLOCK with options from meta._lmat_menu_item
  // WARNING: relies on an unstable filter that may change across WP versions.
  // ---------------------------------------------------------------------------
  function mapBlockTree(blocks, menuItems, blocksMapping, mapper) {
    var _convert = function convert(block) {
      var replaced = mapper(block, menuItems, blocksMapping);
      var innerBlocks = (replaced.innerBlocks || []).map(function (b) {
        return _convert(b);
      });
      return _objectSpread(_objectSpread({}, replaced), {}, {
        innerBlocks: innerBlocks
      });
    };
    return blocks.map(_convert);
  }
  function blocksFilter(block, menuItems, blocksMapping) {
    if (block.name === 'core/navigation-link' && block.attributes && block.attributes.url === '#lmat_switcher') {
      var menuItem = (menuItems || []).find(function (m) {
        return m && m.url === '#lmat_switcher';
      });
      var attrs = menuItem && menuItem.meta && menuItem.meta._lmat_menu_item || {};
      var newBlock = createBlock(NAV_BLOCK, attrs);
      if (menuItem && typeof menuItem.id !== 'undefined') {
        blocksMapping[menuItem.id] = newBlock.clientId;
      }
      return newBlock;
    }
    return block;
  }
  function menuItemsToBlocksFilter(blocks, menuItems) {
    return _objectSpread(_objectSpread({}, blocks), {}, {
      innerBlocks: mapBlockTree(blocks.innerBlocks || [], menuItems || [], blocks.mapping || {}, blocksFilter)
    });
  }
  addFilter('blocks.navigation.__unstableMenuItemsToBlocks', 'linguator/include-language-switcher', menuItemsToBlocksFilter);
})();
/******/ })()
;