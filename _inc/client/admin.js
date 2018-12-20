/**
 * External dependencies
 */
import ReactDOM from 'react-dom';
import React from 'react';
import { Provider } from 'react-redux';
import { Route, Switch } from 'react-router';
import { ConnectedRouter } from 'connected-react-router';
import assign from 'lodash/assign';

/**
 * Internal dependencies
 */
import accessibleFocus from 'lib/accessible-focus';
import store, { history } from 'state/redux-store';
import i18n from 'i18n-calypso';
import Main from 'main';
import * as actionTypes from 'state/action-types';

// Initialize the accessibile focus to allow styling specifically for keyboard navigation
accessibleFocus();

const Initial_State = window.Initial_State;

Initial_State.locale = JSON.parse( Initial_State.locale );

if ( 'undefined' !== typeof Initial_State.locale[ '' ] ) {
	Initial_State.locale[ '' ].localeSlug = Initial_State.localeSlug;

	// Overloading the toLocaleString method to use the set locale
	Number.prototype.realToLocaleString = Number.prototype.toLocaleString;

	Number.prototype.toLocaleString = function( locale, options ) {
		locale = locale || Initial_State.localeSlug;
		options = options || {};

		return this.realToLocaleString( locale, options );
	};
} else {
	Initial_State.locale = { '': { localeSlug: Initial_State.localeSlug } };
}

i18n.setLocale( Initial_State.locale );

// Add dispatch and actionTypes to the window object so we can use it from the browser's console
if ( 'undefined' !== typeof window && process.env.NODE_ENV === 'development' ) {
	assign( window, {
		actionTypes: actionTypes,
		dispatch: store.dispatch,
	} );
}

window.jQuery( window ).bind( 'hashchange', function( evt ) {
	evt;
	// console.log( 'hashchange ' + evt.originalEvent.oldURL + ' => ' + evt.originalEvent.newURL );
	// console.log( evt );
} );

render();

function render() {
	const container = document.getElementById( 'jp-plugin-container' );

	if ( container === null ) {
		return;
	}

	ReactDOM.render(
		<div>
			<Provider store={ store }>
				<ConnectedRouter history={ history }>
					<div>
						<Switch>
							<Route exact path="/" component={ Main } />
							<Route path="/dashboard" component={ Main } />
							<Route path="/my-plan" component={ Main } />
							<Route path="/plans" component={ Main } />
							<Route path="/settings" component={ Main } />
							<Route path="/discussion" component={ Main } />
							<Route path="/security" component={ Main } />
							<Route path="/traffic" component={ Main } />
							<Route path="/writing" component={ Main } />
							<Route path="/sharing" component={ Main } />
							<Route path="/wpbody-content" component={ Main } />
							<Route path="/wp-toolbar" component={ Main } />
							<Route path="/privacy" component={ Main } />
							<Route path="*" component={ Main } />
						</Switch>
					</div>
				</ConnectedRouter>
			</Provider>
		</div>,
		container
	);
}
