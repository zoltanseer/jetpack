/**
 * External dependencies
 */
import { applyMiddleware } from 'redux';
import flowRight from 'lodash/flowRight';
import { hashHistory } from 'react-router';
import thunk from 'redux-thunk';
import { routerMiddleware } from 'react-router-redux';

const history = routerMiddleware( hashHistory );

/**
 * Applies the custom middlewares used specifically in the Jetpack Admin Page.
 *
 * @param {Object} store Store Object.
 *
 * @return {Object} Update Store Object.
 */
function applyMiddlewares( store ) {
	const middlewares = [
		applyMiddleware( thunk ),
		applyMiddleware( history ),
	];

	let enhancedDispatch = () => {
		throw new Error(
			'Dispatching while constructing your middleware is not allowed. ' +
			'Other middleware would not be applied to this dispatch.'
		);
	};
	let chain = [];

	const middlewareAPI = {
		getState: store.getState,
		dispatch: ( ...args ) => enhancedDispatch( ...args ),
	};
	chain = middlewares.map( ( middleware ) => middleware( middlewareAPI ) );
	enhancedDispatch = flowRight( ...chain )( store.dispatch );

	store.dispatch = enhancedDispatch;
	return store;
}

export default applyMiddlewares;
