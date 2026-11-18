<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Tr_Cache_Class
{
	static private $instance = null;

	static $scripts = array();
	static $styles  = array();
	static $script_file = 'cache/site_script.js';
	static $style_file  = 'cache/site_css.css';
	static $option = array();
	static $needupdate = false;
	static $has_run = false;


	public function __construct()
	{
        global $tr_cache_options;
		if (function_exists('domain_mapping_siteurl')) {
			define('TR_WP_SITE_URL',domain_mapping_siteurl());
			define('TR_WP_CONTENT_URL',str_replace(get_original_url(TR_WP_SITE_URL),TR_WP_SITE_URL,content_url()));
		} else {
			define('TR_WP_SITE_URL',site_url());
			define('TR_WP_CONTENT_URL',content_url());
		}
        if(!is_array($tr_cache_options))
        {
            $tr_cache_options = get_option('trcs_cache',array());
        }
	}

	public static function instance()
	{
		if(self::$instance == null)
		{
			self::$instance = new Tr_Cache_Class();
		}
		return self::$instance;
	}

	public function template_redirect()
	{
		ob_start(array(&$this,'autoptimize_end'));
	}

	public function script_loader_tag($tag, $handle, $src)
	{
        if(strpos($src,'/jquery.js')===false)
        {
            $tag = str_replace('<script','<script async ',$tag);
        }

		return $tag;
	}

	public function autoptimize_end($content)
	{
		global $tr_cache_options;
		if(self::$has_run)return $content;
		$starttime = microtime(true);
		if($tr_cache_options['optimize_js'] && !isset($_GET['et_fb']))
		{
			$content = $this->_optimize_js($content);
		}
		if($tr_cache_options['optimize_css'])
		{
			$content = $this->_optimize_css($content);
		}
		self::$has_run= true;
		//$content .= '<!-- optimize in ' . (microtime(true) - $starttime) .' -->';
		return $content;
	}

	function _optimize_js($content)
	{
		global $tr_cache_options;
        $exclude = !empty($tr_cache_options['exclude_js'])? $tr_cache_options['exclude_js'] : false;
        $include = !empty($tr_cache_options['include_js'])? $tr_cache_options['include_js'] : false;

		if($include)
		{
            $include = str_replace("\r\n","\n",$include);
			$include = explode("\n",$include);
		}


		if(preg_match_all('/\<script[^\>]*src\=[\'|\"]+([^\'|^\"]*\.js)[^\'|^\"]*[\'|\"]+[^\<]*\<\/script\>[\n]?/',$content,$matches))
		{
			$time_string = '';
			foreach($matches[0] as $i => $m)
			{
				$url     = $matches[1][$i];
				$filename = basename($url);
                if($exclude)
                {
                    if(strpos($exclude,$filename)!==false){
                        continue;
                    }
                }else if($include){
                    if(!in_array($filename, $include)){
                        continue;
                    }
                }
                if(!$tr_cache_options['add_script_sync']) {
                    if (strpos($url, '/jquery.js') !== false || strpos($url, '/admin-bar.min.js') !== false || strpos($url, 'comment-reply.min.js') !== false) continue;
                }
				$path    = $this->getpath($url);
				if(!$path || !file_exists($path))continue;


				if(isset($_GET['debugjs']))
				{
					if($i==$_GET['debugjs']){
						file_put_contents(WP_CONTENT_DIR."/log.txt",$url);
					}
					if($i>$_GET['debugjs'])continue;
				}


				$content = str_replace($m,'',$content);

				self::$scripts[] = $path;
				$time_string         .= @filemtime($path).'|'.$path.'|';
			}



			$file_md5    = TRSCSC_CACHE_JS.'/'. md5($time_string) . '.js';
			$file_path   = WP_CONTENT_DIR.$file_md5;
            $file_info   = str_replace('/js','/jsinfo',$file_path).'.info';
			if(!empty($time_string))
			{
				if(!file_exists($file_path))
				{
					$data     = '';
					$only_minify = $tr_cache_options['optimize_js_minify'];
					$compression = !$only_minify && $tr_cache_options['optimize_js_compression'];
					foreach(self::$scripts as $handle => $path)
					{
                        $jsd = @file_get_contents($path);
                        //$jsd = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\')\/\/.*))/', '', $jsd);
						if(!$compression)
						{
							$data.='try{'."\n";
							$data.= $jsd."\n";
							$data.= ' }catch(e){console.log(e)} '."\n";
						}else
						{
							$data.= $jsd.";\n";
						}
					}


					if(!empty($data))
					{
						if($only_minify)
						{
							try{
                                $data =  Tr_Minify_Js::minify($data);

							}catch (Exception $e){

							}
						}
						else if($compression)
						{
							try{
								$script = $data;
								$encoding = 0;
								$fast_decode = false;
								$special_char = false;
								$packer = new JavaScriptPacker($script, $encoding, $fast_decode, $special_char);
								$script = $packer->pack();
								$data = $script;
							}catch(Exception $e)
							{

							}
						}
						@file_put_contents($file_path,$data);
                        @file_put_contents($file_info,json_encode(self::$scripts));
					}
				}else{
                    @touch($file_path);
                }

				//put script
				$search = ($tr_cache_options['optimize_js_footer'])? '</body>':'</head>';
				$script = '<script type="text/javascript" src="'.content_url($file_md5).'"></script>';
				$content= str_replace($search,$script.$search,$content);
			}
		}
        if($tr_cache_options['add_script_sync']){
            $content = str_replace('<script type','<script async type',$content);
        }
		return $content;
	}

	function _optimize_css($content)
	{
		global $tr_cache_options;
        $exclude = !empty($tr_cache_options['exclude_css'])? $tr_cache_options['exclude_css'] : false;
        $include = !empty($tr_cache_options['include_css'])? $tr_cache_options['include_css'] : false;

		if(preg_match_all('/\<link[^\>]*href\=[\'|\"]+([^\'|^\"]*\.css)[^\'|^\"]*[\'|\"]+[^\>^\)]*\>[\n]?/',$content,$matches))
		{
			$time_string = '';
			foreach($matches[0] as $i => $m)
			{
				$url     = $matches[1][$i];
                $filename = basename($url);
                if($exclude)
                {
                    if(strpos($exclude,$filename)!==false){
                        continue;
                    }
                }else if($include){
                    if(strpos($include,$filename)===false){
                        continue;
                    }
                }
				if(strpos($url,'ie')!==false|| strpos($url,'/admin-bar.min.css')!==false)continue;

				$path    = $this->getpath($url);
				if(!$path)continue;

				$content = str_replace($m,'',$content);

				self::$styles[] = array($path,$url);
				$time_string         .= filemtime($path).'|'.$path.'|';
			}

			$file_md5    = TRSCSC_CACHE_CSS.'/'. md5($time_string) . '.css';
			$file_path   = WP_CONTENT_DIR.'/'.$file_md5;
            $file_info   = str_replace('/css','/cssinfo',$file_path).'.info';
			if(!empty($time_string))
			{
				if(!file_exists($file_path))
				{
					$data     = '';
                    $maindomain = get_bloginfo('url');
					$maindomain = preg_replace("~^(.*?//.*?)/.*~",'$1',$maindomain);
					foreach(self::$styles as $handle => $css)
					{
                        $css[1] = str_replace($maindomain,'',$css[1]);
						$csscode = $this->_fix_css(@file_get_contents($css[0]),$css[0],$css[1]);
						$data.= $csscode."\n";
					}
					// return $data;
					if(!empty($data))
					{
						// Remove comments
						$data = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $data);
						// Remove space after colons
						$data = str_replace(': ', ':', $data);
						// Remove whitespace

                        $data = str_replace(array("\r","\n","\t"),'',$data);
						if($tr_cache_options['optimize_ws_css'])
						{
                            $data = $this->stripWhitespace($data);
                            $data = $this->shortenHex($data);
                            $data = $this->stripEmptyTags($data);
						}

						@file_put_contents($file_path,$data);
                        @file_put_contents($file_info,json_encode(self::$styles));
					}
				}else{
					@touch($file_path);
				}

				//put script
				$search = '<title>';

                if($tr_cache_options['add_style_bottom'])
                {
                    $search = '</body>';
                }
				$style  = '<link rel="stylesheet" type="text/css"  href="'.content_url($file_md5).'" />';
				$content= str_replace($search,$style.$search,$content);
			}
		}
		return $content;
	}
    function stripWhitespace($content)
    {
        // remove leading & trailing whitespace
        $content = preg_replace('/^\s*/m', '', $content);
        $content = preg_replace('/\s*$/m', '', $content);
        // replace newlines with a single space
        $content = preg_replace('/\s+/', ' ', $content);
        // remove whitespace around meta characters
        // inspired by stackoverflow.com/questions/15195750/minify-compress-css-with-regex
        $content = preg_replace('/\s*([\*$~^|]?+=|[{};,>~]|!important\b)\s*/', '$1', $content);
        $content = preg_replace('/([\[(:])\s+/', '$1', $content);
        $content = preg_replace('/\s+([\]\)])/', '$1', $content);
        $content = preg_replace('/\s+(:)(?![^\}]*\{)/', '$1', $content);
        // whitespace around + and - can only be stripped inside some pseudo-
        // classes, like `:nth-child(3+2n)`
        // not in things like `calc(3px + 2px)`, shorthands like `3px -2px`, or
        // selectors like `div.weird- p`
        $pseudos = array('nth-child', 'nth-last-child', 'nth-last-of-type', 'nth-of-type');
        $content = preg_replace('/:('.implode('|', $pseudos).')\(\s*([+-]?)\s*(.+?)\s*([+-]?)\s*(.*?)\s*\)/', ':$1($2$3$4$5)', $content);
        // remove semicolon/whitespace followed by closing bracket
        $content = str_replace(';}', '}', $content);
        return trim($content);
    }
    function shortenHex($content)
    {
        $content = preg_replace('/(?<=[: ])#([0-9a-z])\\1([0-9a-z])\\2([0-9a-z])\\3(?=[; }])/i', '#$1$2$3', $content);
        // we can shorten some even more by replacing them with their color name
        $colors = array(
            '#F0FFFF' => 'azure',
            '#F5F5DC' => 'beige',
            '#A52A2A' => 'brown',
            '#FF7F50' => 'coral',
            '#FFD700' => 'gold',
            '#808080' => 'gray',
            '#008000' => 'green',
            '#4B0082' => 'indigo',
            '#FFFFF0' => 'ivory',
            '#F0E68C' => 'khaki',
            '#FAF0E6' => 'linen',
            '#800000' => 'maroon',
            '#000080' => 'navy',
            '#808000' => 'olive',
            '#CD853F' => 'peru',
            '#FFC0CB' => 'pink',
            '#DDA0DD' => 'plum',
            '#800080' => 'purple',
            '#F00' => 'red',
            '#FA8072' => 'salmon',
            '#A0522D' => 'sienna',
            '#C0C0C0' => 'silver',
            '#FFFAFA' => 'snow',
            '#D2B48C' => 'tan',
            '#FF6347' => 'tomato',
            '#EE82EE' => 'violet',
            '#F5DEB3' => 'wheat',
        );
        return preg_replace_callback(
            '/(?<=[: ])('.implode(array_keys($colors), '|').')(?=[; }])/i',
            function ($match) use ($colors) {
                return $colors[strtoupper($match[0])];
            },
            $content
        );
    }

    function stripEmptyTags($content)
    {
        $content = preg_replace('/(?<=^)[^\{\};]+\{\s*\}/', '', $content);
        $content = preg_replace('/(?<=(\}|;))[^\{\};]+\{\s*\}/', '', $content);
        return $content;
    }


	function _fix_css($css,$path,$url)
	{
		//fix url()
		if(preg_match_all('/url\([\s]*[\'|\"]?([^\'\"\)]+)[\'|\"]?[^\)]*\)/i',$css,$matches))
		{
			foreach($matches[0] as $i => $bg)
			{
				$s_url = $matches[1][$i];

				if(preg_match('/^http/',$s_url) || preg_match('/^\/\//',$s_url) || strpos($s_url,'data:')===0)continue;

				$img_url = dirname($url).'/'.$s_url;
				$bg_new  = str_replace($s_url,$img_url,$bg);
				$css     = str_replace($bg,$bg_new,$css);
				$css     = str_replace('http://','//',$css);
				//return $bg_new;

			}

		}


		//fix import
		if(preg_match_all('/@import[^\'^\"]+[\'|\"]+([^\'^\"]+)[\'|\"]+/',$css,$matches))
		{
			foreach($matches[0] as $i=> $import)
			{
				$import_url = dirname($url) .'/'. $matches[1][$i];
				$import_path = $this->getpath($import_url);
				if($import_path && file_exists($import_path) && is_readable($import_path))
				{
					$csscode = $this->_fix_css(@file_get_contents($import_path),$import_path,$import_url);
					$css = str_replace($import,'',$css);
					$css .= "\n" . $csscode;
				}
			}
		}

        $css = str_replace('/css/../','/',$css);


		return $css;
	}

	public function getpath($url)
	{
		$main_url = TR_WP_SITE_URL;
        if(strpos($url,'//')===0)
        {
            $url = 'https:'.$url;
			$main_url = str_replace('http:','https:',$main_url);
        }
		$url = current(explode('?',$url,2));
		$path = str_replace($main_url,'',$url);
		if(preg_match('#^((https?|ftp):)?//#i',$path))
		{
			/** External script/css (adsense, etc) */
			return false;
		}
		$path = str_replace('//','/',ABSPATH.$path);
		return $path;
	}

}