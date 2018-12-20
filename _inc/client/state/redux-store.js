/**
 * External dependencies
 */
import { createStore, applyMiddleware, compose } from 'redux';
import thunk from 'redux-thunk';
import { createHashHistory } from 'history';
import { routerMiddleware } from 'connected-react-router';

/**
 * Internal dependencies
 */
import createJetpackReducer from 'state/reducer';

export const history = createHashHistory();

export default createJetpackStore();

function createJetpackStore() {
	const finalCreateStore = compose(
		applyMiddleware( thunk ),
		applyMiddleware( routerMiddleware( history ) ),
		typeof window === 'object' && typeof window.__REDUX_DEVTOOLS_EXTENSION__ !== 'undefined'
			? window.__REDUX_DEVTOOLS_EXTENSION__()
			: f => f
	)( createStore );
	return finalCreateStore( createJetpackReducer( history ) );
}
