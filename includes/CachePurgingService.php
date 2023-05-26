<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\CacheBase;
use NewfoldLabs\WP\Module\Performance\Concerns\Purgeable;
use wpscholar\Url;

class CachePurgingService {

	/**
	 * Cache types.
	 *
	 * @var CacheBase[]
	 */
	public $cacheTypes = [];

	/**
	 * Constructor.
	 *
	 * @param  CacheBase[]  $cacheTypes
	 */
	public function __construct( array $cacheTypes ) {

		$this->cacheTypes = $cacheTypes;

		if ( $this->canPurge() ) {

			// Handle manual purge requests
			add_action( 'init', [ $this, 'manualPurgeRequest' ] );

			// Handle automatic purging
			add_action( 'transition_post_status', array( $this, 'onSavePost' ), 10, 3 );
			add_action( 'edit_terms', array( $this, 'onEditTerm' ) );
			add_action( 'comment_post', array( $this, 'onUpdateComment' ) );
			add_action( 'updated_option', array( $this, 'onUpdateOption' ), 10, 3 );
			add_action( 'wp_update_nav_menu', array( $this, 'purgeAll' ) );

		}
	}

	/**
	 * Check if the cache can be purged.
	 *
	 * @return bool
	 */
	public function canPurge() {
		foreach ( $this->cacheTypes as $instance ) {
			if ( array_key_exists( Purgeable::class, class_implements( $instance ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Listens for purge actions and handles based on type.
	 */
	public function manualPurgeRequest() {

		$purgeAll = Performance::PURGE_ALL;
		$purgeUrl = Performance::PURGE_URL;

		if ( ( isset( $_GET[ $purgeAll ] ) || isset( $_GET[ $purgeUrl ] ) ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification

			$url = new Url();
			$url->removeQueryVar( $purgeAll );
			$url->removeQueryVar( $purgeUrl );

			if ( isset( $_GET[ $purgeAll ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->purgeAll();
			} else {
				$this->purgeUrl( Url::stripQueryString( $url ) );
			}
			wp_safe_redirect(
				$url,
				302,
				'Newfold File Caching'
			);
			exit;
		}
	}

	/**
	 * Purge everything.
	 */
	public function purgeAll() {
		foreach ( $this->cacheTypes as $instance ) {
			if ( array_key_exists( Purgeable::class, class_implements( $instance ) ) ) {
				/**
				 * @var Purgeable $instance
				 */
				$instance->purgeAll();
			}
		}
	}

	/**
	 * Purge a specific URL.
	 *
	 * @param  string  $url  The URL to be purged.
	 */
	public function purgeUrl( $url ) {
		foreach ( $this->cacheTypes as $instance ) {
			if ( array_key_exists( Purgeable::class, class_implements( $instance ) ) ) {
				/**
				 * @var Purgeable $instance
				 */
				$instance->purgeUrl( $url );
			}
		}
	}

	/**
	 * Purge appropriate caches when a post is updated.
	 *
	 * @param  string  $oldStatus  The previous post status
	 * @param  string  $newStatus  The new post status
	 * @param  \WP_Post  $post  The post object of the edited or created post
	 */
	public function onSavePost( $oldStatus, $newStatus, \WP_Post $post ) {

		// Skip purging for non-public post types
		if ( ! get_post_type_object( $post->post_type )->public ) {
			return;
		}

		// Skip purging if the post wasn't public before and isn't now
		if ( 'publish' !== $oldStatus && 'publish' !== $newStatus ) {
			return;
		}

		// Purge post URL when post is updated.
		$permalink = get_permalink( $post );
		if ( $permalink ) {
			$this->purgeUrl( $permalink );
		}

		// Purge taxonomy term URLs for related terms.
		$taxonomies = get_post_taxonomies( $post );
		foreach ( $taxonomies as $taxonomy ) {
			if ( $this->isPublicTaxonomy( $taxonomy ) ) {
				$terms = get_the_terms( $post, $taxonomy );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_link = get_term_link( $term );
						$this->purgeUrl( $term_link );
					}
				}
			}
		}

		// Purge post type archive URL when post is updated.
		$post_type_archive = get_post_type_archive_link( $post->post_type );
		if ( $post_type_archive ) {
			$this->purgeUrl( $post_type_archive );
		}

		// Purge date archive URL when post is updated.
		$year_archive = get_year_link( (int) get_the_date( 'y', $post ) );
		$this->purgeUrl( $year_archive );

	}

	/**
	 * Purge taxonomy term URL when a term is updated.
	 *
	 * @param  int  $termId  Term ID
	 */
	public function onEditTerm( $termId ) {
		$url = get_term_link( $termId );
		if ( ! is_wp_error( $url ) ) {
			$this->purgeUrl( $url );
		}
	}

	/**
	 * Purge a single post when a comment is updated.
	 *
	 * @param  int  $commentId  ID of the comment.
	 */
	public function onUpdateComment( $commentId ) {
		$comment = get_comment( $commentId );
		if ( $comment && property_exists( $comment, 'comment_post_ID' ) ) {
			$postUrl = get_permalink( $comment->comment_post_ID );
			if ( $postUrl ) {
				$this->purgeUrl( $postUrl );
			}
		}
	}

	public function onUpdateOption( $option, $oldValue, $newValue ) {

		// No need to process if nothing was updated
		if ( $oldValue === $newValue ) {
			return false;
		}

		$exemptIfEquals = array(
			'active_plugins'    => true,
			'html_type'         => true,
			'fs_accounts'       => true,
			'rewrite_rules'     => true,
			'uninstall_plugins' => true,
			'wp_user_roles'     => true,
		);

		// If we have an exact match, we can just stop here.
		if ( array_key_exists( $option, $exemptIfEquals ) ) {
			return false;
		}

		$forceIfContains = array(
			'html',
			'css',
			'style',
			'query',
			'queries',
		);

		$exemptIfContains = array(
			'_active',
			'_activated',
			'_activation',
			'_attempts',
			'_available',
			'_blacklist',
			'_cache_validator',
			'_check_',
			'_checksum',
			'_config',
			'_count',
			'_dectivated',
			'_disable',
			'_enable',
			'_errors',
			'_hash',
			'_inactive',
			'_installed',
			'_key',
			'_last_',
			'_license',
			'_log_',
			'_mode',
			'_options',
			'_pageviews',
			'_redirects',
			'_rules',
			'_schedule',
			'_session',
			'_settings',
			'_shown',
			'_stats',
			'_status',
			'_statistics',
			'_supports',
			'_sync',
			'_task',
			'_time',
			'_token',
			'_traffic',
			'_transient',
			'_url_',
			'_version',
			'_views',
			'_visits',
			'_whitelist',
			'404s',
			'cron',
			'limit_login_',
			'nonce',
			'user_roles',
		);

		$force_purge = false;

		if ( ctype_upper( str_replace( array( '-', '_' ), '', $option ) ) ) {
			$option = strtolower( $option );
		}
		$option_name = '_' . toSnakeCase( toStudlyCase( $option ) ) . '_';

		foreach ( $forceIfContains as $slug ) {
			if ( false !== strpos( $option_name, $slug ) ) {
				$force_purge = true;
				break;
			}
		}

		if ( ! $force_purge ) {
			foreach ( $exemptIfContains as $slug ) {
				if ( false !== strpos( $option_name, $slug ) ) {
					return false;
				}
			}
		}

		$this->purgeAll();

		return true;

	}

	/**
	 * Checks if a taxonomy is public.
	 *
	 * @param  string  $taxonomy  Taxonomy name.
	 *
	 * @return boolean
	 */
	protected function isPublicTaxonomy( $taxonomy ) {
		$public          = false;
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( $taxonomy_object && isset( $taxonomy_object->public ) ) {
			$public = $taxonomy_object->public;
		}

		return $public;
	}

}
