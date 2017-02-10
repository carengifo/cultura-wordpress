<?php
/*
Template Name: Print Processing Orders :)
*/
if (!is_user_logged_in() || !current_user_can('manage_options')) wp_die('This page is private.');
?>
<!DOCTYPE HTML>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title><?php _e('Processing Orders'); ?></title>
	<style>
		body { background:white; color:black; width: 95%; margin: 0 auto; }
		table { border: 1px solid #000; width: 100%; }
		table td, table th { border: 1px solid #000; padding: 6px; }
		article { border-top: 2px dashed #000; padding: 20px 0; }
	</style>
</head>
<body>
	<header>
		<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
		
		    <h1 class="title"><?php the_title(); ?></h1>
		
			<?php the_content(); ?>
			
		<?php endwhile; endif; ?>
	</header>
	<section>
	<?php 
	
	global $woocommerce;
	
	$args = array(
		'post_type'			=> 'shop_order',
		'post_status' 		=> 'publish',
                'posts_per_page' => -1,
		'tax_query' => array(
                    array(
                        'taxonomy' => 'shop_order_status',
                        'field' => 'slug',
                        'terms' => array('processing')
                    )
                )
	);
	
	$loop = new WP_Query( $args );
	
	while ( $loop->have_posts() ) : $loop->the_post();
	
		$order_id = $loop->post->ID;
		
		$order = new WC_Order($order_id);
		
		?>
		<article>
			<header>
		        <h2>Order #<?php echo $order_id; ?> &mdash; <time datetime="<?php the_time('c'); ?>"><?php echo the_time('d/m/Y'); ?></time></h2>
			</header>
			<table cellspacing="0" cellpadding="2">
				<thead>
					<tr>
						<th scope="col" style="text-align:left;"><?php _e('Product', 'woothemes'); ?></th>
						<th scope="col" style="text-align:left;"><?php _e('Quantity', 'woothemes'); ?></th>
						<th scope="col" style="text-align:left;"><?php _e('Price', 'woothemes'); ?></th>
					</tr>
				</thead>
				<tfoot> 
					<tr>
						<th scope="row" colspan="2" style="text-align:left; padding-top: 12px;"><?php _e('Subtotal:', 'woothemes'); ?></th>
						<td style="text-align:left; padding-top: 12px;"><?php echo $order->get_subtotal_to_display(); ?></td>
					</tr>
					<?php if ($order->order_shipping > 0) : ?><tr>
						<th scope="row" colspan="2" style="text-align:left;"><?php _e('Shipping:', 'woothemes'); ?></th>
						<td style="text-align:left;"><?php echo $order->get_shipping_to_display(); ?></td>
					</tr><?php endif; ?>
					<?php if ($order->order_discount > 0) : ?><tr>
						<th scope="row" colspan="2" style="text-align:left;"><?php _e('Discount:', 'woothemes'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->order_discount); ?></td>
					</tr><?php endif; ?>
					<?php if ($order->get_total_tax() > 0) : ?><tr>
						<th scope="row" colspan="2" style="text-align:left;"><?php _e('Tax:', 'woothemes'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->get_total_tax()); ?></td>
					</tr><?php endif; ?>
					<tr>
						<th scope="row" colspan="2" style="text-align:left;"><?php _e('Total:', 'woothemes'); ?></th>
						<td style="text-align:left;"><?php echo woocommerce_price($order->order_total); ?> <?php _e('- via', 'woothemes'); ?> <?php echo ucwords($order->payment_method); ?></td>
					</tr>
				</tfoot>
				<tbody>
					<?php echo $order->email_order_items_table(); ?>
				</tbody>
			</table>
	
			<h2><?php _e('Customer details', 'woothemes'); ?></h2>
			
			<?php if ($order->billing_email) : ?>
				<p><strong><?php _e('Email:', 'woothemes'); ?></strong> <?php echo $order->billing_email; ?></p>
			<?php endif; ?>
			<?php if ($order->billing_phone) : ?>
				<p><strong><?php _e('Tel:', 'woothemes'); ?></strong> <?php echo $order->billing_phone; ?></p>
			<?php endif; ?>
			
			<div style="float:left; width: 49%;">
			
				<h3><?php _e('Billing address', 'woothemes'); ?></h3>
				
				<p>
					<?php echo $order->get_formatted_billing_address(); ?>
				</p>
			
			</div>
			
			<div style="float:right; width: 49%;">
			
				<h3><?php _e('Shipping address', 'woothemes'); ?></h3>
				
				<p>
					<?php echo $order->get_formatted_shipping_address(); ?>
				</p>
			
			</div>
			
			<div style="clear:both;"></div>
			
		</article>
	<?php endwhile; ?>
	</section>
</body>
</html>