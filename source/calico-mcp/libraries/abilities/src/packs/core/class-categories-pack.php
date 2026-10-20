<?php
/**
 * Core Categories ability pack: definitions for the calicomcp/category-* abilities.
 *
 * @package Mosmcp_Abilities_Library
 */

namespace CalicoMCP\Abilities\Packs\Core;

use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
use CalicoMCP\Abilities\Packs\Core\Support\Core_Schema as Schema;

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
 * Class Categories_Pack
 *
 * Declares the category abilities and their governed contract. Execute logic
 * lives in Categories_Provider and is preserved from the reviewed source.
 */
class Categories_Pack extends Ability_Pack {

	/**
	 * Ability category slug.
	 */
	const CATEGORY = 'calicomcp-categories';

	/**
	 * Ability category for category abilities.
	 *
	 * @return array Category definition.
	 */
	public function category() {
		return array(
			'slug'        => self::CATEGORY,
			'label'       => __( 'Categories', 'calicomcp-abilities' ),
			'description' => __( 'Create, edit, organize, and manage post categories.', 'calicomcp-abilities' ),
		);
	}

	/**
	 * The category abilities.
	 *
	 * @return Ability[]
	 */
	public function abilities() {
		return array(
			$this->create(),
			$this->delete(),
			$this->find(),
			$this->get(),
			$this->list_all(),
			$this->list_children(),
			$this->list_empty(),
			$this->list_posts(),
			$this->update_description(),
			$this->update_name(),
			$this->update_slug(),
		);
	}

	/**
	 * Defines the calicomcp/category-create ability.
	 *
	 * @return Ability
	 */
	private function create() {
		return new Ability(
			'calicomcp/category-create',
			array(
				'label'         => __( 'Create Category', 'calicomcp-abilities' ),
				'description'   => __( 'Creates a new post category. Optionally set a parent category (to create a sub-category), a custom slug, and a description. Fails with a clear error if a category with the same name already exists.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'manage_categories',
				'annotations'   => self::annotations( false, false, false, false ),
				'execute'       => array( Categories_Provider::class, 'create' ),
				'input_schema'  => Schema::object(
					array(
						'name'        => Schema::str( __( 'The name of the new category.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
						'parent_id'   => Schema::int( __( 'Optional ID of a parent category, to create this as a sub-category.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'slug'        => Schema::str( __( 'Optional custom URL slug. If omitted, WordPress generates one from the name.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
						'description' => Schema::str( __( 'Optional description of the category.', 'calicomcp-abilities' ) ),
					),
					array( 'name' )
				),
				'output_schema' => Schema::object(
					array(
						'id'          => Schema::int(),
						'name'        => Schema::str(),
						'slug'        => Schema::str(),
						'description' => Schema::str(),
						'parent_id'   => Schema::int(),
						'edit_url'    => Schema::str(),
					),
					array( 'id', 'name' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-delete ability.
	 *
	 * @return Ability
	 */
	private function delete() {
		return new Ability(
			'calicomcp/category-delete',
			array(
				'label'         => __( 'Delete Category', 'calicomcp-abilities' ),
				'description'   => __( 'Deletes a post category. The posts in it are NOT deleted — they move to the default category. The default category itself cannot be deleted. Requires confirm=true.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'manage_categories',
				'annotations'   => self::annotations( false, true, true, true ),
				'execute'       => array( Categories_Provider::class, 'delete' ),
				'input_schema'  => Schema::object(
					array(
						'id'      => Schema::int( __( 'The ID of the category to delete.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'confirm' => Schema::boolean( __( 'Must be true to confirm the deletion.', 'calicomcp-abilities' ) ),
					),
					array( 'id', 'confirm' )
				),
				'output_schema' => Schema::object(
					array(
						'id'             => Schema::int(),
						'name'           => Schema::str(),
						'deleted'        => Schema::boolean(),
						'posts_moved_to' => Schema::str(),
					),
					array( 'id', 'name', 'deleted' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-find ability.
	 *
	 * @return Ability
	 */
	private function find() {
		return new Ability(
			'calicomcp/category-find',
			array(
				'label'         => __( 'Find Category (ID by Name / Name by ID)', 'calicomcp-abilities' ),
				'description'   => __( 'Looks up post categories to resolve a category ID from a name, or a name from an ID. Use this FIRST whenever the user refers to a category by name and another ability requires a category ID. Provide "search" with the full or partial category name, or provide "id".', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Categories_Provider::class, 'find' ),
				'input_schema'  => Schema::object(
					array(
						'search' => Schema::str( __( 'Full or partial category name to search for. Provide either this or "id".', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
						'id'     => Schema::int( __( 'A category ID to look up. Provide either this or "search".', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					)
				),
				'output_schema' => self::matches_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-get ability.
	 *
	 * @return Ability
	 */
	private function get() {
		return new Ability(
			'calicomcp/category-get',
			array(
				'label'         => __( 'Get Category', 'calicomcp-abilities' ),
				'description'   => __( 'Gets the details of a single post category by its ID: name, slug, description, parent category, and how many posts it contains.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Categories_Provider::class, 'get' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the category to retrieve.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => self::category_item(),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-list-all ability.
	 *
	 * @return Ability
	 */
	private function list_all() {
		return new Ability(
			'calicomcp/category-list-all',
			array(
				'label'         => __( 'List All Categories', 'calicomcp-abilities' ),
				'description'   => __( 'Lists all post categories, including empty ones, with their post counts and hierarchy (each item includes its parent category, if any). Returns at most per_page categories (default 20) plus the total.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Categories_Provider::class, 'list_all' ),
				'input_schema'  => Schema::object( Schema::pagination_props( __( 'categories', 'calicomcp-abilities' ) ) ),
				'output_schema' => self::categories_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-list-children ability.
	 *
	 * @return Ability
	 */
	private function list_children() {
		return new Ability(
			'calicomcp/category-list-children',
			array(
				'label'         => __( 'List Sub-Categories', 'calicomcp-abilities' ),
				'description'   => __( 'Lists the direct sub-categories (children) of a given post category, including empty ones.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Categories_Provider::class, 'list_children' ),
				'input_schema'  => Schema::object(
					array(
						'id' => Schema::int( __( 'The ID of the parent category.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
					),
					array( 'id' )
				),
				'output_schema' => Schema::object(
					array(
						'showing'     => Schema::int(),
						'total'       => Schema::int(),
						'parent_name' => Schema::str(),
						'categories'  => Schema::arr( self::category_item() ),
					),
					array( 'showing', 'total', 'categories' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-list-empty ability.
	 *
	 * @return Ability
	 */
	private function list_empty() {
		return new Ability(
			'calicomcp/category-list-empty',
			array(
				'label'         => __( 'List Empty Categories', 'calicomcp-abilities' ),
				'description'   => __( 'Lists post categories that contain no posts — useful for finding categories that could be cleaned up or deleted.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Categories_Provider::class, 'list_empty' ),
				'input_schema'  => Schema::object( Schema::pagination_props( __( 'categories', 'calicomcp-abilities' ) ) ),
				'output_schema' => self::categories_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-list-posts ability.
	 *
	 * @return Ability
	 */
	private function list_posts() {
		$properties = array_merge(
			array(
				'id' => Schema::int( __( 'The ID of the category.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
			),
			Schema::pagination_props( __( 'posts', 'calicomcp-abilities' ) )
		);

		return new Ability(
			'calicomcp/category-list-posts',
			array(
				'label'         => __( 'List Posts in Category', 'calicomcp-abilities' ),
				'description'   => __( 'Lists the posts in a given category (including posts in its sub-categories), any status except trash, newest first. Returns at most per_page posts (default 20) plus the total.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'edit_posts',
				'annotations'   => self::annotations( true, false, true, false ),
				'execute'       => array( Categories_Provider::class, 'list_posts' ),
				'input_schema'  => Schema::object( $properties, array( 'id' ) ),
				'output_schema' => Schema::object(
					array(
						'showing'       => Schema::int(),
						'total'         => Schema::int(),
						'category_name' => Schema::str(),
						'posts'         => Schema::arr( self::term_post_item() ),
					),
					array( 'showing', 'total', 'posts' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-update-description ability.
	 *
	 * @return Ability
	 */
	private function update_description() {
		return new Ability(
			'calicomcp/category-update-description',
			array(
				'label'         => __( 'Update Category Description', 'calicomcp-abilities' ),
				'description'   => __( 'Changes the description of an existing post category. The name and slug are not affected. Pass an empty string to clear the description.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'manage_categories',
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Categories_Provider::class, 'update_description' ),
				'input_schema'  => Schema::object(
					array(
						'id'          => Schema::int( __( 'The ID of the category to update.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'description' => Schema::str( __( 'The new description. May be an empty string to clear it.', 'calicomcp-abilities' ) ),
					),
					array( 'id', 'description' )
				),
				'output_schema' => Schema::object(
					array(
						'id'          => Schema::int(),
						'name'        => Schema::str(),
						'description' => Schema::str(),
						'edit_url'    => Schema::str(),
					),
					array( 'id', 'name', 'description' )
				),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-update-name ability.
	 *
	 * @return Ability
	 */
	private function update_name() {
		return new Ability(
			'calicomcp/category-update-name',
			array(
				'label'         => __( 'Rename Category', 'calicomcp-abilities' ),
				'description'   => __( 'Changes the name of an existing post category. Posts in the category are not affected.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'manage_categories',
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Categories_Provider::class, 'update_name' ),
				'input_schema'  => Schema::object(
					array(
						'id'   => Schema::int( __( 'The ID of the category to rename.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'name' => Schema::str( __( 'The new name for the category.', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
					),
					array( 'id', 'name' )
				),
				'output_schema' => self::term_name_output(),
			)
		);
	}

	/**
	 * Defines the calicomcp/category-update-slug ability.
	 *
	 * @return Ability
	 */
	private function update_slug() {
		return new Ability(
			'calicomcp/category-update-slug',
			array(
				'label'         => __( 'Update Category Slug', 'calicomcp-abilities' ),
				'description'   => __( 'Changes the URL slug of an existing post category. The name and description are not affected. Note: this changes the category archive URL, so old links to it will stop working.', 'calicomcp-abilities' ),
				'category'      => self::CATEGORY,
				'capability'    => 'manage_categories',
				'annotations'   => self::annotations( false, false, true, true ),
				'execute'       => array( Categories_Provider::class, 'update_slug' ),
				'input_schema'  => Schema::object(
					array(
						'id'   => Schema::int( __( 'The ID of the category to update.', 'calicomcp-abilities' ), array( 'minimum' => 1 ) ),
						'slug' => Schema::str( __( 'The new URL slug (lowercase letters, numbers, and dashes).', 'calicomcp-abilities' ), array( 'minLength' => 1 ) ),
					),
					array( 'id', 'slug' )
				),
				'output_schema' => self::term_slug_output(),
			)
		);
	}

	/**
	 * Standard category output item schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function category_item() {
		return Schema::object(
			array(
				'id'          => Schema::int(),
				'name'        => Schema::str(),
				'slug'        => Schema::str(),
				'description' => Schema::str(),
				'parent_id'   => Schema::int(),
				'parent_name' => Schema::str(),
				'post_count'  => Schema::int(),
				'edit_url'    => Schema::str(),
			),
			array( 'id', 'name', 'post_count' )
		);
	}

	/**
	 * Output item for posts listed under a category.
	 *
	 * @return array<string, mixed>
	 */
	private static function term_post_item() {
		return Schema::object(
			array(
				'id'       => Schema::int(),
				'title'    => Schema::str(),
				'status'   => Schema::str(),
				'date'     => Schema::str(),
				'edit_url' => Schema::str(),
			),
			array( 'id', 'title', 'status' )
		);
	}

	/**
	 * { showing, total, matches } output schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function matches_output() {
		return Schema::object(
			array(
				'showing' => Schema::int(),
				'total'   => Schema::int(),
				'matches' => Schema::arr( self::category_item() ),
			),
			array( 'showing', 'total', 'matches' )
		);
	}

	/**
	 * { showing, total, categories } output schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function categories_output() {
		return Schema::object(
			array(
				'showing'    => Schema::int(),
				'total'      => Schema::int(),
				'categories' => Schema::arr( self::category_item() ),
			),
			array( 'showing', 'total', 'categories' )
		);
	}

	/**
	 * Output schema for rename (id, name, slug, edit_url).
	 *
	 * @return array<string, mixed>
	 */
	private static function term_name_output() {
		return Schema::object(
			array(
				'id'       => Schema::int(),
				'name'     => Schema::str(),
				'slug'     => Schema::str(),
				'edit_url' => Schema::str(),
			),
			array( 'id', 'name' )
		);
	}

	/**
	 * Output schema for slug change (id, name, slug, edit_url).
	 *
	 * @return array<string, mixed>
	 */
	private static function term_slug_output() {
		return Schema::object(
			array(
				'id'       => Schema::int(),
				'name'     => Schema::str(),
				'slug'     => Schema::str(),
				'edit_url' => Schema::str(),
			),
			array( 'id', 'name', 'slug' )
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
}
