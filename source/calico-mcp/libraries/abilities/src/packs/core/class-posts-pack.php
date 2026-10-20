<?php
/**
 * Core Posts ability pack: definitions for the calicomcp/post-* abilities.
 *
 * @package Mosmcp_Abilities_Library
 */

namespace CalicoMCP\Abilities\Packs\Core;

use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
use CalicoMCP\Abilities\Packs\Core\Support\Core_Schema as Schema;
use CalicoMCP\Abilities\Packs\Core\Support\Post_Duplicator;
use CalicoMCP\Abilities\Support\Html_Policy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This library authors every translatable string under its own fixed text domain
 * ('calicomcp-abilities'). The host plugin remaps them to its own text domain at
 * runtime via Abilities_Library::init(). The domain therefore intentionally will
 * not match any host plugin's slug, so the text-domain-mismatch check is disabled
 * for this file (the library's phpcs.xml.dist allows the domain on the CLI; this
 * directive covers IDE and Plugin Check runs that don't load that ruleset).
 */
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Class Posts_Pack
 *
 * Declares the post abilities and their governed contract (capability gate, MCP
 * annotations, schema). Execute logic lives in Posts_Provider and is preserved
 * from the reviewed source.
 */
class Posts_Pack extends Ability_Pack {

	/**
	 * Ability category slug.
	 */
	const CATEGORY = 'calicomcp-posts';

	/**
	 * Ability category for post abilities.
	 *
	 * @return array Category definition.
	 */
	public function category() {
		return array(
			'slug'        => self::CATEGORY,
			'label'       => __( 'Posts', 'calicomcp-abilities' ),
			'description' => __( 'Create, edit, publish, organize, and manage blog posts.', 'calicomcp-abilities' ),
		);
	}

	/**
	 * The post abilities.
	 *
	 * @return Ability[]
	 */
	public function abilities() {
		return array(
			$this->create_draft(),
			$this->duplicate(),
			$this->update(),
			$this->update_own(),
			$this->set_featured_image(),
			$this->template_get(),
			$this->template_set(),
			$this->compare_meta(),
			$this->publish(),
			$this->unpublish(),
			$this->schedule(),
			$this->set_private(),
			$this->set_pending(),
			$this->trash(),
			$this->restore(),
			$this->delete_permanently(),
			$this->assign_category(),
			$this->assign_tag(),
			$this->remove_category(),
			$this->remove_tag(),
			$this->find(),
			$this->get(),
			$this->list_all(),
			$this->list_drafts(),
			$this->list_own_drafts(),
			$this->list_pending(),
			$this->list_private(),
			$this->list_published(),
			$this->list_scheduled(),
			$this->list_trash(),
			$this->list_untagged(),
		);
	}

	/**
	 * Defines the calicomcp/post-create-draft ability.
	 *
	 * @return Ability
	 */
	private function create_draft() {
		return new Ability(
			'calicomcp/post-create-draft',
			array(
				'label'         => __( 'Create Draft', 'calicomcp-abilities' ),
				'description'   => __( 'Creates a new post as a draft, authored by the current user. The post is not published.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( false, false, false, false ),
				'execute'       => array( Posts_Provider::class, 'create_draft' ),
				'input_schema'  => Schema::object(
					array(
						'title'   => Schema::str( __( 'The title of the new draft.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
						'content' => Schema::str( __( 'Optional content/body of the new draft.', 'calicomcp-abilities' ) . ' ' . Html_Policy::rule_summary(), array( 'default' => '' ) ),
						'excerpt' => Schema::str( __( 'Optional excerpt for the new draft.', 'calicomcp-abilities' ), array( 'default' => '' ) ),
						'slug'    => Schema::str( __( 'New URL slug. Omit to leave the address unchanged. Reduced to a URL-safe form, and a suffix is added if it is already taken.', 'calicomcp-abilities' ) ),
					),
					array( 'title' )
				),
				'output_schema' => Schema::object(
					array(
						'id'        => Schema::int(),
						'title'     => Schema::str(),
						'status'    => Schema::str(),
						'slug'      => Schema::str( __( 'The URL slug as stored, which may differ from a requested one.', 'calicomcp-abilities' ) ),
						'edit_url'  => Schema::str(),
						'view_url'  => Schema::str(),
						'post_type' => Schema::str(),
						'warnings'  => Schema::warnings( __( 'Notes about the save, such as attributes that could not be kept or content that WordPress changed while saving.', 'calicomcp-abilities' ) ),
					),
					array( 'id', 'title', 'status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-duplicate ability.
	 *
	 * A post's design lives in its metadata, not its content: an Elementor layout,
	 * the theme's per-post sidebar and width settings, ACF fields and SEO fields
	 * are all meta rows. Creating a post from scratch therefore produces correct
	 * text with none of the design. Duplicating an existing post that already
	 * looks right is the reliable way to author a new one that matches.
	 *
	 * @return Ability
	 */
	private function duplicate() {
		return new Ability(
			'calicomcp/post-duplicate',
			array(
				'label'            => __( 'Duplicate Post', 'calicomcp-abilities' ),
				'description'      => __( 'Copies an existing post as a new draft, including its Elementor layout, theme display settings, custom fields, SEO fields, categories, tags and featured image. Use this to create a post that matches the design of existing ones, then edit the copy. The duplicate is authored by the current user and is created as a draft unless another status is given.', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'edit_post',
				'cap_args'         => self::source_id_args(),
				'permission_extra' => self::can_create_posts(),
				'annotations'      => self::annotations( false, false, false, false ),
				'execute'          => array( Posts_Provider::class, 'duplicate' ),
				'input_schema'     => Schema::object(
					array(
						'source_id'         => Schema::int( __( 'The ID of the post to duplicate. Its layout and settings become the new post\'s.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'title'             => Schema::str( __( 'Title for the duplicate. Omit to use the original title followed by "(copy)".', 'calicomcp-abilities' ) ),
						'status'            => Schema::str(
							__( 'Status for the duplicate. Defaults to draft so the result can be reviewed before publishing. Publishing requires the publish capability.', 'calicomcp-abilities' ),
							array(
								'enum'    => Post_Duplicator::ALLOWED_STATUSES,
								'default' => 'draft',
							)
						),
						'include_content'   => Schema::boolean( __( 'Whether to copy the post body. Set false to keep the layout and settings but start with empty content. Defaults to true.', 'calicomcp-abilities' ), array( 'default' => true ) ),
						'expected_modified' => Schema::str( __( 'Optional. The "modified" timestamp from a previous read of the source post. The duplicate is refused if the source changed since then.', 'calicomcp-abilities' ) ),
					),
					array( 'source_id' )
				),
				'output_schema'    => Schema::object(
					array(
						'new_id'    => Schema::int( __( 'ID of the newly created duplicate.', 'calicomcp-abilities' ) ),
						'source_id' => Schema::int(),
						'title'     => Schema::str(),
						'status'    => Schema::str(),
						'edit_url'  => Schema::str(),
						'view_url'  => Schema::str(),
						'copied'    => Schema::object(
							array(
								'meta_rows'       => Schema::int( __( 'Number of metadata rows copied.', 'calicomcp-abilities' ) ),
								'taxonomies'      => Schema::arr( array( 'type' => 'string' ), __( 'Taxonomies whose terms were copied.', 'calicomcp-abilities' ) ),
								'thumbnail_id'    => Schema::int( __( 'Featured image attachment ID on the duplicate, or 0.', 'calicomcp-abilities' ) ),
								'has_elementor'   => Schema::boolean( __( 'Whether the duplicate carries an Elementor layout.', 'calicomcp-abilities' ) ),
								'elementor_bytes' => Schema::int(),
							)
						),
						'skipped'   => Schema::arr(
							Schema::object(
								array(
									'key'    => Schema::str(),
									'reason' => Schema::str(),
								)
							),
							__( 'Metadata deliberately not copied, with the reason for each.', 'calicomcp-abilities' )
						),
						'rewritten' => Schema::arr(
							Schema::object(
								array(
									'key'    => Schema::str(),
									'reason' => Schema::str(),
								)
							),
							__( 'Metadata cleared rather than copied because the value is unique to the original.', 'calicomcp-abilities' )
						),
						'warnings'  => Schema::arr(
							Schema::object(
								array(
									'code'    => Schema::str(),
									'message' => Schema::str(),
									'context' => Schema::str(),
								)
							),
							__( 'Non-fatal issues encountered while duplicating.', 'calicomcp-abilities' )
						),
					),
					array( 'new_id', 'source_id', 'title', 'status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-update ability.
	 *
	 * The companion to post-update-own, for any post the caller may edit rather
	 * than only their own. Object-level edit_post is what separates the two, so a
	 * Contributor still cannot reach another author's work through this.
	 *
	 * @return Ability
	 */
	private function update() {
		return new Ability(
			'calicomcp/post-update',
			array(
				'label'            => __( 'Update Post', 'calicomcp-abilities' ),
				'description'      => __( 'Edits the title, content and/or excerpt of any post the current user has permission to edit, including posts written by other authors. Does not change the post status. If the post is built with Elementor, what visitors read comes from the Elementor layout rather than from this content, so change the text on the Elementor elements instead. This replaces a whole field with the text you supply. To change only part of the text, use calicomcp/content-replace-in-post instead: it is far cheaper on a long page and cannot lose formatting, shortcodes or block markup the way rewriting the whole field can.', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'edit_post',
				'cap_args'         => self::id_args(),
				'permission_extra' => self::can_edit_post(),
				'annotations'      => self::annotations( false, false, false, true ),
				'execute'          => array( Posts_Provider::class, 'update' ),
				'input_schema'     => Schema::object(
					array(
						'id'      => Schema::int( __( 'The ID of the post to update.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'title'   => Schema::str( __( 'New title. Omit to keep the current title.', 'calicomcp-abilities' ) ),
						'content' => Schema::str( __( 'New content. Omit to keep the current content.', 'calicomcp-abilities' ) . ' ' . Html_Policy::rule_summary() ),
						'excerpt' => Schema::str( __( 'New excerpt. Omit to keep the current excerpt; pass an empty string to clear it.', 'calicomcp-abilities' ) ),
						'slug'    => Schema::str( __( 'New URL slug. Omit to leave the address unchanged. Reduced to a URL-safe form, and a suffix is added if it is already taken.', 'calicomcp-abilities' ) ),
					),
					array( 'id' )
				),
				'output_schema'    => Schema::object(
					array(
						'id'        => Schema::int(),
						'title'     => Schema::str(),
						'status'    => Schema::str(),
						'slug'      => Schema::str( __( 'The URL slug as stored, which may differ from a requested one.', 'calicomcp-abilities' ) ),
						'modified'  => Schema::str(),
						'updated'   => Schema::arr( array( 'type' => 'string' ), __( 'Fields that were changed.', 'calicomcp-abilities' ) ),
						'warnings'  => Schema::warnings( __( 'Notes about the update, such as the content not being visible because Elementor renders this post.', 'calicomcp-abilities' ) ),
						'edit_url'  => Schema::str(),
						'view_url'  => Schema::str(),
						'post_type' => Schema::str(),
					),
					array( 'id', 'title', 'status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-set-featured-image ability.
	 *
	 * @return Ability
	 */
	private function set_featured_image() {
		return new Ability(
			'calicomcp/post-set-featured-image',
			array(
				'label'            => __( 'Set Post Featured Image', 'calicomcp-abilities' ),
				'description'      => __( 'Sets the featured image of a post to an existing media library image, or removes it by passing 0. Many themes render the featured image as the post banner.', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'edit_post',
				'cap_args'         => self::id_args(),
				'permission_extra' => self::can_edit_post(),
				'annotations'      => self::annotations( false, false, true, true ),
				'execute'          => array( Posts_Provider::class, 'set_featured_image' ),
				'input_schema'     => Schema::object(
					array(
						'id'            => Schema::int( __( 'The ID of the post to update.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'attachment_id' => Schema::int( __( 'Media library attachment ID of the image to use, or 0 to remove the current featured image.', 'calicomcp-abilities' ), array( 'minimum' => 0 ) ),
					),
					array( 'id', 'attachment_id' )
				),
				'output_schema'    => Schema::featured_image_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-template-get ability.
	 *
	 * @return Ability
	 */
	private function template_get() {
		return new Ability(
			'calicomcp/post-template-get',
			array(
				'label'         => __( 'Get Post Template And Display Settings', 'calicomcp-abilities' ),
				'description'   => __( 'Reports which page template a post uses, whether Elementor controls its layout, and the per-post theme settings (sidebar, content width, title visibility) that decide how it renders. Use this to find out why one post looks different from another.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Posts_Provider::class, 'template_get' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the post to inspect.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => Schema::template_get_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-template-set ability.
	 *
	 * @return Ability
	 */
	private function template_set() {
		return new Ability(
			'calicomcp/post-template-set',
			array(
				'label'            => __( 'Set Post Template', 'calicomcp-abilities' ),
				'description'      => __( 'Assigns a page template to a post, for example one of Elementor\'s full-width or canvas templates. Only templates the active theme and its plugins actually register are accepted, so a mistyped slug is refused instead of silently falling back to the default.', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'edit_post',
				'cap_args'         => self::id_args(),
				'permission_extra' => self::can_edit_post(),
				'annotations'      => self::annotations( false, false, true, true ),
				'execute'          => array( Posts_Provider::class, 'template_set' ),
				'input_schema'     => Schema::object(
					array(
						'id'            => Schema::int( __( 'The ID of the post to update.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'page_template' => Schema::str( __( 'Template slug to assign, or "default" for the theme default. Read the post first to list the slugs available on this site.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
					),
					array( 'id', 'page_template' )
				),
				'output_schema'    => Schema::template_set_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-compare-meta ability.
	 *
	 * @return Ability
	 */
	private function compare_meta() {
		return new Ability(
			'calicomcp/post-compare-meta',
			array(
				'label'         => __( 'Compare Post Settings', 'calicomcp-abilities' ),
				'description'   => __( 'Compares all stored settings of two posts or pages and reports which are missing or different, highlighting the ones that affect layout. Use this when a new post does not look like an existing one, to find out exactly which settings it is missing.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::compare_args(),
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Posts_Provider::class, 'compare_meta' ),
				'input_schema'  => Schema::object(
					array(
						'post_a'         => Schema::int( __( 'ID of the first post or page — typically the one that looks correct.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'post_b'         => Schema::int( __( 'ID of the second post or page — typically the one that looks wrong.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'include_values' => Schema::boolean( __( 'Whether to include the stored values alongside the key names. Large values are replaced with their size. Defaults to true.', 'calicomcp-abilities' ), array( 'default' => true ) ),
					),
					array( 'post_a', 'post_b' )
				),
				'output_schema' => Schema::meta_compare_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-update-own ability.
	 *
	 * @return Ability
	 */
	private function update_own() {
		return new Ability(
			'calicomcp/post-update-own',
			array(
				'label'         => __( 'Update Own Post', 'calicomcp-abilities' ),
				'description'   => __( 'Edits the title, content and/or excerpt of a post authored by the current user. Cannot edit posts written by other users. Only the fields you pass are changed; the post keeps its current status. If the post is built with Elementor, what visitors read comes from the Elementor layout rather than from this content, so change the text on the Elementor elements instead. This replaces a whole field with the text you supply. To change only part of the text, use calicomcp/content-replace-in-post instead: it is far cheaper on a long page and cannot lose formatting, shortcodes or block markup the way rewriting the whole field can.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, false, true ),
				'execute'       => array( Posts_Provider::class, 'update_own' ),
				'input_schema'  => Schema::object(
					array(
						'id'      => Schema::int( __( 'The ID of the post to update.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'title'   => Schema::str( __( 'New title for the post. Omit to keep the current title.', 'calicomcp-abilities' ) ),
						'content' => Schema::str( __( 'New content for the post. Omit to keep the current content.', 'calicomcp-abilities' ) . ' ' . Html_Policy::rule_summary() ),
						'excerpt' => Schema::str( __( 'New excerpt for the post. Omit to keep the current excerpt; pass an empty string to clear it.', 'calicomcp-abilities' ) ),
						'slug'    => Schema::str( __( 'New URL slug. Omit to leave the address unchanged. Reduced to a URL-safe form, and a suffix is added if it is already taken.', 'calicomcp-abilities' ) ),
					),
					array( 'id' )
				),
				'output_schema' => Schema::object(
					array(
						'id'        => Schema::int(),
						'title'     => Schema::str(),
						'status'    => Schema::str(),
						'slug'      => Schema::str( __( 'The URL slug as stored, which may differ from a requested one.', 'calicomcp-abilities' ) ),
						'modified'  => Schema::str(),
						'updated'   => Schema::arr( array( 'type' => 'string' ), __( 'Fields that were changed.', 'calicomcp-abilities' ) ),
						'warnings'  => Schema::warnings( __( 'Notes about the update, such as the content not being visible because Elementor renders this post.', 'calicomcp-abilities' ) ),
						'edit_url'  => Schema::str(),
						'view_url'  => Schema::str(),
						'post_type' => Schema::str(),
					),
					array( 'id', 'title', 'status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-publish ability.
	 *
	 * @return Ability
	 */
	private function publish() {
		return new Ability(
			'calicomcp/post-publish',
			array(
				'label'            => __( 'Publish Post', 'calicomcp-abilities' ),
				'description'      => __( 'Changes a draft or pending post to published, making it publicly visible immediately.', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'publish_posts',
				'permission_extra' => self::can_edit_post(),
				'annotations'      => self::annotations( false, false, false, true ),
				'execute'          => array( Posts_Provider::class, 'publish' ),
				'input_schema'     => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the draft or pending post to publish.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema'    => Schema::object(
					array(
						'id'              => Schema::int(),
						'title'           => Schema::str(),
						'previous_status' => Schema::str(),
						'new_status'      => Schema::str(),
						'link'            => Schema::str(),
						'edit_url'        => Schema::str(),
					),
					array( 'id', 'title', 'previous_status', 'new_status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-unpublish ability.
	 *
	 * @return Ability
	 */
	private function unpublish() {
		return new Ability(
			'calicomcp/post-unpublish',
			array(
				'label'         => __( 'Unpublish Post', 'calicomcp-abilities' ),
				'description'   => __( 'Reverts a published post back to draft status, removing it from public view.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, false, true ),
				'execute'       => array( Posts_Provider::class, 'unpublish' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the published post to revert to draft.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => self::status_change_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-schedule ability.
	 *
	 * @return Ability
	 */
	private function schedule() {
		return new Ability(
			'calicomcp/post-schedule',
			array(
				'label'            => __( 'Schedule Post', 'calicomcp-abilities' ),
				'description'      => __( 'Schedules a draft or pending post to be published automatically at a future date and time (site timezone).', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'publish_posts',
				'permission_extra' => self::can_edit_post(),
				'annotations'      => self::annotations( false, false, false, true ),
				'execute'          => array( Posts_Provider::class, 'schedule' ),
				'input_schema'     => Schema::object(
					array(
						'id'   => Schema::int( __( 'The ID of the draft or pending post to schedule.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'date' => Schema::str( __( 'Future date and time in the site timezone, format: YYYY-MM-DD HH:MM:SS (e.g. 2026-08-01 09:00:00).', 'calicomcp-abilities' ) ),
					),
					array( 'id', 'date' )
				),
				'output_schema'    => Schema::object(
					array(
						'id'              => Schema::int(),
						'title'           => Schema::str(),
						'previous_status' => Schema::str(),
						'new_status'      => Schema::str(),
						'scheduled_for'   => Schema::str(),
						'edit_url'        => Schema::str(),
					),
					array( 'id', 'title', 'new_status', 'scheduled_for' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-set-private ability.
	 *
	 * @return Ability
	 */
	private function set_private() {
		return new Ability(
			'calicomcp/post-set-private',
			array(
				'label'            => __( 'Set Post to Private', 'calicomcp-abilities' ),
				'description'      => __( 'Sets a post to private status, making it visible only to administrators and editors.', 'calicomcp-abilities' ),
				'category'         => self::CATEGORY,
				'capability'       => 'publish_posts',
				'permission_extra' => self::can_edit_post(),
				'annotations'      => self::annotations( false, false, false, true ),
				'execute'          => array( Posts_Provider::class, 'set_private' ),
				'input_schema'     => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the post to make private.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema'    => self::status_change_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-set-pending ability.
	 *
	 * @return Ability
	 */
	private function set_pending() {
		return new Ability(
			'calicomcp/post-set-pending',
			array(
				'label'         => __( 'Set Post to Pending Review', 'calicomcp-abilities' ),
				'description'   => __( 'Sets a draft post to pending review, so an editor can review and publish it.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, false, true ),
				'execute'       => array( Posts_Provider::class, 'set_pending' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the draft post to submit for review.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => self::status_change_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-trash ability.
	 *
	 * @return Ability
	 */
	private function trash() {
		return new Ability(
			'calicomcp/post-trash',
			array(
				'label'         => __( 'Trash Post', 'calicomcp-abilities' ),
				'description'   => __( 'Moves a post to the trash. The post is recoverable and can be restored later.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'delete_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, true, true, true ),
				'execute'       => array( Posts_Provider::class, 'trash' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the post to move to trash.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => Schema::object(
					array(
						'id'              => Schema::int(),
						'title'           => Schema::str(),
						'previous_status' => Schema::str(),
						'new_status'      => Schema::str(),
						'restorable'      => Schema::boolean(),
					),
					array( 'id', 'title', 'new_status', 'restorable' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-restore ability.
	 *
	 * @return Ability
	 */
	private function restore() {
		return new Ability(
			'calicomcp/post-restore',
			array(
				'label'         => __( 'Restore Post from Trash', 'calicomcp-abilities' ),
				'description'   => __( 'Restores a post from the trash back to its previous status.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'delete_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Posts_Provider::class, 'restore' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the trashed post to restore.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => Schema::object(
					array(
						'id'         => Schema::int(),
						'title'      => Schema::str(),
						'new_status' => Schema::str(),
						'edit_url'   => Schema::str(),
					),
					array( 'id', 'title', 'new_status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-delete-permanently ability.
	 *
	 * @return Ability
	 */
	private function delete_permanently() {
		return new Ability(
			'calicomcp/post-delete-permanently',
			array(
				'label'         => __( 'Delete Post Permanently', 'calicomcp-abilities' ),
				'description'   => __( 'Permanently deletes a post, bypassing the trash. This action is IRREVERSIBLE. Requires confirm=true.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'delete_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, true, true, true ),
				'execute'       => array( Posts_Provider::class, 'delete_permanently' ),
				'input_schema'  => Schema::object(
					array(
						'id'      => Schema::int( __( 'The ID of the post to permanently delete.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'confirm' => Schema::boolean( __( 'Must be true to confirm permanent, irreversible deletion.', 'calicomcp-abilities' ) ),
					),
					array( 'id', 'confirm' )
				),
				'output_schema' => Schema::object(
					array(
						'id'      => Schema::int(),
						'title'   => Schema::str(),
						'deleted' => Schema::boolean(),
					),
					array( 'id', 'title', 'deleted' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-assign-category ability.
	 *
	 * @return Ability
	 */
	private function assign_category() {
		return new Ability(
			'calicomcp/post-assign-category',
			array(
				'label'         => __( 'Assign Category to Post', 'calicomcp-abilities' ),
				'description'   => __( 'Adds a category to a post. Existing categories on the post are kept. Use calicomcp/category-find to resolve a category name to its ID first.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Posts_Provider::class, 'assign_category' ),
				'input_schema'  => Schema::object(
					array(
						'id'          => Schema::int( __( 'The ID of the post.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'category_id' => Schema::int( __( 'The ID of the category to add.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id', 'category_id' )
				),
				'output_schema' => self::post_terms_output( 'categories' ),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-assign-tag ability.
	 *
	 * @return Ability
	 */
	private function assign_tag() {
		return new Ability(
			'calicomcp/post-assign-tag',
			array(
				'label'         => __( 'Assign Tag to Post', 'calicomcp-abilities' ),
				'description'   => __( 'Adds a tag to a post by tag name. Existing tags on the post are kept. If no tag with that name exists, it is created automatically (same as typing a new tag in the post editor).', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Posts_Provider::class, 'assign_tag' ),
				'input_schema'  => Schema::object(
					array(
						'id'  => Schema::int( __( 'The ID of the post.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'tag' => Schema::str( __( 'The name of the tag to add. Created automatically if it does not exist.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
					),
					array( 'id', 'tag' )
				),
				'output_schema' => self::post_terms_output( 'tags' ),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-remove-category ability.
	 *
	 * @return Ability
	 */
	private function remove_category() {
		return new Ability(
			'calicomcp/post-remove-category',
			array(
				'label'         => __( 'Remove Category from Post', 'calicomcp-abilities' ),
				'description'   => __( 'Removes a category from a post. Other categories on the post are kept. If the removed category was the only one, the post falls back to the default category (WordPress requires every post to have at least one).', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Posts_Provider::class, 'remove_category' ),
				'input_schema'  => Schema::object(
					array(
						'id'          => Schema::int( __( 'The ID of the post.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'category_id' => Schema::int( __( 'The ID of the category to remove.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id', 'category_id' )
				),
				'output_schema' => self::post_terms_output( 'categories' ),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-remove-tag ability.
	 *
	 * @return Ability
	 */
	private function remove_tag() {
		return new Ability(
			'calicomcp/post-remove-tag',
			array(
				'label'         => __( 'Remove Tag from Post', 'calicomcp-abilities' ),
				'description'   => __( 'Removes a tag from a post by tag name. Other tags on the post are kept. The tag itself is not deleted from the site.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_post',
				'cap_args'      => self::id_args(),
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Posts_Provider::class, 'remove_tag' ),
				'input_schema'  => Schema::object(
					array(
						'id'  => Schema::int( __( 'The ID of the post.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'tag' => Schema::str( __( 'The name of the tag to remove from the post.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
					),
					array( 'id', 'tag' )
				),
				'output_schema' => self::post_terms_output( 'tags' ),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-find ability.
	 *
	 * @return Ability
	 */
	private function find() {
		return new Ability(
			'calicomcp/post-find',
			array(
				'label'         => __( 'Find Post (ID by Name / Name by ID)', 'calicomcp-abilities' ),
				'description'   => __( 'Looks up posts to resolve a post ID from a title/name, or a title from a post ID. Use this FIRST whenever the user refers to a post by its name and another ability requires a post ID. Provide "search" with the full or partial post title to get matching posts with their IDs, or provide "id" to get that post\'s title and details. Searches all statuses. Returns up to 10 matches plus the total number of matches.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Posts_Provider::class, 'find' ),
				'input_schema'  => Schema::object(
					array(
						'search' => Schema::str( __( 'Full or partial post title/name to search for. Provide either this or "id".', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
						'id'     => Schema::int( __( 'A post ID to look up the title and details for. Provide either this or "search".', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					)
				),
				'output_schema' => Schema::object(
					array(
						'showing' => Schema::int(),
						'total'   => Schema::int(),
						'matches' => Schema::arr(
							Schema::object(
								array(
									'id'          => Schema::int(),
									'title'       => Schema::str(),
									'status'      => Schema::str(),
									'author_name' => Schema::str(),
									'date'        => Schema::str(),
									'edit_url'    => Schema::str(),
								),
								array( 'id', 'title', 'status' )
							)
						),
						'notes'   => Schema::arr( Schema::str() ),
					),
					array( 'showing', 'total', 'matches' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-get ability.
	 *
	 * @return Ability
	 */
	private function get() {
		return new Ability(
			'calicomcp/post-get',
			array(
				'label'         => __( 'Get Post', 'calicomcp-abilities' ),
				'description'   => __( 'Gets the full details of a single post by its ID, including content, status, author, dates, categories and tags.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'read',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Posts_Provider::class, 'get' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the post to retrieve.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => Schema::object(
					array(
						'id'          => Schema::int(),
						'title'       => Schema::str(),
						'content'     => Schema::str(),
						'excerpt'     => Schema::str(),
						'status'      => Schema::str(),
						'author_id'   => Schema::int(),
						'author_name' => Schema::str(),
						'created'     => Schema::str(),
						'modified'    => Schema::str(),
						'link'        => Schema::str(),
						'edit_url'    => Schema::str(),
						'categories'  => Schema::arr( Schema::str() ),
						'tags'        => Schema::arr( Schema::str() ),
					),
					array( 'id', 'title', 'content', 'status' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-list-all ability.
	 *
	 * @return Ability
	 */
	private function list_all() {
		$properties = array_merge(
			array(
				'status' => Schema::str(
					__( 'Filter by post status. Use "any" for all statuses.', 'calicomcp-abilities' ),
					array(
						'enum'    => array( 'any', 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
						'default' => 'any',
					)
				),
			),
			Schema::pagination_props( __( 'posts', 'calicomcp-abilities' ) )
		);

		return new Ability(
			'calicomcp/post-list-all',
			array(
				'label'         => __( 'List All Posts', 'calicomcp-abilities' ),
				'description'   => __( 'Lists all posts of every status (published, draft, pending, scheduled, private, trashed), with an optional status filter. Returns at most per_page posts (default 20) plus the total number of matching posts. Users who cannot edit others\' posts only see their own posts.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Posts_Provider::class, 'list_all' ),
				'input_schema'  => Schema::object( $properties ),
				'output_schema' => self::post_list_output(
					array(
						'id'          => Schema::int(),
						'title'       => Schema::str(),
						'status'      => Schema::str(),
						'author_name' => Schema::str(),
						'date'        => Schema::str(),
						'modified'    => Schema::str(),
						'edit_url'    => Schema::str(),
					),
					array( 'id', 'title', 'status' ),
					'posts'
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/post-list-drafts ability.
	 *
	 * @return Ability
	 */
	private function list_drafts() {
		return $this->paginated_list(
			'calicomcp/post-list-drafts',
			__( 'List Draft Posts', 'calicomcp-abilities' ),
			__( 'Lists all draft posts on the site, from every author, newest-modified first. Users who cannot edit others\' posts only see their own drafts.', 'calicomcp-abilities' ),
			'edit_posts',
			'list_drafts',
			__( 'drafts', 'calicomcp-abilities' ),
			array(
				'id'          => Schema::int(),
				'title'       => Schema::str(),
				'author_name' => Schema::str(),
				'excerpt'     => Schema::str(),
				'modified'    => Schema::str(),
				'edit_url'    => Schema::str(),
			),
			array( 'id', 'title', 'modified' ),
			'drafts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-own-drafts ability.
	 *
	 * @return Ability
	 */
	private function list_own_drafts() {
		return $this->paginated_list(
			'calicomcp/post-list-own-drafts',
			__( 'List Own Drafts', 'calicomcp-abilities' ),
			__( 'Lists draft posts created by the currently logged-in user only, newest-modified first.', 'calicomcp-abilities' ),
			'edit_posts',
			'list_own_drafts',
			__( 'drafts', 'calicomcp-abilities' ),
			array(
				'id'       => Schema::int(),
				'title'    => Schema::str(),
				'excerpt'  => Schema::str(),
				'modified' => Schema::str(),
				'edit_url' => Schema::str(),
			),
			array( 'id', 'title', 'modified' ),
			'drafts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-pending ability.
	 *
	 * @return Ability
	 */
	private function list_pending() {
		return $this->paginated_list(
			'calicomcp/post-list-pending',
			__( 'List Pending Posts', 'calicomcp-abilities' ),
			__( 'Lists posts that are pending review (submitted for an editor to approve and publish), newest-modified first. Users who cannot edit others\' posts only see their own pending posts.', 'calicomcp-abilities' ),
			'edit_posts',
			'list_pending',
			__( 'posts', 'calicomcp-abilities' ),
			array(
				'id'          => Schema::int(),
				'title'       => Schema::str(),
				'author_name' => Schema::str(),
				'excerpt'     => Schema::str(),
				'modified'    => Schema::str(),
				'edit_url'    => Schema::str(),
			),
			array( 'id', 'title', 'modified' ),
			'posts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-private ability.
	 *
	 * @return Ability
	 */
	private function list_private() {
		return $this->paginated_list(
			'calicomcp/post-list-private',
			__( 'List Private Posts', 'calicomcp-abilities' ),
			__( 'Lists private posts, which are visible only to editors and administrators.', 'calicomcp-abilities' ),
			'read_private_posts',
			'list_private',
			__( 'posts', 'calicomcp-abilities' ),
			array(
				'id'       => Schema::int(),
				'title'    => Schema::str(),
				'excerpt'  => Schema::str(),
				'modified' => Schema::str(),
				'edit_url' => Schema::str(),
			),
			array( 'id', 'title', 'modified' ),
			'posts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-published ability.
	 *
	 * @return Ability
	 */
	private function list_published() {
		return $this->paginated_list(
			'calicomcp/post-list-published',
			__( 'List Published Posts', 'calicomcp-abilities' ),
			__( 'Lists published (publicly visible) posts, newest first.', 'calicomcp-abilities' ),
			'read',
			'list_published',
			__( 'posts', 'calicomcp-abilities' ),
			array(
				'id'          => Schema::int(),
				'title'       => Schema::str(),
				'author_name' => Schema::str(),
				'excerpt'     => Schema::str(),
				'date'        => Schema::str(),
				'link'        => Schema::str(),
				'edit_url'    => Schema::str(),
			),
			array( 'id', 'title', 'date', 'link' ),
			'posts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-scheduled ability.
	 *
	 * @return Ability
	 */
	private function list_scheduled() {
		return $this->paginated_list(
			'calicomcp/post-list-scheduled',
			__( 'List Scheduled Posts', 'calicomcp-abilities' ),
			__( 'Lists posts that are scheduled to be published automatically at a future date and time.', 'calicomcp-abilities' ),
			'edit_posts',
			'list_scheduled',
			__( 'posts', 'calicomcp-abilities' ),
			array(
				'id'            => Schema::int(),
				'title'         => Schema::str(),
				'scheduled_for' => Schema::str(),
				'edit_url'      => Schema::str(),
			),
			array( 'id', 'title', 'scheduled_for' ),
			'posts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-trash ability.
	 *
	 * @return Ability
	 */
	private function list_trash() {
		return $this->paginated_list(
			'calicomcp/post-list-trash',
			__( 'List Trashed Posts', 'calicomcp-abilities' ),
			__( 'Lists posts currently in the trash, newest-modified first. Every trashed post is restorable (via calicomcp/post-restore) or can be permanently deleted (via calicomcp/post-delete-permanently). Users who cannot edit others\' posts only see their own trashed posts.', 'calicomcp-abilities' ),
			'edit_posts',
			'list_trash',
			__( 'posts', 'calicomcp-abilities' ),
			array(
				'id'              => Schema::int(),
				'title'           => Schema::str(),
				'author_name'     => Schema::str(),
				'previous_status' => Schema::str( __( 'The status the post had before it was trashed (it returns to this on restore).', 'calicomcp-abilities' ) ),
				'trashed_on'      => Schema::str(),
				'restorable'      => Schema::boolean(),
			),
			array( 'id', 'title', 'restorable' ),
			'posts'
		);
	}

	/**
	 * Defines the calicomcp/post-list-untagged ability.
	 *
	 * @return Ability
	 */
	private function list_untagged() {
		return $this->paginated_list(
			'calicomcp/post-list-untagged',
			__( 'List Untagged Posts', 'calicomcp-abilities' ),
			__( 'Lists posts that have no tags at all, any status except trash, newest first — useful for finding posts that still need tagging.', 'calicomcp-abilities' ),
			'edit_posts',
			'list_untagged',
			__( 'posts', 'calicomcp-abilities' ),
			array(
				'id'       => Schema::int(),
				'title'    => Schema::str(),
				'status'   => Schema::str(),
				'date'     => Schema::str(),
				'edit_url' => Schema::str(),
			),
			array( 'id', 'title', 'status' ),
			'posts'
		);
	}

	/**
	 * Builds a read-only, paginated list ability.
	 *
	 * @param string               $name       Ability name.
	 * @param string               $label      Ability label.
	 * @param string               $desc       Ability description.
	 * @param string               $capability Capability gate.
	 * @param string               $method     Posts_Provider method name.
	 * @param string               $noun       Plural noun for pagination descriptions.
	 * @param array<string, mixed> $item_props Output item properties.
	 * @param string[]             $item_req   Required output item fields.
	 * @param string               $items_key  Output key for the rows.
	 * @return Ability
	 */
	private function paginated_list( $name, $label, $desc, $capability, $method, $noun, array $item_props, array $item_req, $items_key ) {
		return new Ability(
			$name,
			array(
				'label'         => $label,
				'description'   => $desc,
				'category'      => self::CATEGORY,
				'capability'    => $capability,
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Posts_Provider::class, $method ),
				'input_schema'  => Schema::object( Schema::pagination_props( $noun ) ),
				'output_schema' => self::post_list_output( $item_props, $item_req, $items_key ),
			)
		);
	}

	/**
	 * Standard { showing, total, <key> } list output schema.
	 *
	 * @param array<string, mixed> $item_props Output item properties.
	 * @param string[]             $item_req   Required output item fields.
	 * @param string               $items_key  Output key for the rows.
	 * @return array<string, mixed>
	 */
	private static function post_list_output( array $item_props, array $item_req, $items_key ) {
		return Schema::object(
			array(
				'showing'  => Schema::int(),
				'total'    => Schema::int(),
				$items_key => Schema::arr( Schema::object( $item_props, $item_req ) ),
			),
			array( 'showing', 'total', $items_key )
		);
	}

	/**
	 * Output schema for abilities returning a post with its term names.
	 *
	 * @param string $terms_key Either 'categories' or 'tags'.
	 * @return array<string, mixed>
	 */
	private static function post_terms_output( $terms_key ) {
		return Schema::object(
			array(
				'id'       => Schema::int(),
				'title'    => Schema::str(),
				$terms_key => Schema::arr( Schema::str() ),
			),
			array( 'id', 'title', $terms_key )
		);
	}

	/**
	 * Output schema shared by simple status-change abilities.
	 *
	 * @return array<string, mixed>
	 */
	private static function status_change_output() {
		return Schema::object(
			array(
				'id'              => Schema::int(),
				'title'           => Schema::str(),
				'previous_status' => Schema::str(),
				'new_status'      => Schema::str(),
				'edit_url'        => Schema::str(),
			),
			array( 'id', 'title', 'previous_status', 'new_status' )
		);
	}

	/**
	 * Builds the four MCP annotation hints.
	 *
	 * @param bool $read_only   Read-only hint.
	 * @param bool $destructive Destructive hint.
	 * @param bool $idempotent  Idempotent hint.
	 * @param bool $open_world  Open-world hint.
	 * @return array<string, bool>
	 */
	private static function annotations( $read_only, $destructive, $idempotent, $open_world ) {
		return array(
			'readonly'    => $read_only,
			'destructive' => $destructive,
			'idempotent'  => $idempotent,
			'open_world'  => $open_world,
		);
	}

	/**
	 * Resolves the post ID from input for object-level capability checks (cap_args).
	 *
	 * @return callable
	 */
	private static function id_args() {
		return static function ( $input ) {
			return array( isset( $input['id'] ) ? absint( $input['id'] ) : 0 );
		};
	}

	/**
	 * Resolves the source post ID for object-level capability checks (cap_args).
	 *
	 * Duplication reads the source, so the capability gate is edit_post against
	 * source_id rather than against a post the caller is about to create.
	 *
	 * @return callable
	 */
	private static function source_id_args() {
		return static function ( $input ) {
			return array( isset( $input['source_id'] ) ? absint( $input['source_id'] ) : 0 );
		};
	}

	/**
	 * Resolves the first post ID for the comparison ability's capability check.
	 *
	 * The gate can only carry one object, so it covers post_a here; the provider
	 * re-checks edit rights on both posts before reading either.
	 *
	 * @return callable
	 */
	private static function compare_args() {
		return static function ( $input ) {
			return array( isset( $input['post_a'] ) ? absint( $input['post_a'] ) : 0 );
		};
	}

	/**
	 * Requires the post type's create capability in addition to edit_post on the
	 * source (permission_extra).
	 *
	 * wp_insert_post enforces no capabilities of its own, so without this a user
	 * who may edit a post could create new ones they have no right to.
	 *
	 * @return callable
	 */
	private static function can_create_posts() {
		return static function () {
			$type = get_post_type_object( 'post' );
			return $type ? current_user_can( $type->cap->create_posts ) : false;
		};
	}

	/**
	 * Requires edit rights on the specific post when an ID is given (permission_extra).
	 *
	 * @return callable
	 */
	private static function can_edit_post() {
		return static function ( $input ) {
			$id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
			return $id > 0 ? current_user_can( 'edit_post', $id ) : true;
		};
	}
}
