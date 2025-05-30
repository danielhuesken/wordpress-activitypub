<?php
/**
 * Test file for Activitypub User.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Model\User;
use Activitypub\Move;

/**
 * Test class for Activitypub User.
 *
 * @coversDefaultClass User
 */
class Test_User extends \WP_UnitTestCase {

	/**
	 * Test the Blog constructor.
	 *
	 * @covers ::__construct
	 */
	public function test___construct() {
		$old_domain = home_url( '/' );
		$new_domain = 'http://newdomain.com';
		\remove_filter( 'option_home', '_config_wp_home' );

		\add_filter( 'update_option_home', array( Move::class, 'change_domain' ), 10, 2 );
		\update_option( 'home', $new_domain );
		\remove_filter( 'update_option_home', array( Move::class, 'change_domain' ) );

		// New domain is set.
		$this->assertSame( 'http://newdomain.com/?author=1', ( new User( 1 ) )->get_id() );

		// Set up the old host.
		$_SERVER['HTTP_HOST'] = \wp_parse_url( $old_domain, PHP_URL_HOST );

		// User now returns old user actor.
		\add_action( 'activitypub_construct_model_actor', array( Move::class, 'maybe_initiate_old_user' ) );
		$user = ( new User( 1 ) )->to_array();
		$this->assertSame( add_query_arg( 'author', 1, $old_domain ), $user['id'] );
		\remove_action( 'activitypub_construct_model_actor', array( Move::class, 'maybe_initiate_old_user' ) );

		// Clean up.
		\delete_option( 'activitypub_old_host' );
		\delete_option( 'activitypub_blog_user_old_host_data' );
		\update_option( 'home', $old_domain );
		\add_filter( 'option_home', '_config_wp_home' );
	}

	/**
	 * Test the activitypub capability.
	 */
	public function test_activitypub_cap() {
		$userdata = array(
			'user_email' => 'subscriber@example.com',
			'first_name' => 'Max',
			'last_name'  => 'Mustermann',
			'user_login' => 'subscriber',
			'user_pass'  => 'subscriber',
			'role'       => 'subscriber',
		);

		$user_id = wp_insert_user( $userdata );
		$can     = user_can( $user_id, 'activitypub' );

		$this->assertFalse( $can );

		$userdata = array(
			'user_email' => 'editor@example.com',
			'first_name' => 'Max',
			'last_name'  => 'Mustermann',
			'user_login' => 'editor',
			'user_pass'  => 'editor',
			'role'       => 'editor',
		);

		$user_id = wp_insert_user( $userdata );
		$can     = user_can( $user_id, 'activitypub' );

		$this->assertTrue( $can );
	}

	/**
	 * Test that on attachment pages the user avatar is returned.
	 *
	 * @ticket https://github.com/Automattic/wordpress-activitypub/issues/1459
	 * @covers ::get_icon
	 */
	public function test_icon() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = User::from_wp_user( $user_id );

		// Add attachment.
		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/assets/test.jpg' );

		// Navigate to attachment page.
		$this->go_to( get_attachment_link( $attachment_id ) );

		$icon = $user->get_icon();

		$this->assertArrayHasKey( 'url', $icon );
		$this->assertNotSame( wp_get_attachment_url( $attachment_id ), $icon['url'] );
	}

	/**
	 * Tests the get_moved_to method.
	 *
	 * @covers ::get_moved_to
	 */
	public function test_get_moved_to() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = User::from_wp_user( $user_id );

		// No value => should not even be set.
		$this->assertArrayNotHasKey( 'movedTo', $user->to_array( false ) );

		// Set movedTo.
		\update_user_option( $user_id, 'activitypub_moved_to', 'https://example.com' );

		$user = User::from_wp_user( $user_id )->to_array( false );
		$this->assertArrayHasKey( 'movedTo', $user );
		$this->assertSame( 'https://example.com', $user['movedTo'] );
	}
}
