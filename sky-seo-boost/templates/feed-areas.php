<?php
/**
 * RSS2 Feed Template for Sky Areas
 */

header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';

/**
 * Fires between the xml and rss tags in a feed.
 */
do_action( 'rss_tag_pre', 'rss2' );
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	<?php do_action( 'rss2_ns' ); ?>
>

<channel>
	<title><?php wp_title_rss(); ?> - Areas We Cover</title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss('url'); ?></link>
	<description><?php bloginfo_rss("description"); ?> - Areas We Cover</description>
	<lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
	<?php do_action( 'rss2_head'); ?>

	<?php
	$args = array(
		'post_type' => 'sky_areas',
		'posts_per_page' => get_option('posts_per_rss'),
		'post_status' => 'publish',
		'orderby' => 'date',
		'order' => 'DESC'
	);
	
	$query = new WP_Query($args);
	
	while($query->have_posts()) : $query->the_post();
	?>
	<item>
		<title><?php the_title_rss(); ?></title>
		<link><?php the_permalink_rss(); ?></link>
		<comments><?php comments_link_feed(); ?></comments>
		<dc:creator><![CDATA[<?php the_author(); ?>]]></dc:creator>
		<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
		<?php the_category_rss('rss2'); ?>
		<guid isPermaLink="false"><?php the_guid(); ?></guid>
		<?php if (get_option('rss_use_excerpt')) : ?>
		<description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
		<?php else : ?>
		<description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
		<?php $content = get_the_content_feed('rss2'); ?>
		<?php if ( strlen( $content ) > 0 ) : ?>
		<content:encoded><![CDATA[<?php echo $content; ?>]]></content:encoded>
		<?php else : ?>
		<content:encoded><![CDATA[<?php the_excerpt_rss(); ?>]]></content:encoded>
		<?php endif; ?>
		<?php endif; ?>
		<?php if ( comments_open() || pings_open() ) : ?>
		<wfw:commentRss><?php echo esc_url( get_post_comments_feed_link(null, 'rss2') ); ?></wfw:commentRss>
		<slash:comments><?php echo get_comments_number(); ?></slash:comments>
		<?php endif; ?>
		<?php rss_enclosure(); ?>
		<?php do_action('rss2_item'); ?>
	</item>
	<?php endwhile; wp_reset_postdata(); ?>
</channel>
</rss>