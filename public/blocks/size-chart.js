( function( blocks, element, editor, components, i18n ) {
const { __ } = i18n;
const el = element.createElement;
const { InspectorControls } = editor;
const { PanelBody, TextControl } = components;

blocks.registerBlockType( 'pifc/size-chart', {
title: __( 'Printful Size Chart', 'printful-integration-for-fluentcart' ),
icon: 'table-col-before',
category: 'widgets',
attributes: {
productId: { type: 'number' },
printfulProductId: { type: 'string' },
templateProductId: { type: 'string' },
},
supports: {
align: [ 'wide', 'full' ],
},
edit: function( props ) {
return [
el(
InspectorControls,
null,
el(
PanelBody,
{ title: __( 'Source', 'printful-integration-for-fluentcart' ), initialOpen: true },
el( TextControl, {
label: __( 'FluentCart product ID (optional)', 'printful-integration-for-fluentcart' ),
value: props.attributes.productId || '',
onChange: function( value ) {
props.setAttributes( { productId: value ? parseInt( value, 10 ) : undefined } );
},
} ),
el( TextControl, {
label: __( 'Printful product ID', 'printful-integration-for-fluentcart' ),
value: props.attributes.printfulProductId || '',
onChange: function( value ) {
props.setAttributes( { printfulProductId: value } );
},
} ),
el( TextControl, {
label: __( 'Template product ID (optional)', 'printful-integration-for-fluentcart' ),
value: props.attributes.templateProductId || '',
onChange: function( value ) {
props.setAttributes( { templateProductId: value } );
},
} )
)
),
el( 'p', {}, __( 'Size chart is rendered on the front-end using Printful cache data.', 'printful-integration-for-fluentcart' ) )
];
},
save: function() {
return null;
},
} );
} )( window.wp.blocks, window.wp.element, window.wp.editor || window.wp.blockEditor, window.wp.components, window.wp.i18n );
