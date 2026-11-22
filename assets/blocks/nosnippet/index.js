/* KhabarSEO "Hidden from search snippets" block. Plain ES2017, no build step. */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var blockEditor = wp.blockEditor;
	var settings = window.khsNosnippet || {};
	var label = settings.label || 'Hidden from search snippets';

	wp.blocks.registerBlockType( 'khabar-seo/nosnippet', {
		edit: function () {
			var props = blockEditor.useBlockProps( {
				style: {
					borderInlineStart: '3px dashed currentColor',
					paddingInlineStart: '12px'
				}
			} );
			return el(
				'div',
				props,
				el( 'p', { style: { fontSize: '12px', opacity: 0.7, margin: '0 0 8px' } }, label ),
				el( blockEditor.InnerBlocks )
			);
		},
		save: function () {
			return el(
				'div',
				blockEditor.useBlockProps.save( { 'data-nosnippet': '' } ),
				el( blockEditor.InnerBlocks.Content )
			);
		}
	} );
}( window.wp ) );
