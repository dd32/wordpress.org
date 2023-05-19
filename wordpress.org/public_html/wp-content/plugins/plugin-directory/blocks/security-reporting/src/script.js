/**
 * WordPress dependencies
 */
import {
	StrictMode,
	createContext,
	useCallback,
	useEffect,
	useState,
	createRoot,
} from '@wordpress/element';
import { Icon, chevronLeft } from '@wordpress/icons';
import { Card, CardHeader, CardBody, Spinner } from '@wordpress/components';

import PluginSelection from './components/plugin-selection';

/**
 * Internal dependencies
 */

export const GlobalContext = createContext( null );

window.addEventListener( 'DOMContentLoaded', render );

/**
 * Render the initial view into the DOM.
 */
function render() {
	const wrapper = document.querySelector( '.wp-block-plugin-directory-security-reporting' );
	if ( ! wrapper ) {
		return;
	}

	const root = createRoot( wrapper );

	root.render(
		<StrictMode>
			<Main/>
		</StrictMode>
	);
}

/**
 * Render the correct component based on the URL.
 */
function Main() {
	const [ reportDetails, setReportDetails ] = useState( false );
	const localStorageKey = 'plugin-security-report';
	let currentUrl = new URL( document.location.href );

	useEffect( () => {
		const data = window.localStorage.getItem( localStorageKey );
		if ( data ) {
			console.log( data );
			setReportDetails( JSON.parse( data ) );
		}
	}, [] );

	useEffect( () => {
		if ( reportDetails ) {
			window.localStorage.setItem( localStorageKey, JSON.stringify( reportDetails ) );

		}
	}, [ reportDetails ] );

	let heading       = 'Security Reporting';
	let screenContent = (
		<p>
			<Spinner />
			Security reporting is loading...
		</p>
	);

	if ( ! reportDetails?.slug ) {
		heading       = 'Select a plugin';
		screenContent = <PluginSelection reportDetails={ reportDetails } setReportDetails={ setReportDetails } />;
	} else if ( reportDetails.plugin ) {
		screenContent = (
			<>
				<p>
					<strong>{ reportDetails.plugin.name }</strong>
					<em>{ reportDetails.slug }</em>
				</p>
				<Spinner />
			</>
		);
	}

	return (
		<GlobalContext.Provider value>
			<Card>
				<CardHeader size="xSmall">
					<h2>{ heading }</h2>
				</CardHeader>

				<CardBody>
					{ screenContent }
				</CardBody>
			</Card>
		</GlobalContext.Provider>
	);
}
