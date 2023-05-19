/**
 * WordPress dependencies
 */
import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

/**
 * Render the Account Status.
 */
export default function PluginSelection( { reportDetails, setReportDetails } ) {
	const [ plugins, setPlugins ] = useState( {} );
	const [ selectedPlugin, setSelectedPlugin ] = useState( '' );

	const onChange = ( value ) => {
		if ( value in plugins ) {
			setSelectedPlugin( value );
		}

		// TODO: Debounce, fetch aborts, etc.
		fetch( addQueryArgs(
			'https://api.wordpress.org/plugins/info/1.2/',
			{
				action: 'query_plugins',
				search: value
			}
		) )
		.then( ( response ) => response.json() )
		.then( ( data ) => {
			let newPlugins = {};

			for ( const item in data.plugins ) {
				if ( ! ( data.plugins[ item ].slug in plugins ) ) {
					newPlugins[ data.plugins[ item ].slug ] = data.plugins[ item ];
				}
			}

			if ( Object.keys( newPlugins ).length ) {
				setPlugins( plugins => ( { ...plugins, ...newPlugins } ) );

				if ( value in plugins ) {
					setSelectedPlugin( value );
				}
			}
		} );
	};

	const selectPlugin = () => {
		setReportDetails( { ...reportDetails, slug: selectedPlugin, plugin: plugins[ selectedPlugin ] } );
	};

	return (
		<>
			<datalist id="xhrQuery">
				{ Object.keys( plugins ).map( ( slug ) => {
					return ( <option key={ slug } value={ slug }>{ plugins[ slug ].name }</option> );
				} ) }
			</datalist>

			<p>
				<TextControl
					label="Plugin Name"
					placeholder="Enter plugin name"
					list="xhrQuery"
					onChange={ onChange }
				/>
			</p>

			<div class="next">
				<Button variant="primary" onClick={ selectPlugin }>Next</Button>
			</div>
		</>
	);
}