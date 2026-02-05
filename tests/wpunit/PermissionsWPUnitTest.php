<?php

namespace NewfoldLabs\WP\Module\Performance;

/**
 * Permissions wpunit tests.
 *
 * @coversDefaultClass \NewfoldLabs\WP\Module\Performance\Permissions
 */
class PermissionsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Permission constants are defined.
	 *
	 * @return void
	 */
	public function test_permission_constants() {
		$this->assertSame( 'manage_options', Permissions::ADMIN );
		$this->assertSame( 'upload_files', Permissions::UPLOAD_FILES );
		$this->assertSame( 'edit_posts', Permissions::EDIT_POSTS );
		$this->assertSame( 'manage_media_library', Permissions::MANAGE_MEDIA_LIBRARY );
	}

	/**
	 * Rest_is_authorized_admin returns false when not logged in.
	 *
	 * @return void
	 */
	public function test_rest_is_authorized_admin_when_logged_out() {
		wp_set_current_user( 0 );
		$this->assertFalse( Permissions::rest_is_authorized_admin() );
	}

	/**
	 * Rest_is_authorized_admin returns true for administrator.
	 *
	 * @return void
	 */
	public function test_rest_is_authorized_admin_when_administrator() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( Permissions::rest_is_authorized_admin() );
	}

	/**
	 * Rest_can_upload_media returns true for administrator.
	 *
	 * @return void
	 */
	public function test_rest_can_upload_media_for_administrator() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( Permissions::rest_can_upload_media() );
	}
}
