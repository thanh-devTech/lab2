<!DOCTYPE html>

<html class="no-js" <?php language_attributes(); ?>>

<head>
	<title><?php bloginfo('name'); ?> <?php wp_title("-",true); ?></title>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" >
	<link href="<?php echo get_stylesheet_uri()?>?12" rel="stylesheet"/>

	<?php wp_head(); ?>

</head>

<body <?php body_class(); ?>>