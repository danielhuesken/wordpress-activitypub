<?php
/**
 * Follow handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Notification;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

use function Activitypub\add_to_outbox;

/**
 * Handle Follow requests.
 */
class Follow {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_follow',
			array( self::class, 'handle_follow' )
		);

		\add_action(
			'activitypub_followers_post_follow',
			array( self::class, 'queue_accept' ),
			10,
			4
		);
	}

	/**
	 * Handle "Follow" requests.
	 *
	 * @param array $activity The activity object.
	 */
	public static function handle_follow( $activity ) {
		$user = Actors::get_by_resource( $activity['object'] );

		if ( ! $user || is_wp_error( $user ) ) {
			// If we can not find a user, we can not initiate a follow process.
			return;
		}

		$user_id = $user->get__id();

		// Save follower.
		$follower = Followers::add_follower(
			$user_id,
			$activity['actor']
		);

		/**
		 * Fires after a new follower has been added.
		 *
		 * @param string                                $actor    The URL of the actor (follower) who initiated the follow.
		 * @param array                                 $activity The complete activity data of the follow request.
		 * @param int                                   $user_id  The ID of the WordPress user being followed.
		 * @param \Activitypub\Model\Follower|\WP_Error $follower The Follower object containing the new follower's data.
		 */
		do_action( 'activitypub_followers_post_follow', $activity['actor'], $activity, $user_id, $follower );

		// Send notification.
		$notification = new Notification(
			'follow',
			$activity['actor'],
			$activity,
			$user_id
		);
		$notification->send();
	}

	/**
	 * Send Accept response.
	 *
	 * @param string                                $actor           The Actor URL.
	 * @param array                                 $activity_object The Activity object.
	 * @param int                                   $user_id         The ID of the WordPress User.
	 * @param \Activitypub\Model\Follower|\WP_Error $follower        The Follower object.
	 */
	public static function queue_accept( $actor, $activity_object, $user_id, $follower ) {
		if ( \is_wp_error( $follower ) ) {
			// Impossible to send a "Reject" because we can not get the Remote-Inbox.
			return;
		}

		// Only send minimal data.
		$activity_object = array_intersect_key(
			$activity_object,
			array_flip(
				array(
					'id',
					'type',
					'actor',
					'object',
				)
			)
		);

		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		$activity->set_object( $activity_object );
		$activity->set_to( array( $actor ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}
}
