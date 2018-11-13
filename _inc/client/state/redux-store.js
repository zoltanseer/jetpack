/**
 * External dependencies
 */
import { registerStore } from '@wordpress/data';

/**
 * Internal dependencies
 */
import applyMiddlewares from 'state/middlewares';
import reducer from 'state/reducer';

// export default createJetpackStore();
const jetpackStore = registerStore( 'jetpack', {
	reducer
} );

export default applyMiddlewares( jetpackStore );
