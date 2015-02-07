<?php
// displays the Languages admin panel
?>
<div class="wrap"><?php
	screen_icon('options-general'); ?>

	<h2 class="nav-tab-wrapper"><?php
	// display tabs
	foreach ($tabs as $key => $name)
		printf(
			'<a href="options-general.php?page=mlang&amp;tab=%s" class="nav-tab %s">%s</a>',
			$key,
			$key == $this->active_tab ? 'nav-tab-active' : '',
			$name
		);?>
	</h2><?php

	switch($this->active_tab) {

		case 'lang': // Languages tab
		case 'strings': // string translations tab
		case 'settings': // settings tab
			include(PLL_ADMIN_INC.'/view-tab-' . $this->active_tab . '.php');
			break;

		default:
			do_action('pll_settings_active_tab_' . $this->active_tab);
			break;
	}?>

</div><!-- wrap -->
