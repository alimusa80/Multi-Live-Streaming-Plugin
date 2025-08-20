(function(blocks, element, editor, components, i18n) {
    'use strict';
    
    const { registerBlockType } = blocks;
    const { createElement: el } = element;
    const { InspectorControls } = editor;
    const { PanelBody, TextControl, SelectControl, ToggleControl } = components;
    const { __ } = i18n;
    
    registerBlockType('live-tv-streaming/player', {
        title: liveTVBlock.title,
        description: liveTVBlock.description,
        icon: liveTVBlock.icon,
        category: liveTVBlock.category,
        
        attributes: {
            width: {
                type: 'string',
                default: '100%'
            },
            height: {
                type: 'string',
                default: '400px'
            },
            category: {
                type: 'string',
                default: ''
            },
            autoplay: {
                type: 'string',
                default: 'false'
            },
            show_controls: {
                type: 'string',
                default: 'true'
            },
            responsive: {
                type: 'string',
                default: 'true'
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { width, height, category, autoplay, show_controls, responsive } = attributes;
            
            return [
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: __('Player Settings', 'live-tv-streaming'),
                        initialOpen: true
                    },
                        el(TextControl, {
                            label: __('Width', 'live-tv-streaming'),
                            value: width,
                            onChange: function(value) {
                                setAttributes({ width: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Height', 'live-tv-streaming'),
                            value: height,
                            onChange: function(value) {
                                setAttributes({ height: value });
                            }
                        }),
                        el(TextControl, {
                            label: __('Category Filter', 'live-tv-streaming'),
                            value: category,
                            help: __('Filter channels by category (leave empty for all)', 'live-tv-streaming'),
                            onChange: function(value) {
                                setAttributes({ category: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Autoplay', 'live-tv-streaming'),
                            value: autoplay,
                            options: [
                                { label: __('Disabled', 'live-tv-streaming'), value: 'false' },
                                { label: __('Enabled', 'live-tv-streaming'), value: 'true' }
                            ],
                            onChange: function(value) {
                                setAttributes({ autoplay: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Show Controls', 'live-tv-streaming'),
                            value: show_controls,
                            options: [
                                { label: __('Show', 'live-tv-streaming'), value: 'true' },
                                { label: __('Hide', 'live-tv-streaming'), value: 'false' }
                            ],
                            onChange: function(value) {
                                setAttributes({ show_controls: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Responsive Design', 'live-tv-streaming'),
                            value: responsive,
                            options: [
                                { label: __('Enabled', 'live-tv-streaming'), value: 'true' },
                                { label: __('Disabled', 'live-tv-streaming'), value: 'false' }
                            ],
                            onChange: function(value) {
                                setAttributes({ responsive: value });
                            }
                        })
                    )
                ),
                
                el('div', {
                    className: 'live-tv-block-preview',
                    style: {
                        width: width,
                        height: height,
                        backgroundColor: '#000',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: '#fff',
                        fontSize: '16px',
                        fontFamily: 'system-ui, sans-serif',
                        borderRadius: '4px',
                        border: '2px dashed #ccc'
                    }
                },
                    el('div', {
                        style: {
                            textAlign: 'center'
                        }
                    },
                        el('div', {
                            style: {
                                fontSize: '24px',
                                marginBottom: '8px'
                            }
                        }, '📺'),
                        el('div', {}, __('Live TV Player', 'live-tv-streaming')),
                        el('div', {
                            style: {
                                fontSize: '12px',
                                opacity: '0.8',
                                marginTop: '4px'
                            }
                        }, category ? __('Category: ', 'live-tv-streaming') + category : __('All Channels', 'live-tv-streaming'))
                    )
                )
            ];
        },
        
        save: function() {
            // Return null as this is a dynamic block rendered server-side
            return null;
        }
    });
    
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.editor || window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
);