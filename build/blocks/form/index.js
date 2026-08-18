/**
 * BlueFairy Forms — Gutenberg block editor script.
 *
 * Registers the block type with a minimal edit component.
 * The actual form output is server-side rendered (render.php).
 */
(function () {
	'use strict';

	var el              = window.wp.element.createElement;
	var registerBlock   = window.wp.blocks.registerBlockType;
	var InspectorControls = window.wp.blockEditor.InspectorControls;
	var PanelBody       = window.wp.components.PanelBody;
	var TextControl     = window.wp.components.TextControl;
	var Placeholder     = window.wp.components.Placeholder;
	var Disabled        = window.wp.components.Disabled;
	var ServerSideRender = window.wp.serverSideRender;
	var __              = window.wp.i18n.__;

	registerBlock( 'bluefairy/forms', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var formId = attributes.formId;

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Form Settings', 'bluefairy-forms-lite' ), initialOpen: true },
					el( TextControl, {
						label: __( 'Form ID', 'bluefairy-forms-lite' ),
						help: __( 'Enter the numeric ID of the BlueFairy Form to embed. Find IDs at Tools → BlueFairy Forms.', 'bluefairy-forms-lite' ),
						type: 'number',
						value: formId || '',
						min: 1,
						onChange: function ( val ) {
							setAttributes( { formId: parseInt( val, 10 ) || 0 } );
						},
					} )
				)
			);

			var preview;
			if ( formId > 0 ) {
				preview = el(
					Disabled,
					null,
					el( ServerSideRender, {
						block: 'bluefairy/forms',
						attributes: attributes,
					} )
				);
			} else {
				preview = el(
					Placeholder,
					{
						icon: 'feedback',
						label: __( 'BlueFairy Form', 'bluefairy-forms-lite' ),
						instructions: __( 'Enter a Form ID in the block settings panel on the right to embed a form.', 'bluefairy-forms-lite' ),
					}
				);
			}

			return el( window.wp.element.Fragment, null, inspector, preview );
		},
	} );
}());
