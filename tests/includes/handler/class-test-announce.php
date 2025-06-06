<?php
/**
 * Test file for Activitypub Announce Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Announce;

/**
 * Test class for Activitypub Announce Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Announce
 */
class Test_Announce extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * User URL.
	 *
	 * @var string
	 */
	public $user_url;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Post permalink.
	 *
	 * @var string
	 */
	public $post_permalink;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id  = 1;
		$authordata     = \get_userdata( $this->user_id );
		$this->user_url = $authordata->user_url;

		$this->post_id        = \wp_insert_post(
			array(
				'post_author'  => $this->user_id,
				'post_content' => 'test',
			)
		);
		$this->post_permalink = \get_permalink( $this->post_id );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	/**
	 * Get remote metadata by actor.
	 *
	 * @param string $value The value.
	 * @param string $actor The actor.
	 * @return array The metadata.
	 */
	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => $actor,
			'id'   => 'http://example.org/users/example',
		);
	}

	/**
	 * Create a test object.
	 *
	 * @return array The test object.
	 */
	public function create_test_object() {
		return array(
			'actor'  => $this->user_url,
			'type'   => 'Announce',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $this->user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $this->post_permalink,
		);
	}

	/**
	 * Test handle announce.
	 *
	 * @covers ::handle_announce
	 */
	public function test_handle_announce() {
		$object = $this->create_test_object();

		Announce::handle_announce( $object, $this->user_id );

		$args = array(
			'type'    => 'repost',
			'post_id' => $this->post_id,
		);

		$query  = new \WP_Comment_Query( $args );
		$result = $query->comments;

		$this->assertInstanceOf( 'WP_Comment', $result[0] );
	}

	/**
	 * Test handle announces.
	 *
	 * @covers ::handle_announces
	 *
	 * @dataProvider data_handle_announces
	 *
	 * @param array  $announce  The announce.
	 * @param int    $recursion The recursion.
	 * @param string $message   The message.
	 */
	public function test_handle_announces( $announce, $recursion, $message ) {
		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_inbox', array( $inbox_action, 'action' ) );

		Announce::handle_announce( $announce, $this->user_id );

		$this->assertEquals( $recursion, $inbox_action->get_call_count(), $message );
	}

	/**
	 * Test handle announce with invalid object.
	 *
	 * @covers ::handle_announce
	 */
	public function test_maybe_save_announce() {
		$activity = array(
			'actor'  => $this->user_url,
			'type'   => 'Announce',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $this->user_url ),
			'object' => $this->post_permalink,
		);

		// Set up mock action.
		$inbox_action = new \MockAction();
		\add_action( 'activitypub_handled_announce', array( $inbox_action, 'action' ) );

		Announce::maybe_save_announce( $activity, $this->user_id );
		Announce::maybe_save_announce( $activity, $this->user_id );

		$activity['id'] = 'https://example.com/id/' . microtime( true );
		Announce::maybe_save_announce( $activity, $this->user_id );

		$this->assertEquals( 2, $inbox_action->get_call_count() );
	}

	/**
	 * Data provider for test_handle_announces.
	 *
	 * @return array The data provider.
	 */
	public function data_handle_announces() {
		return array(
			array(
				'announce'  => $this->create_test_object(),
				'recursion' => 0,
				'message'   => 'Simple Announce of an URL.',
			),
			array(
				'announce'  => array(
					'actor'  => $this->user_url,
					'type'   => 'Announce',
					'id'     => 'https://example.com/id/' . microtime( true ),
					'to'     => array( $this->user_url ),
					'object' => array(
						'actor'   => $this->user_url,
						'type'    => 'Note',
						'id'      => $this->post_permalink,
						'to'      => array( $this->user_url ),
						'content' => 'text',
					),
				),
				'recursion' => 0,
				'message'   => 'Announce of a Note-Object.',
			),
			array(
				'announce'  => array(
					'actor'  => $this->user_url,
					'type'   => 'Announce',
					'id'     => 'https://example.com/id/' . microtime( true ),
					'to'     => array( $this->user_url ),
					'object' => $this->create_test_object(),
				),
				'recursion' => 1,
				'message'   => 'Announce of an Announce-Object.',
			),
		);
	}
}
