<?php
/**
 * Generic template helpers: URLs, company output, icons, menus, blog, page config.
 *
 * Company data comes only from starter_get_company() (mu-plugin); nothing project-specific here.
 *
 * @package Starter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Absolute URL for a site path (leading slash optional).
 *
 * @param string $path Path relative to home, e.g. '/contacts/'.
 * @return string
 */
function starter_url( string $path = '/' ): string {
	return home_url( '/' . ltrim( $path, '/' ) );
}

/**
 * Home-page URL with an optional fragment.
 *
 * @param string $hash Fragment with or without the leading #.
 * @return string
 */
function starter_home_hash( string $hash = '' ): string {
	$hash = ltrim( $hash, '#' );

	return '' === $hash ? home_url( '/' ) : home_url( '/' ) . '#' . $hash;
}

/**
 * Company value as a trimmed string ('' for arrays / missing keys).
 *
 * @param string $key Dotted key of starter_get_company(), e.g. 'phone' or 'address.text'.
 * @return string
 */
function starter_company_value( string $key ): string {
	$value = starter_get_company( $key );

	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Phones of the company: list of { number, href, label }.
 *
 * @return array<int, array{number: string, href: string, label: string}>
 */
function starter_company_phones(): array {
	$phones = starter_get_company( 'phones' );
	$result = array();

	foreach ( is_array( $phones ) ? $phones : array() as $phone ) {
		if ( ! is_array( $phone ) || empty( $phone['number'] ) ) {
			continue;
		}
		$result[] = array(
			'number' => (string) $phone['number'],
			'href'   => (string) ( $phone['href'] ?? '' ),
			'label'  => (string) ( $phone['label'] ?? '' ),
		);
	}

	return $result;
}

/**
 * Phone link URL with the tel: scheme (core starter_phone_href() normalizes the digits).
 *
 * @param string $phone Optional number; default — the main company phone.
 * @return string Raw URL ('' when there is no phone); escape with esc_url() on output.
 */
function starter_tel_href( string $phone = '' ): string {
	$href = '' === $phone ? starter_company_value( 'phone_href' ) : starter_phone_href( $phone );

	return '' === $href ? '' : 'tel:' . $href;
}

/**
 * Social / messenger links from company options.
 *
 * @return array<int, array{network: string, url: string, label: string}>
 */
function starter_company_socials(): array {
	$socials = starter_get_company( 'socials' );
	$result  = array();
	$names   = starter_social_networks();

	foreach ( is_array( $socials ) ? $socials : array() as $row ) {
		if ( ! is_array( $row ) || empty( $row['url'] ) ) {
			continue;
		}
		$network  = (string) ( $row['network'] ?? 'other' );
		$label    = trim( (string) ( $row['label'] ?? '' ) );
		$result[] = array(
			'network' => $network,
			'url'     => (string) $row['url'],
			'label'   => '' !== $label ? $label : (string) ( $names[ $network ] ?? $network ),
		);
	}

	return $result;
}

/**
 * Inline SVG icon markup (decorative, aria-hidden). Unknown names fall back to a generic link icon.
 *
 * @param string $name Icon: phone, mail, pin, clock, telegram, whatsapp, viber, instagram, vk, facebook, youtube, arrow, check, close, play, star, link.
 * @param int    $size Width/height in px.
 * @return string Trusted SVG markup.
 */
function starter_icon( string $name, int $size = 20 ): string {
	$stroke = 'fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"';
	$fill   = 'fill="currentColor"';
	$paths  = array(
		'phone'     => array( $stroke, '<path d="M6.6 3.5 9 8l-2 1.6a12 12 0 0 0 5.4 5.4L14 13l4.5 2.4c.7.4 1 1.2.8 1.9l-.6 2A2 2 0 0 1 16.4 21C9.6 20 4 14.4 3 7.6a2 2 0 0 1 1.4-2.2l2-.6c.8-.2 1.6.1 2 .7z"/>' ),
		'mail'      => array( $stroke, '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>' ),
		'pin'       => array( $stroke, '<path d="M12 21s-7-6.2-7-11.2A7 7 0 0 1 19 9.8C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.8" r="2.6"/>' ),
		'clock'     => array( $stroke, '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' ),
		'telegram'  => array( $fill, '<path d="M21.9 4.3 18.8 19c-.2 1-.9 1.3-1.7.8l-4.6-3.4-2.2 2.1c-.3.3-.5.5-1 .5l.4-4.9 8.6-7.8c.4-.3-.1-.5-.6-.2L6.8 12.8l-4.3-1.3c-.9-.3-.9-.9.2-1.3l17.1-6.6c.8-.3 1.4.2 1.1 1.6"/>' ),
		'whatsapp'  => array( $stroke, '<path d="M3.5 20.5 5 16a8.5 8.5 0 1 1 3 3z"/><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.5-2-1-1 .8a5 5 0 0 1-2.8-2.8l.8-1-1-2z"/>' ),
		'viber'     => array( $stroke, '<path d="M12 3c5 0 8 2.5 8 7.5S17 18 12 18l-4 3v-3.5C5 16.5 4 14 4 10.5 4 5.5 7 3 12 3z"/><path d="M9.5 8c0 3 2.5 5.5 5.5 5.5"/>' ),
		'instagram' => array( $stroke, '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>' ),
		'vk'        => array( $stroke, '<path d="M3 7h3c.5 3 2 5.5 4 6V7h3v3.5c1.5-.3 3-2 3.5-3.5H20c-.5 2.5-2.5 4.5-4 5.3 1.7.7 3.5 2.7 4 4.7h-3.5c-.5-1.5-2-3-3.5-3.2V17h-1C6.5 17 3.3 12.5 3 7z"/>' ),
		'facebook'  => array( $stroke, '<path d="M14 21v-8h3l.5-3.5H14V7.5c0-1 .3-1.8 1.8-1.8H18V2.6c-.4 0-1.6-.1-2.9-.1-2.9 0-4.6 1.7-4.6 4.9v2.1H7.5V13h3v8"/>' ),
		'youtube'   => array( $stroke, '<rect x="2.5" y="5.5" width="19" height="13" rx="3.5"/><path d="m10 9 5 3-5 3z"/>' ),
		'arrow'     => array( $stroke, '<path d="M5 12h14M13 6l6 6-6 6"/>' ),
		'check'     => array( $stroke, '<path d="M20 6 9 17l-5-5"/>' ),
		'close'     => array( $stroke, '<path d="M6 6l12 12M18 6 6 18"/>' ),
		'play'      => array( $fill, '<path d="M8 5.5v13l11-6.5z"/>' ),
		'star'      => array( $fill, '<path d="m12 2 3 6.5 7 .9-5 4.8 1.2 7L12 17.8 5.8 21.2 7 14.2 2 9.4l7-.9z"/>' ),
		'link'      => array( $stroke, '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>' ),
	);

	$icon = $paths[ $name ] ?? $paths['link'];

	return sprintf(
		'<svg class="icon icon--%1$s" viewBox="0 0 24 24" width="%2$d" height="%2$d" %3$s aria-hidden="true" focusable="false">%4$s</svg>',
		esc_attr( $name ),
		$size,
		$icon[0],
		$icon[1]
	);
}

/**
 * Echo an icon (see starter_icon()).
 *
 * @param string $name Icon name.
 * @param int    $size Size in px.
 */
function starter_the_icon( string $name, int $size = 20 ): void {
	echo starter_icon( $name, $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG from a whitelist, attributes escaped.
}

/**
 * Neutral brand mark (replace with the project logo when porting the prototype).
 */
function starter_brand_mark(): void {
	?>
	<svg class="brand__mark" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true" focusable="false">
		<rect class="brand__mark-bg" x="4" y="4" width="40" height="40" rx="11"/>
		<circle class="brand__mark-ring" cx="24" cy="24" r="12"/>
	</svg>
	<?php
}

/**
 * Social / messenger icon links.
 *
 * @param string $modifier BEM modifier of the .socials block (e.g. 'footer').
 */
function starter_social_links( string $modifier = '' ): void {
	$socials = starter_company_socials();
	if ( ! $socials ) {
		return;
	}

	$class = 'socials' . ( '' !== $modifier ? ' socials--' . sanitize_html_class( $modifier ) : '' );
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $socials as $social ) {
		printf(
			'<li class="socials__item"><a class="socials__link socials__link--%1$s" href="%2$s" target="_blank" rel="noopener" aria-label="%3$s">%4$s</a></li>',
			esc_attr( sanitize_html_class( $social['network'] ) ),
			esc_url( $social['url'] ),
			esc_attr( $social['label'] ),
			starter_icon( $social['network'], 20 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		);
	}
	echo '</ul>';
}

/**
 * Whether a theme location has a menu with at least one item.
 *
 * @param string $location Menu location.
 * @return bool
 */
function starter_has_menu_items( string $location ): bool {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return false;
	}

	$items = wp_get_nav_menu_items( (int) $locations[ $location ] );

	return is_array( $items ) && count( $items ) > 0;
}

/**
 * Links for menu fallbacks (no menu assigned yet): top-level pages from pages-map.
 *
 * @return array<int, array{title: string, url: string, current: bool}>
 */
function starter_fallback_nav_links(): array {
	$links   = array();
	$current = is_singular() || is_home() ? (string) wp_parse_url( (string) get_permalink( get_queried_object_id() ), PHP_URL_PATH ) : '';

	foreach ( starter_core_pages() as $page ) {
		$url  = (string) ( $page['url'] ?? '' );
		$path = trim( $url, '/' );
		$type = (string) ( $page['type'] ?? '' );
		if ( '' === $path || str_contains( $path, '/' ) || ! in_array( $type, array( 'page', 'service', 'blog-index' ), true ) ) {
			continue;
		}
		$links[] = array(
			'title'   => (string) ( $page['title'] ?? $url ),
			'url'     => starter_url( $url ),
			'current' => $current === $url,
		);
	}

	/**
	 * Filter fallback menu links.
	 *
	 * @param array<int, array{title: string, url: string, current: bool}> $links Links.
	 */
	return (array) apply_filters( 'starter_fallback_nav_links', $links );
}

/**
 * Blog index URL (posts page, or home when none is set).
 *
 * @return string
 */
function starter_blog_url(): string {
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( $posts_page > 0 ) {
		$link = get_permalink( $posts_page );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}

	return home_url( '/' );
}

/**
 * Whether the blog has at least one published post.
 *
 * @return bool
 */
function starter_has_blog(): bool {
	$counts = wp_count_posts( 'post' );

	return isset( $counts->publish ) && (int) $counts->publish > 0;
}

/**
 * Non-empty categories for blog filter chips (render chips only for 3+ terms).
 *
 * @return WP_Term[]
 */
function starter_blog_filter_categories(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);

	if ( ! is_array( $terms ) ) {
		return array();
	}

	return array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term ) );
}

/**
 * Privacy policy URL (Settings → Privacy page), '' when not published.
 *
 * @return string
 */
function starter_privacy_url(): string {
	return (string) get_privacy_policy_url();
}

/**
 * Count words in content (Unicode-aware).
 *
 * @param string $content Raw content.
 * @return int
 */
function starter_count_words( string $content ): int {
	$text = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	if ( '' === $text ) {
		return 0;
	}

	$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

	return is_array( $parts ) ? count( $parts ) : 0;
}

/**
 * Estimated reading time in minutes.
 *
 * @param int $post_id Post ID (0 = current).
 * @return int At least 1.
 */
function starter_post_reading_minutes( int $post_id = 0 ): int {
	$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
	$wpm     = max( 60, (int) apply_filters( 'starter_reading_wpm', 180 ) );

	return max( 1, (int) ceil( starter_count_words( (string) get_post_field( 'post_content', $post_id ) ) / $wpm ) );
}

/**
 * Add Instagram to the user profile contact methods (author card link).
 *
 * @param array<string, string> $methods Contact methods.
 * @return array<string, string>
 */
function starter_user_contactmethods( array $methods ): array {
	if ( ! isset( $methods['instagram'] ) ) {
		$methods['instagram'] = 'Instagram';
	}

	return $methods;
}
add_filter( 'user_contactmethods', 'starter_user_contactmethods' );

/**
 * Normalize a profile Instagram value (@user, user or URL) to an https URL.
 *
 * @param string $raw Raw value.
 * @return string '' when unusable.
 */
function starter_normalize_instagram_url( string $raw ): string {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return '';
	}

	if ( preg_match( '#^https?://#i', $raw ) ) {
		$host = wp_parse_url( $raw, PHP_URL_HOST );
		return is_string( $host ) && preg_match( '/(^|\.)instagram\.com$/i', $host ) ? esc_url_raw( $raw ) : '';
	}

	$user = (string) preg_replace( '#^(www\.)?instagram\.com/#i', '', ltrim( $raw, '@' ) );
	$user = trim( $user, '/' );

	return preg_match( '/^[A-Za-z0-9._]+$/', $user ) ? 'https://www.instagram.com/' . rawurlencode( $user ) . '/' : '';
}

/**
 * Author display data for a post. No Gravatar request: avatars come only from the
 * `starter_post_author_avatar` filter (local image), otherwise initials are shown.
 *
 * @param int $user_id Author ID (0 = author of the current post).
 * @return array{name: string, initials: string, url: string, instagram_url: string, instagram_label: string, avatar_html: string}|null
 */
function starter_get_post_author_data( int $user_id = 0 ): ?array {
	if ( $user_id <= 0 ) {
		$user_id = (int) get_post_field( 'post_author', (int) get_the_ID() );
	}
	$user = $user_id > 0 ? get_userdata( $user_id ) : false;
	if ( ! $user instanceof WP_User ) {
		return null;
	}

	$first = trim( (string) $user->first_name );
	$last  = trim( (string) $user->last_name );
	$name  = trim( $first . ' ' . $last );
	if ( '' === $name ) {
		$name = trim( (string) $user->display_name );
	}
	if ( '' === $name ) {
		return null;
	}

	$words    = preg_split( '/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY );
	$initials = '';
	foreach ( array_slice( is_array( $words ) ? $words : array(), 0, 2 ) as $word ) {
		$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}

	$instagram = starter_normalize_instagram_url( (string) get_user_meta( $user_id, 'instagram', true ) );
	$ig_label  = '';
	if ( '' !== $instagram ) {
		$path     = trim( (string) wp_parse_url( $instagram, PHP_URL_PATH ), '/' );
		$ig_label = '' !== $path ? '@' . $path : 'Instagram';
	}

	return array(
		'name'            => $name,
		'initials'        => $initials,
		'url'             => (string) $user->user_url,
		'instagram_url'   => $instagram,
		'instagram_label' => $ig_label,
		/**
		 * Filter the author avatar HTML (must be a local <img> with width/height/loading).
		 *
		 * @param string $html    Default ''.
		 * @param int    $user_id Author ID.
		 */
		'avatar_html'     => (string) apply_filters( 'starter_post_author_avatar', '', $user_id ),
	);
}

/**
 * Map embed for the company location (OpenStreetMap by default), for template-parts/embed-facade.
 *
 * @return array{src: string, link: string}|null Null when the company has no coordinates.
 */
function starter_company_map(): ?array {
	$lat = starter_get_company( 'geo.lat' );
	$lng = starter_get_company( 'geo.lng' );
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return null;
	}

	$lat  = (float) $lat;
	$lng  = (float) $lng;
	$bbox = implode( ',', array( $lng - 0.02, $lat - 0.01, $lng + 0.02, $lat + 0.01 ) );
	$map  = array(
		'src'  => add_query_arg(
			array(
				'bbox'   => rawurlencode( $bbox ),
				'layer'  => 'mapnik',
				'marker' => rawurlencode( $lat . ',' . $lng ),
			),
			'https://www.openstreetmap.org/export/embed.html'
		),
		'link' => sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=16/%1$s/%2$s', $lat, $lng ),
	);

	/**
	 * Filter the company map embed (switch provider, zoom, …).
	 *
	 * @param array{src: string, link: string} $map Iframe src and fallback link.
	 * @param float                            $lat Latitude.
	 * @param float                            $lng Longitude.
	 */
	$map = apply_filters( 'starter_company_map', $map, $lat, $lng );

	return is_array( $map ) && ! empty( $map['src'] )
		? array(
			'src'  => (string) $map['src'],
			'link' => (string) ( $map['link'] ?? '' ),
		)
		: null;
}

/**
 * Folio card orientation modifier from the attachment size.
 *
 * @param int $attachment_id Attachment ID.
 * @return string folio__item--portrait | folio__item--landscape.
 */
function starter_folio_orientation_class( int $attachment_id ): string {
	$meta = $attachment_id > 0 ? wp_get_attachment_metadata( $attachment_id ) : false;
	if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && (int) $meta['height'] > (int) $meta['width'] ) {
		return 'folio__item--portrait';
	}

	return 'folio__item--landscape';
}

/**
 * Entry of pages-map for the current request (front page, singular or the posts page).
 *
 * @return array<string, mixed>|null
 */
function starter_page_config(): ?array {
	static $cache = array();

	$key = (string) get_queried_object_id() . ( is_home() ? ':home' : '' );
	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$config = null;
	if ( is_home() && ! is_front_page() ) {
		foreach ( starter_core_pages() as $page ) {
			if ( 'blog-index' === ( $page['type'] ?? '' ) ) {
				$config = $page;
				break;
			}
		}
	} elseif ( function_exists( 'starter_current_page_config' ) ) {
		$config = starter_current_page_config();
	}

	$cache[ $key ] = $config;

	return $config;
}

/**
 * Whether the current page renders the lead form (K14: one form.js-lead per page, none on utility pages).
 *
 * Source: pages-map `lead_form`; unmapped views — true for pages, posts and the blog index.
 *
 * @return bool
 */
function starter_page_has_lead_form(): bool {
	$config = starter_page_config();

	if ( null !== $config && array_key_exists( 'lead_form', $config ) ) {
		$has = (bool) $config['lead_form'];
	} else {
		$has = is_front_page() || is_home() || is_page() || is_singular( 'post' );
	}

	/**
	 * Filter whether the current view renders the lead form.
	 *
	 * @param bool                      $has    Default decision.
	 * @param array<string, mixed>|null $config pages-map entry or null.
	 */
	return (bool) apply_filters( 'starter_page_has_lead_form', $has, $config );
}

/**
 * Lead source key of the current view (stored with the lead).
 *
 * @return string
 */
function starter_lead_source(): string {
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_home() ) {
		return 'blog';
	}
	if ( is_singular() ) {
		return (string) get_post_field( 'post_name', get_queried_object_id() );
	}

	return 'site';
}

/**
 * Breadcrumb trail for the current view: list of [ label, url ] (url '' = current page).
 *
 * @return array<int, array{0: string, 1: string}>
 */
function starter_breadcrumbs(): array {
	$trail = array( array( __( 'Главная', 'starter' ), home_url( '/' ) ) );

	if ( is_singular( 'post' ) ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			$trail[] = array( get_the_title( $posts_page ), starter_blog_url() );
		}
		$trail[] = array( single_post_title( '', false ), '' );
	} elseif ( is_page() ) {
		$page_id = (int) get_queried_object_id();
		foreach ( array_reverse( get_post_ancestors( $page_id ) ) as $ancestor ) {
			$trail[] = array( get_the_title( $ancestor ), (string) get_permalink( $ancestor ) );
		}
		$trail[] = array( get_the_title( $page_id ), '' );
	} elseif ( is_home() ) {
		$trail[] = array( single_post_title( '', false ), '' );
	} elseif ( is_archive() ) {
		$trail[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	} elseif ( is_search() ) {
		$trail[] = array( __( 'Поиск', 'starter' ), '' );
	}

	return $trail;
}

/**
 * Echo breadcrumbs (visible trail; BreadcrumbList schema is Yoast's job).
 */
function starter_the_breadcrumbs(): void {
	$trail = starter_breadcrumbs();
	if ( count( $trail ) < 2 ) {
		return;
	}

	echo '<nav class="crumbs" aria-label="' . esc_attr__( 'Хлебные крошки', 'starter' ) . '"><ol class="crumbs__list">';
	foreach ( $trail as $crumb ) {
		if ( '' === $crumb[1] ) {
			echo '<li class="crumbs__item"><span aria-current="page">' . esc_html( $crumb[0] ) . '</span></li>';
		} else {
			echo '<li class="crumbs__item"><a class="crumbs__link" href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a></li>';
		}
	}
	echo '</ol></nav>';
}

/**
 * Pagination for the main query (BEM markup, no inline styles).
 */
function starter_the_pagination(): void {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => '←<span class="visually-hidden"> ' . esc_html__( 'Назад', 'starter' ) . '</span>',
			'next_text'          => '<span class="visually-hidden">' . esc_html__( 'Вперёд', 'starter' ) . ' </span>→',
			'class'              => 'pagination',
			'screen_reader_text' => __( 'Навигация по страницам', 'starter' ),
		)
	);
}
