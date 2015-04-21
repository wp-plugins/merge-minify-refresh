<?php
/**
 * Plugin Name: Merge + Minify + Refresh
 * Plugin URI: https://wordpress.org/plugins/merge-minify-refresh
 * Description: 
 * Version: 0.7
 * Author: Marc Castles
 * Author URI: http://launchinteractive.com.au
 * License: GPL2
*/
/*
Copyright 2015  Marc Castles  (email : marc@launchinteractive.com.au)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//TODO:
//ability to prevent merging/compressing certain files
//store files date last access so admin can see old files

class MergeMinifyRefresh {
	
	private $host = '';
	private $root = '';

  public function __construct() {
    
    if(!is_dir(WP_CONTENT_DIR.'/mmr')) {
			mkdir(WP_CONTENT_DIR.'/mmr');
		}
		
		$this->root = untrailingslashit(ABSPATH);
    
    if(is_admin()) {
	    
	    add_action( 'admin_menu', array($this,'admin_menu') );
	    
	    add_action( 'admin_enqueue_scripts', array($this,'load_admin_styles') );
	    
	    add_action( 'admin_init', array($this,'merge_minify_refresh_settings_action') );
	    
	  } else {

			$this->host = $_SERVER['HTTP_HOST'];
			//php < 5.4.7 returns null if host without scheme entered
			if(mb_substr($this->host, 0, 4) !== 'http') $this->host = 'http'.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 's' : '').'://' . $this->host;
			$this->host = parse_url( $this->host, PHP_URL_HOST );
	    
	    add_action( 'compress_css',array($this,'compress_css_action'), 10, 1 );
			add_action( 'compress_js', array($this,'compress_js_action'), 10, 1 );
	    
    	add_action( 'wp_print_scripts', array($this,'inspect_scripts'), PHP_INT_MAX );
    	add_action( 'wp_print_styles', array($this,'inspect_styles'), PHP_INT_MAX );
    	
    	add_filter( 'style_loader_src', array($this,'remove_cssjs_ver'), 10, 2 );
			add_filter( 'script_loader_src', array($this,'remove_cssjs_ver'), 10, 2 );

    }
    
    register_deactivation_hook( __FILE__, array($this, 'plugin_deactivate') );
  }
  
  public function plugin_deactivate() {
	  if(is_dir(WP_CONTENT_DIR.'/mmr')) {
			$this->rrmdir(WP_CONTENT_DIR.'/mmr'); 
		}
  }
  
  private function rrmdir($dir) { 
	  foreach(glob($dir . '/*') as $file) { 
	    if(is_dir($file)) rrmdir($file); else unlink($file); 
	  } rmdir($dir); 
	}
  
  public function load_admin_styles() {
    wp_enqueue_style( 'merge-minify-refresh', plugins_url('admin.css', __FILE__) );
    wp_enqueue_script( 'merge-minify-refresh', plugins_url('admin.js', __FILE__) );
  }
  
  public function admin_menu() {
	  add_options_page( 'Merge + Minify + Refresh Settings', 'Merge + Minify + Refresh', 'manage_options', 'merge-minify-refresh', array($this,'merge_minify_refresh_settings') );
  }
  public function merge_minify_refresh_settings_action() {
	  if(isset($_GET['page'],$_GET['purge']) && $_GET['page']=='merge-minify-refresh') {
			if($_GET['purge'] == 'all') {
				$this->rrmdir(WP_CONTENT_DIR.'/mmr'); 
			} else {
				if(is_file(WP_CONTENT_DIR.'/mmr/'.$_GET['purge'])) {
					unlink(WP_CONTENT_DIR.'/mmr/'.$_GET['purge']);
				}
				if(is_file(WP_CONTENT_DIR.'/mmr/'.$_GET['purge'].'.log')) {
					unlink(WP_CONTENT_DIR.'/mmr/'.$_GET['purge'].'.log');
				}
			}
			wp_redirect( 'options-general.php?page=merge-minify-refresh' );
			exit;
		}
	}
  public function merge_minify_refresh_settings() {
	  if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		

		
		//echo '<pre>';var_dump(_get_cron_array()); echo '</pre>';

		$files = glob(WP_CONTENT_DIR.'/mmr/*.{js,css}', GLOB_BRACE);
		
		echo '<div id="merge-minify-refresh"><h2>Merge + Minify + Refresh Settings</h2>';
		
		echo '<p>When a CSS or JS file is modified MMR will automatically re-process the files. However, when a dependancy changes these files may become stale.</p>';
		
		if(count($files) > 0) {
			
			echo '<a href="?page=merge-minify-refresh&purge=all" class="button button-secondary">Purge All</a>';
			
			echo '<h4>The following Javascript files have been processed:</h4>';
			
			
			echo '<ul class="processed">';
			
			$css = null;
			
			foreach($files as $file) {
				$ext = pathinfo($file, PATHINFO_EXTENSION);
				
				
				
				if($css == null && $ext == 'css') {
					echo '</ul><h4>The following CSS files have been processed:</h4><ul class="processed">';
					$css = true;
				}
				
				if(in_array($ext, array('js','css'))) {
					
					$scheduled = '';
					
					if($ext == 'css' && substr($file, -8) != '.min.css' || $ext == 'js' && substr($file, -7) != '.min.js') {
						$schedule = wp_next_scheduled( 'compress_'.$ext, array($file) );
						
						if($schedule !== false) {
							$scheduled = ' <span class="dashicons dashicons-clock" title="Compression Scheduled"></span>';
						}
					}
					
					$log = file_get_contents(preg_replace('/(.*).min.(js|css)$/','$1.$2',$file).'.log');
					
					$error = '';
					if(strpos($log,'COMPRESSION FAILED') !== false || strpos($log,'UNABLE TO COMPRESS') !== false) {
						$error = ' error';
					}
					
					echo '<li><span class="filename'.$error.'">'.basename($file).$scheduled.'</span> <a href="#" class="log button button-primary">View Log</a> <a href="?page=merge-minify-refresh&purge='.basename($file).'" class="button button-secondary">Purge</a><pre>'.$log.'</pre></li>';
					
				}
			}
			
			echo '</ul>';
		} else {
			echo '<p><strong>No files have been processed</strong></p>';
		}
		
		echo '</div>';
		
  }
  
	public function remove_cssjs_ver( $src ) {
		if( strpos( $src, '?ver=' ) )
			$src = remove_query_arg( 'ver', $src );
		return $src;
	}
	
	private function host_match( $url ) {
		if( empty($url) ) {
			return false;
		}

		$url = $this->ensure_scheme($url);

		$url_host = parse_url( $url, PHP_URL_HOST );
		
		if(  !$url_host || $url_host == $this->host ) {
			return true;
		} else {
			return false;
		}
	}
	
	//php < 5.4.7 parse_url returns null if host without scheme entered
	private function ensure_scheme($url) {
		return preg_replace("/(http(s)?:\/\/|\/\/)(.*)/i", "http$2://$3", $url);
	}
	
	private function remove_continuations($js) {
		return preg_replace_callback(
	    '#//[^\\r\\n]*|/\\*.*?\\*/|("(?:\\\\.|[^\\\\"])*"|\'(?:\\\\.|[^\\\\\'])*\')#s',
	    function($matches) {
		    $str = $matches[1];
		    if (empty($str)) return $matches[0];
		    return preg_replace('#\\\\(?:\\r\\n?|\\n)#', '', $str);
			},
	    $js
		);
	}
  
  public function inspect_scripts() {

		global $wp_scripts;
		
		$scripts = wp_clone( $wp_scripts );
    
    $scripts->all_deps($scripts->queue);
    
    $header = array();
    $footer = array();
    
    // Loop through queue and determine groups of handles & latest modified date
    foreach( $scripts->to_do as $handle ) :
    
    	$script_path = parse_url($this->ensure_scheme($wp_scripts->registered[$handle]->src));
    	
    	$is_footer = isset($wp_scripts->registered[$handle]->extra['group']);
    	
    	$array = ($is_footer ? 'footer' : 'header');

	    if( $this->host_match($wp_scripts->registered[$handle]->src)) { //is a local script

				if(isset(${$array}[count(${$array})-1]['handle']) || count(${$array}) == 0  ) {
					array_push(${$array}, array('modified'=>0,'handles'=>array(), 'data'=>''));
		    }

				//save any data added with wp_localize_script
		    if(isset($wp_scripts->registered[$handle]->extra['data'])) {
					${$array}[count(${$array})-1]['data'] .= $wp_scripts->registered[$handle]->extra['data'];
				}
				
				$modified = 0;
				
				if(is_file($this->root.$script_path['path'])) {
					$modified = filemtime($this->root.$script_path['path']);
				}

			  array_push(${$array}[count(${$array})-1]['handles'], $handle);

		   	if($modified > ${$array}[count(${$array})-1]['modified']) {
			   	${$array}[count(${$array})-1]['modified'] = $modified;
		   	}
		    
		  } else { //external script

				array_push(${$array}, array('handle'=>$handle,'data'=>''));

		  }

		  wp_dequeue_script($handle); //dequeue all scripts as we will enqueue in order below

		endforeach;
		
		

		//loop through header scripts and merge + schedule wpcron
		for($i=0,$l=count($header);$i<$l;$i++) {

				if(!isset($header[$i]['handle'])) {

					$hash = md5(implode('',$header[$i]['handles']));					
					
					$file_path = '/mmr/'.$hash.'-'.$header[$i]['modified'].'.js';
					
					$full_path = WP_CONTENT_DIR.$file_path;
					
					$min_path = '/mmr/'.$hash.'-'.$header[$i]['modified'].'.min.js';
					
					$min_exists = file_exists(WP_CONTENT_DIR.$min_path);
					
					if(!file_exists($full_path) && !$min_exists) {
	
						$js = '';
						
						$log = "";
						
						foreach( $header[$i]['handles'] as $handle ) :
						
							$log .= " - ".$handle." - ".$wp_scripts->registered[$handle]->src."\n";
		
							$script_path = parse_url($this->ensure_scheme($wp_scripts->registered[$handle]->src));
							
							$contents = file_get_contents($this->root.$script_path['path']);
							
							// Remove Javascript String Continuations
							//$contents = preg_replace("/['|\"].[^'|^\"]*(\\r?\n|\r).[^'|^\"]*['|\"]/", "", $contents);
							//$contents = preg_replace("/\\r?\n|\r/", '', $contents);
							
							$contents = $this->remove_continuations($contents);
							
							// Remove the BOM
							$contents = preg_replace("/^\xEF\xBB\xBF/", '', $contents);
							
							$js .= $contents . "\n";
		
						endforeach;
						
						//remove existing expired files
						array_map('unlink', glob(WP_CONTENT_DIR.'/mmr/'.$hash.'-*'));
						
						file_put_contents($full_path , $js);
						
						file_put_contents($full_path.'.log', date('c')." - MERGED:\n".$log);

						if(function_exists('exec')) {
							wp_clear_scheduled_hook('compress_js', array($full_path) );
							wp_schedule_single_event( time(), 'compress_js', array($full_path) );
						} else {
							file_put_contents($full_path.'.log', date('c')." - UNABLE TO COMPRESS (PHP exec not available)\n",FILE_APPEND);
						}
					}
					
					if($min_exists) {
						wp_register_script('header-'.$i, WP_CONTENT_URL.$min_path);
					} else {
						wp_register_script('header-'.$i, WP_CONTENT_URL.$file_path);
					}

					//set any existing data that was added with wp_localize_script
					if($header[$i]['data'] != '') {
						$wp_scripts->registered['header-'.$i]->extra['data'] = $header[$i]['data'];
					}
					
					wp_enqueue_script('header-'.$i);
				
				} else { //external
					
					wp_enqueue_script($header[$i]['handle']);
					
				}
		}
		
		//loop through footer scripts and merge + schedule wpcron
		for($i=0,$l=count($footer);$i<$l;$i++) {
				
			if(!isset($footer[$i]['handle'])) {

				$hash = md5(implode('',$footer[$i]['handles']));
				
				$file_path = '/mmr/'.$hash.'-'.$footer[$i]['modified'].'.js';
					
				$full_path = WP_CONTENT_DIR.$file_path;
				
				$min_path = '/mmr/'.$hash.'-'.$footer[$i]['modified'].'.min.js';
				
				$min_exists = file_exists(WP_CONTENT_DIR.$min_path);
				
				if(!file_exists($full_path) && !$min_exists) {

					$js = '';
					
					$log = "";
					
					foreach( $footer[$i]['handles'] as $handle ) :
					
						$log .= " - ".$handle." - ".$wp_scripts->registered[$handle]->src."\n";
	
						$script_path = parse_url($this->ensure_scheme($wp_scripts->registered[$handle]->src));
						
						$contents = file_get_contents($this->root.$script_path['path']);
						
						// Remove Javascript String Continuations
						//$contents = preg_replace("/['|\"].[^'|^\"]*(\\r?\n|\r).[^'|^\"]*['|\"]/", "", $contents);
						//$contents = preg_replace("/\\r?\n|\r/", '', $contents);
						$contents = $this->remove_continuations($contents);
						
						// Remove the BOM
						$contents = preg_replace("/^\xEF\xBB\xBF/", '', $contents);
						
						$js .= $contents . "\n";
	
					endforeach;
					
					//remove existing expired files
					array_map('unlink', glob(WP_CONTENT_DIR.'/mmr/'.$hash.'-*'));

					file_put_contents($full_path , $js);
					
					file_put_contents($full_path.'.log', date('c')." - MERGED:\n".$log);
					
					if(function_exists('exec')) {
						wp_clear_scheduled_hook('compress_js', array($full_path));
						wp_schedule_single_event( time(), 'compress_js', array($full_path) );
					} else {
						file_put_contents($full_path.'.log', date('c')." - UNABLE TO COMPRESS (PHP exec not available)\n",FILE_APPEND);
					}

				}
				
				if($min_exists) {
					wp_register_script('footer-'.$i, WP_CONTENT_URL.$min_path, false, false, true);
				} else {
					wp_register_script('footer-'.$i, WP_CONTENT_URL.$file_path, false, false, true);
				}
				
				//set any existing data that was added with wp_localize_script
				if($footer[$i]['data'] != '') {
					$wp_scripts->registered['footer-'.$i]->extra['data'] = $footer[$i]['data'];
				}
				
				wp_enqueue_script('footer-'.$i);
				
			} else { //external
					
				wp_enqueue_script($header[$i]['handle']);
					
			}
		} 

  }
  
  public function inspect_styles() {

		global $wp_styles;		

		$styles = wp_clone( $wp_styles );
    
    $styles->all_deps($styles->queue);
    
    $header = array();
    
    // Loop through queue and determine groups of handles & latest modified date
    foreach( $styles->to_do as $handle ) :
    
    	$style_path = parse_url($this->ensure_scheme($wp_styles->registered[$handle]->src));

	    if( $this->host_match($wp_styles->registered[$handle]->src) ) { //is a local script

				if(isset($header[count($header)-1]['handle']) || count($header) == 0 || $header[count($header)-1]['media'] != $wp_styles->registered[$handle]->args ) {
					$media = isset($wp_styles->registered[$handle]->args) ? $wp_styles->registered[$handle]->args : 'all';

					array_push($header, array('modified'=>0,'handles'=>array(),'media'=>$media ));
		    }
		    
		    $media_type = $wp_styles->registered[$handle]->args;

				$modified = 0;
				
				if(is_file($this->root.$style_path['path'])) {
					$modified = filemtime($this->root.$style_path['path']);
				}

			  array_push($header[count($header)-1]['handles'], $handle);

		   	if($modified > $header[count($header)-1]['modified']) {
			   	$header[count($header)-1]['modified'] = $modified;
		   	}
		    
		  } else { //external script

				array_push($header, array('handle'=>$handle));
				$media_type = null;

		  }

			wp_dequeue_style($handle); //dequeue all styles as we will enqueue in order below

		endforeach;

		//loop through header styles and merge + schedule wpcron
		for($i=0,$l=count($header);$i<$l;$i++) {

				if(!isset($header[$i]['handle'])) {
					
					$hash = md5(implode('',$header[$i]['handles']));

					$file_path = '/mmr/'.$hash.'-'.$header[$i]['modified'].'.css';

					$full_path = WP_CONTENT_DIR.$file_path;
					
					$min_path = '/mmr/'.$hash.'-'.$header[$i]['modified'].'.min.css';
					
					$min_exists = file_exists(WP_CONTENT_DIR.$min_path);
					
					if(!file_exists($full_path) && !$min_exists) {

						$css = '';
						
						$log = "";

						foreach( $header[$i]['handles'] as $handle ) :

							$style_path = parse_url($this->ensure_scheme($wp_styles->registered[$handle]->src));
							
							$log .= " - ".$handle." - ".$wp_styles->registered[$handle]->src."\n";
							
							$css_contents = file_get_contents($this->root.$style_path['path']);
							
							// Remove the BOM
							$css_contents = preg_replace("/^\xEF\xBB\xBF/", '', $css_contents);
							
							// prevent YUI Compressor stripping 0 second units
							$css_contents = str_replace(' 0s', ' .00s', $css_contents);
							
							//convert relative paths to absolute & ignore data: or absolute paths (starts with /)
							$css_contents =preg_replace("/url\(\s*['\"]?(?!data:)(?!http)(?![\/'\"])(.+?)['\"]?\s*\)/i", "url(".dirname($style_path['path'])."/$1)", $css_contents);
							
							$css .= $css_contents . "\n";
		
						endforeach;
						
						//remove existing out of date files
						array_map('unlink', glob(WP_CONTENT_DIR.'/mmr/'.$hash.'-*'));

						file_put_contents($full_path , $css);
						
						file_put_contents($full_path.'.log', date('c')." - MERGED:\n".$log);

						if(function_exists('exec')) {
							wp_clear_scheduled_hook('compress_css', array($full_path) );
							wp_schedule_single_event( time(), 'compress_css', array($full_path) );
						} else {
							file_put_contents($full_path.'.log', date('c')." - UNABLE TO COMPRESS (PHP exec not available)\n",FILE_APPEND);
						}
					}
					
					if($min_exists) {
						wp_register_style('header-'.$i, WP_CONTENT_URL.$min_path,false,false,$header[$i]['media']);
					} else {
						wp_register_style('header-'.$i, WP_CONTENT_URL.$file_path,false,false,$header[$i]['media']);
					}
					
					wp_enqueue_style('header-'.$i);
				
				} else { //external
					
					wp_enqueue_style($header[$i]['handle']);
					
				}

		}
	  
	}
	
	public function compress_css_action($full_path) {
	
		if(is_file($full_path)) {
	
			file_put_contents($full_path.'.log', date('c')." - COMPRESSING CSS\n",FILE_APPEND);
			
			$file_size_before = filesize($full_path);
			
			$cmd = 'java -jar \''.WP_PLUGIN_DIR.'/merge-minify-refresh/yuicompressor.jar\' \''.$full_path.'\' -o \''.$full_path.'.tmp\'';
			exec($cmd . ' 2>&1', $output);
			
			if(count($output) == 0) {
				rename($full_path.'.tmp',str_replace('.css','.min.css',$full_path));
				unlink($full_path);
				$file_size_after = filesize($full_path);
				file_put_contents($full_path.'.log', date('c')." - COMPRESSION COMPLETE - ".$this->human_filesize($file_size_before-$file_size_after)." saved\n",FILE_APPEND);
			} else {
				ob_start();
				var_dump($output);
				$error=ob_get_contents();
				ob_end_clean();
				
				file_put_contents($full_path.'.log', date('c')." - COMPRESSION FAILED\n".$error,FILE_APPEND);
				unlink($full_path.'.tmp');
			}
		}
	}
	
	public function compress_js_action($full_path) {

		if(is_file($full_path)) {
			file_put_contents($full_path.'.log', date('c')." - COMPRESSING JS\n",FILE_APPEND);
			
			$file_size_before = filesize($full_path);
			
			$cmd = 'java -jar \''.WP_PLUGIN_DIR.'/merge-minify-refresh/closure-compiler.jar\' --warning_level QUIET --js \''.$full_path.'\' --js_output_file \''.$full_path.'.tmp\'';
		
			exec($cmd . ' 2>&1', $output);
			
			if(count($output) == 0) {
				rename($full_path.'.tmp',str_replace('.js','.min.js',$full_path));
				unlink($full_path);
				$file_size_after = filesize($full_path);
				file_put_contents($full_path.'.log', date('c')." - COMPRESSION COMPLETE - ".$this->human_filesize($file_size_before-$file_size_after)." saved\n",FILE_APPEND);
			} else {
				
				ob_start();
				var_dump($output);
				$error=ob_get_contents();
				ob_end_clean();
				
				file_put_contents($full_path.'.log', date('c')." - COMPRESSION FAILED\n".$error,FILE_APPEND);
				unlink($full_path.'.tmp');
			}
		}
	}
    
  //thanks to http://php.net/manual/en/function.filesize.php#106569
	private function human_filesize($bytes, $decimals = 2) {
	  $sz = 'BKMGTP';
	  $factor = floor((strlen($bytes) - 1) / 3);
	  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
	}
}
 
$mergeminifyrefresh = new MergeMinifyRefresh();