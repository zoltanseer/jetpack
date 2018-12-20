/**
 * External dependencies
 *
 * @format
 */

import React from 'react';
import PropTypes from 'prop-types';
import { translate as __ } from 'i18n-calypso';

/**
 * Internal dependencies
 */
import Discussion from 'discussion';
import Performance from 'performance';
import Privacy from 'privacy';
import SearchableModules from 'searchable-modules';
import Security from 'security';
import Sharing from 'sharing';
import Traffic from 'traffic';
import Writing from 'writing';

export default class SearchableSettings extends React.Component {
	static propTypes = {
		path: PropTypes.string.isRequired,
	};
	render() {
		const commonProps = {
			path: this.props.path,
			searchTerm: this.props.searchTerm,
			rewindStatus: this.props.rewindStatus,
		};

		return (
			<div className="jp-settings-container">
				<div className="jp-no-results">
					{ commonProps.searchTerm
						? __( 'No search results found for %(term)s', {
								args: {
									term: commonProps.searchTerm,
								},
						  } )
						: __( 'Enter a search term to find settings or close search.' ) }
				</div>
				<Discussion
					siteRawUrl={ this.props.siteRawUrl }
					active={ '/discussion' === this.props.path }
					{ ...commonProps }
				/>
				<Performance
					active={ '/performance' === this.props.path || '/settings' === this.props.path }
					{ ...commonProps }
				/>
				<Security
					siteAdminUrl={ this.props.siteAdminUrl }
					siteRawUrl={ this.props.siteRawUrl }
					active={ '/security' === this.props.path }
					{ ...commonProps }
				/>
				<Traffic
					siteRawUrl={ this.props.siteRawUrl }
					siteAdminUrl={ this.props.siteAdminUrl }
					active={ '/traffic' === this.props.path }
					{ ...commonProps }
				/>
				<Writing
					siteAdminUrl={ this.props.siteAdminUrl }
					active={ '/writing' === this.props.path }
					{ ...commonProps }
				/>
				<Sharing
					siteAdminUrl={ this.props.siteAdminUrl }
					active={ '/sharing' === this.props.path }
					{ ...commonProps }
				/>
				<Privacy active={ '/privacy' === this.props.path } { ...commonProps } />
				<SearchableModules searchTerm={ this.props.searchTerm } />
			</div>
		);
	}
}
