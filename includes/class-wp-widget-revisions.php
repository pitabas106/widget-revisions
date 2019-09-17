<?php
/**
 * Author: NetTantra
 * @package Widget Revisions
 */


if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class WPWidgetRevisions {

	public $widget_revisions_table;
	public $option_table;

	function __construct() {
		add_action( 'widget_update_callback', array($this, 'ntwr_update_callback'), 10, 4);
		add_action( 'in_widget_form', array($this, 'ntwr_extend_widget_form'), 10, 3);
		add_action( 'admin_enqueue_scripts', array($this, 'ntwr_load_wp_admin_style'));

		add_action( 'wp_ajax_wp_widget_revisions_restore_ajax', array($this, 'ntwr_restore_revision_data'));
		add_action( 'wp_ajax_nopriv_wp_widget_revisions_restore_ajax', array($this, 'ntwr_restore_revision_data'));

		add_action( 'wp_ajax_wp_widget_revisions_ajax', array($this, 'ntwr_get_ajax_data'));
		add_action( 'wp_ajax_nopriv_wp_widget_revisions_ajax', array($this, 'ntwr_get_ajax_data'));

		add_action( 'sidebar_admin_setup', array($this, 'ntwr_delete_widget_revisions'));

		global $wpdb;
		$this->widget_revisions_table = $wpdb->prefix . "widget_revisions";
		$this->option_table = $wpdb->prefix . "options";
	}

	public function ntwr_delete_widget_revisions() {
		if ( isset( $_POST['delete_widget'] ) ) {
			$widget_id_base = $_POST['id_base'];
			$context['widget_id_base'] = $widget_id_base;
			$widget = $this->getWidgetByIdBase( $widget_id_base );
			if ( $widget ) {
				global $wpdb;
				$delete_widget_revisions = $wpdb->delete($this->widget_revisions_table,
				array(
					'option_name' 	=> $widget->option_name,
					 'widget_id' 		=> $widget->number
			 ));
			 if($delete_widget_revisions) {
				 echo __('Deleted!', 'widget-revisions');
			 }
			}
		}
	}

	public function getWidgetByIdBase( $widget_id_base ) {

		$widget_factory = isset( $GLOBALS['wp_widget_factory'] ) ? $GLOBALS['wp_widget_factory'] : false;

		if ( ! $widget_factory ) {
			return false;
		}

		foreach ( $widget_factory->widgets as $one_widget ) {
			if ( $one_widget->id_base == $widget_id_base ) {
				return $one_widget;
			}
		}

		return false;

	}


	public function ntwr_update_callback( $instance, $new_data, $old_data, $obj ) {
		if($obj) {
			$option_name = $obj->option_name;
			$widget_id = $obj->number;
		}
		if(isset($instance)) {
			global $wpdb;
			$success = $wpdb->insert($this->widget_revisions_table, array(
				"widget_id" 			=> $widget_id,
				"option_name" 		=> $option_name,
				"option_value" 		=> maybe_serialize($instance),
				"widget_author" 	=> 1,
		 ));
		}
			return $instance;
	}


	public function ntwr_extend_widget_form( $widget, $return, $instance ) {

		$instance = wp_parse_args( $instance, array(
			'ids' 						=> '',
			'classes' 				=> '',
			'classes-defined' => array(),
		) );

		$get_current_screen = get_current_screen();
		
		$widget->number = is_numeric($widget->number) ? $widget->number : 0;
		$get_current_screen_base = isset($get_current_screen->base) ? $get_current_screen->base : '';

		if($widget->option_name && $widget->number && $get_current_screen_base != 'customize') {
			global $wpdb;


			$total_result = $wpdb->get_row( "SELECT COUNT(id) as total_row
				FROM $this->widget_revisions_table
				WHERE option_name = '{$widget->option_name}'
				AND widget_id = $widget->number;" );


				if($total_result->total_row > 0) {
					add_thickbox();
					echo '<div id="ntwr-modal-window" style="display:none;"> <div class="wr-modal-content"></div><div class="nt-wr-loading"></div></div>';
					echo '<div class="wcssc" style="clear: both; margin: 1em 0;"><a data-id="'.$widget->number.'" data-name="'.$widget->option_name.'" title="'.$widget->name.' - Widget Revisions" href="#TB_inline?width=750&height=470&inlineId=ntwr-modal-window" class="wr-revision-window button button-primary thickbox">'.__('View All Revisions', 'widget-revisions').'</a></div>';
				}
		}
		return $return;
	}


	public function ntwr_load_wp_admin_style($hook) {

		if($hook != 'widgets.php') {
				return;
		}
		$ajax_nonce = wp_create_nonce( "wp_widget_revisions_nonce" );

		wp_enqueue_style( 'main-css',  plugin_dir_url( __FILE__ ) . '../assets/css/admin-wr-main.css', '1.0' );
	  wp_register_script( 'ccc-main-js',  plugin_dir_url( __FILE__ ) . '../assets/js/admin-wr-main.js', '1.0' );
	  wp_localize_script( 'ccc-main-js', 'wp_widget_revisions', array(
	    'ajax_url' => admin_url( 'admin-ajax.php' ),
	    'nonce'           => $ajax_nonce,
	   ) );
	  wp_enqueue_script( 'ccc-main-js' );

	}


	public function ntwr_strtotime($str) {
	  $tz_string = get_option('timezone_string');
	  $tz_offset = get_option('gmt_offset', 0);

	  if (!empty($tz_string)) {
	      // If site timezone option string exists, use it
	      $timezone = $tz_string;

	  } elseif ($tz_offset == 0) {
	      // get UTC offset, if it isn’t set then return UTC
	      $timezone = 'UTC';

	  } else {
	      $timezone = $tz_offset;

	      if(substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
	          $timezone = "+" . $tz_offset;
	      }
	  }

	  $datetime = new DateTime($str, new DateTimeZone($timezone));
	  return $datetime->format('U');
	}


	public function ntwr_human_time_ago($from_time) {
		$revision_datetime = $this->ntwr_strtotime($from_time);
		return esc_html( human_time_diff( $revision_datetime, current_time('timestamp', 1) ) ) . __( ' ago', 'widget-revisions' );
	}


	public function ntwr_get_ajax_data() {

		$posted_data = $_POST;
		$posted_data = stripslashes_deep( $posted_data );

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_widget_revisions_nonce' ) ) {
			die( 'Security check!' );
		}

		$option_name 	= $posted_data['option_name'];
		$widget_id 		= $posted_data['widget_id'];
		global $wpdb;

		$lists = $wpdb->get_results( "SELECT *
			FROM $this->widget_revisions_table
			WHERE option_name 	= '{$option_name}'
			AND widget_id 			= $widget_id
			ORDER BY id DESC;" );
		$data = '';
		if(count($lists) > 0) {

			$data .= '<table style="width: 100%;">';
			$data .= '<thead>
				<tr>
				<th style="width: 100%;">'.__('Revision Data', 'widget-revisions').'</th>
				</tr>
			</thead>';
			foreach ($lists as $key => $value) {
				$get_avatar = get_avatar( get_the_author_meta( $value->widget_author ), 22 );
				$author_obj = get_user_by('id', $value->widget_author);

				if($author_obj->data->display_name) {
					$get_username = $author_obj->data->display_name;
				} else {
					$get_username = $author_obj->data->user_nicename;
				}
				$restore_ajax_nonce = wp_create_nonce( "wp_widget_revisions_restore_nonce" );
				$data .= '<tr>';
				$data .= '<td style="padding-bottom: 7px;"><table style="width: 100%;" class="wp-list-table widefat wr-table">';
				$data .= '<thead>
					<tr style="background: #EEE;">
					<th style="width: 30%;">'.__('Key', 'widget-revisions').'</th>
					<th style="width: 70%;">'.__('Value', 'widget-revisions').'</th>
					</tr>
				</thead>';
				$array_option_value = maybe_unserialize($value->option_value);
				foreach($array_option_value as $k => $v) {
					$data .= '<tr>';
					$data .= '<td style="padding: 4px 10px;">'.$k .'</td>';
					$data .= '<td style="padding: 4px 10px;">'.htmlspecialchars($v) .'</td>';
					$data .= '</tr>';
				}
				$data .= '<tr class="footer-border">';
				$data .= '<td colspan="2"><span>'.$get_avatar.'</span>&nbsp;&#9679;&nbsp;<span>'.$get_username.'</span>,&nbsp;'. $this->ntwr_human_time_ago($value->creation_timestamp).' ('.$value->creation_timestamp.')';

				$data .= '&nbsp;&nbsp;<a data-nonce="'.$restore_ajax_nonce.'" data-widget-id="'.$value->widget_id.'" data-name="'.$value->option_name.'" data-revision-id="'.$value->id.'"  href="javascript:void(0);" class="button button-primary nt-wr-restore-btn">'.__('Restore To This Version', 'widget-revisions').'</a>
				<span class="nt-wr-restore-revision-msg"><span class="nt-wr-loader" style="display: none;"></span></span>
				</td>';
				$data .= '</tr>';

				$data .= '</table></td>';

				$data .= '</tr>';
			}
			$data .= '</table>';

			$result_data = json_encode(array(
				'error' 	=> false,
				'msg' 		=>  __('Data feched succesfully!', 'widget-revisions'),
				'data' 		=> $data
			 ));
		} else {
			$result_data = json_encode(array(
				'error' 	=> true,
				'msg' 		=>  __('No revision found!', 'widget-revisions'),
				'data' 		=> ''
			 ));
		}
		 echo $result_data;
		 exit;
	}


	public function ntwr_restore_revision_data() {
		$data = stripslashes_deep($_POST);

		if ( ! wp_verify_nonce( $_POST['nonce'], 'wp_widget_revisions_restore_nonce' ) ) {
			die( 'Security check!' );
		}
		$error = '';
		$revision_id = $data['revision_id'];
		$option_name = $data['option_name'];
		$widget_id = $data['widget_id'];
		$nonce = $data['nonce'];
		if(!$revision_id || !$option_name || !$widget_id) {
			$message 		= __( "Can't proceed this time.", 'widget-revisions');
			$error 			= false;
		}

		if(!$error) {
			global $wpdb;
			$get_revision_data  = $wpdb->get_row( "SELECT *
				FROM $this->widget_revisions_table
				WHERE option_name = '{$option_name}'
				AND widget_id 		= $widget_id
				AND id 						= $revision_id " );

			$get_option_widget_data = $wpdb->get_row( "SELECT *
				FROM $this->option_table
				WHERE option_name = '{$option_name}'" );

			$revision_widget_id = $get_revision_data->widget_id;
			$get_revision_option_value = maybe_unserialize($get_revision_data->option_value);
			
			if($get_option_widget_data) {
				$array_option_widget_data = maybe_unserialize($get_option_widget_data->option_value);
			}

			// Restore revision data to current data
			$array_option_widget_data[$revision_widget_id] = $get_revision_option_value;
			$update_revision_widget_data = maybe_serialize($array_option_widget_data);

			global $wpdb;
			$updated = $wpdb->update($this->option_table,
				array(
					'option_name'		=>	$option_name,
					'option_value'	=>	$update_revision_widget_data
				),
				array(
					'option_name'		=>	$option_name
				)
			);

			if ( false === $updated ) {
					$message 	= __( "Error in restore widget.", 'widget-revisions');
					$error 		= true;
			} else {
			    $message 	= __( "Revision has been restored successfully.", 'widget-revisions');
					$error 		= false;
			}
		}

		$output = json_encode(array(
			'error' => $error,
			'msg' 	=>  $message,
			'data' 	=> ''
		));

		echo $output;
		exit;
	}

}
