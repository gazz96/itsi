<?php
/**
 * Custom Walker_Nav_Menu subclasses for the ITSI theme.
 *
 * Each walker emits HTML that matches the existing CSS selectors in
 * style.css. Without these, `wp_nav_menu()` falls back to WP's default
 * markup (`.menu-item`, plain anchors) and the navbar/mobile dropdown
 * styling collapses.
 *
 * Walker_Nav_Menu::start_lvl() etc. all pass `$args` (the wp_nav_menu()
 * $args object) and `$depth`. We rely on `$args->theme_location` to
 * distinguish the two locations and pick the right markup rules.
 *
 * @package itsi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Navbar desktop walker — used for theme_location 'menu-1'.
 *
 * Emits markup matching style.css selectors:
 *   - <ul class="nav-links">   (wrapper, set by wp_nav_menu menu_class)
 *   - <li class="nli ...">     (top-level item)
 *   - <a class="nl-a">         (top-level link)
 *   - <div class="dd">         (sub-menu wrapper, instead of default <ul>)
 *   - <a class="dd-a">         (dropdown item)
 *
 * The dropdown is flat (no `.dd-group` / `.dd-label` grouping) — admin can
 * still add child items via WP Menu admin UI but they're rendered in one
 * column. Falls back gracefully on depth >=2 by reusing dd-a styling.
 */
class ITSI_Navbar_Walker extends Walker_Nav_Menu {

	/**
	 * Open the sub-menu container.
	 *
	 * @param string   $output
	 * @param int      $depth
	 * @param stdClass $args
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		// IMPORTANT: emit <ul>, not <div>. The HTML spec forbids <li> directly
		// inside <div>, so browsers silently restructure the tree when we wrap
		// <li> children in a <div> — they hoist the <li> back out to the
		// nearest <ul>/<ol> parent, leaving an empty <div class="dd"> and
		// breaking the dropdown entirely. <ul class="dd"> is both valid and
		// matches our CSS selector `.dd` (which styles by class, not tag).
		if ( 0 === $depth ) {
			$output .= '<ul class="dd">';
		} else {
			$output .= '<ul class="dd dd-nested">';
		}
	}

	/**
	 * Close the sub-menu container.
	 *
	 * @param string   $output
	 * @param int      $depth
	 * @param stdClass $args
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	/**
	 * Render a single menu item.
	 *
	 * @param string   $output
	 * @param WP_Post  $data_object
	 * @param int      $depth
	 * @param stdClass $args
	 * @param int      $current_object_id
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		$item = $data_object;

		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		// Inject our custom classes alongside WP defaults.
		if ( 0 === $depth ) {
			$classes[] = 'nli';
		} else {
			$classes[] = 'dd-i';
		}

		/** This filter is documented in wp-includes/nav-menu-template.php */
		$class_names = implode( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
		$id          = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );

		$li_atts          = array();
		$li_atts['id']    = $id;
		$li_atts['class'] = $class_names;
		// Mark items that have nested children (depth>=1 with grandchildren)
		// with [data-has-children] so the JS click handler can wire them as
		// toggles. CSS targets `.dd-i[data-has-children]` to draw the
		// right-side chevron; plain `.dd-i` (leaf) has no chevron.
		if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$li_atts['data-has-children'] = '1';
		}
		$li_attributes    = $this->build_atts( $li_atts );

		$output .= '<li' . $li_attributes . '>';

		$title = apply_filters( 'the_title', $item->title, $item->ID );

		$atts           = array();
		$atts['href']   = ! empty( $item->url ) ? $item->url : '#';
		$atts['class']  = ( 0 === $depth ) ? 'nl-a' : 'dd-a';
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';

		// Append caret chevron for any item that has children — top-level or
		// nested. CSS targets `.dd-i:hover > .dd-a > svg` to rotate it -90deg
		// (pointing left → down when active) for the level-1 fly-out cue.
		if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$has_caret = true;
		} else {
			$has_caret = false;
		}

		$attributes = $this->build_atts( $atts );
		$item_output = isset( $args->before ) ? $args->before : '';
		$item_output .= '<a' . $attributes . '>';
		$item_output .= isset( $args->link_before ) ? $args->link_before : '';
		$item_output .= esc_html( $title );
		$item_output .= isset( $args->link_after ) ? $args->link_after : '';
		if ( $has_caret ) {
			$item_output .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>';
		}
		$item_output .= '</a>';
		$item_output .= isset( $args->after ) ? $args->after : '';

		/** This filter is documented in wp-includes/nav-menu-template.php */
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}
}

/**
 * Mobile walker — used for theme_location 'mobile-menu'.
 *
 * Emits markup matching style.css selectors:
 *   - <a class="mob-a">              (each item is an anchor, no <li> wrapper)
 *   - <div class="mob-sec-ttl">      (top-level item with children gets a
 *                                    section-title treatment automatically)
 *
 * Children are rendered inline with the same mob-a class so they indent
 * visually via CSS rather than via deeper nesting. We still emit proper
 * HTML structure (<ul class="sub-menu">) so screen readers get it.
 */
class ITSI_Mobile_Walker extends Walker_Nav_Menu {

	/**
	 * Open sub-menu container.
	 *
	 * @param string   $output
	 * @param int      $depth
	 * @param stdClass $args
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '<ul class="mob-sub">';
	}

	/**
	 * Close sub-menu container.
	 *
	 * @param string   $output
	 * @param int      $depth
	 * @param stdClass $args
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	/**
	 * Render a single menu item as a direct anchor (no <li> wrapper).
	 *
	 * @param string   $output
	 * @param WP_Post  $data_object
	 * @param int      $depth
	 * @param stdClass $args
	 * @param int      $current_object_id
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		$item = $data_object;

		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$url   = ! empty( $item->url ) ? $item->url : '#';

		// Top-level items with children get a section-title treatment so the
		// mobile nav reads as grouped sections like the fallback HTML.
		if ( 0 === $depth && in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$output .= '<div class="mob-sec-ttl">' . esc_html( $title ) . '</div>';
			return;
		}

		$atts          = array();
		$atts['href']  = $url;
		$atts['class'] = 'mob-a';
		$atts['title'] = ! empty( $item->attr_title ) ? $item->attr_title : '';

		// Mark current item so CSS can highlight it.
		$is_current = array_intersect(
			array( 'current-menu-item', 'current_page_item', 'current-menu-ancestor', 'current_page_ancestor' ),
			(array) $item->classes
		);
		if ( ! empty( $is_current ) ) {
			$atts['class'] .= ' mob-current';
		}

		$attributes = $this->build_atts( $atts );

		// Indent child anchors with non-breaking space prefix — purely visual,
		// CSS doesn't have a clean selector for child-of-mob-nav <a> here.
		$prefix = ( $depth > 0 ) ? str_repeat( '&nbsp;&nbsp;', $depth ) : '';

		$item_output  = '<a' . $attributes . ' onclick="closeMob()">';
		$item_output .= $prefix . esc_html( $title );
		$item_output .= '</a>';

		/** This filter is documented in wp-includes/nav-menu-template.php */
		$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
	}

	/**
	 * Suppress the closing </li> wrapper since start_el doesn't open one.
	 *
	 * @param string   $output
	 * @param WP_Post  $data_object
	 * @param int      $depth
	 * @param stdClass $args
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
		// No-op: start_el renders the full element directly.
	}
}

/**
 * Return the appropriate Walker instance for a given theme_location.
 *
 * @param string $theme_location
 * @return Walker_Nav_Menu|null
 */
function itsi_get_menu_walker( $theme_location ) {
	switch ( $theme_location ) {
		case 'menu-1':
			return new ITSI_Navbar_Walker();
		case 'mobile-menu':
			return new ITSI_Mobile_Walker();
		default:
			return null;
	}
}