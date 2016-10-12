<?php
/**
 * @package TSF_Extension_Manager_Extension\Monitor\Admin\Views
 */
namespace TSF_Extension_Manager_Extension;

defined( 'ABSPATH' ) and $_class = monitor_class() and $this instanceof $_class or die;

?>
<div class="tsfem-extensions-panes-row tsfem-flex tsfem-flex-row">
<?php
	tsf_extension_manager()->do_pane_wrap(
		__( 'Issues', 'the-seo-framework-extension-manager' ),
		$this->get_issues_overview(),
		array(
			'full' => true,
			'collapse' => true,
			'move' => false,
			'ajax' => true,
			'ajax_id' => 'tsfem-feed-ajax',
		)
	);
?>
</div>
<div class="tsfem-extensions-panes-row tsfem-flex tsfem-flex-row">
<?php
	tsf_extension_manager()->do_pane_wrap(
		__( 'Points of Interest', 'the-seo-framework-extension-manager' ),
		$this->get_poi_overview(),
		array(
			'full' => true,
			'collapse' => true,
			'move' => false,
			'ajax' => true,
			'ajax_id' => 'tsfem-actions-ajax',
		)
	);
?>
</div>
<div class="tsfem-extensions-panes-row tsfem-flex tsfem-flex-row">
<?php
	tsf_extension_manager()->do_pane_wrap(
		__( 'Statistics', 'the-seo-framework-extension-manager' ),
		$this->get_statistics_overview(),
		array(
			'full' => true,
			'collapse' => true,
			'move' => false,
			'ajax' => true,
			'ajax_id' => 'tsfem-extensions-ajax',
		)
	);
?>
</div>
<?php