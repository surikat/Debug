<?php
/*
 * ErrorHandler - Error and Exception hanlder with syntax highlighting
 *
 * @package Debug
 * @version 1.4
 * @link http://github.com/redcatphp/Debug/
 * @author Jo Surikat <jo@surikat.pro>
 * @website http://redcatphp.com
 */

namespace RedCat\Debug;
class ErrorHandler{
	private static $errorType;
	private $handle;
	private $registeredErrorHandler;
	private $debugLines;
	private $debugStyle;
	public $debugWrapInlineCSS;
	public $html_errors;
	public $loadFunctions;
	public $cwd;
	function __construct(
		$html_errors=null,
		$debugLines=5,
		$debugStyle='<style>code br{line-height:0.1em;}pre.error{display:block;position:relative;z-index:99999;}pre.error span:first-child{color:#d00;}</style>',
		$debugWrapInlineCSS='margin:4px;padding:4px;border:solid 1px #ccc;border-radius:5px;overflow-x:auto;background-color:#fff;',
		$loadFunctions=true
	){
		$this->html_errors = isset($html_errors)?$html_errors:php_sapi_name()!='cli';
		$this->debugLines = $debugLines;
		$this->debugStyle = $debugStyle;
		$this->debugWrapInlineCSS = $debugWrapInlineCSS;
		$this->loadFunctions = $loadFunctions;
		$this->cwd = getcwd();
	}
	function handle($force=false){
		$this->handle = true;
		error_reporting(-1);
		ini_set('display_startup_errors',true);
		ini_set('display_errors','stdout');
		ini_set('html_errors',$this->html_errors);
		if(!$this->registeredErrorHandler||$force){
			$this->registeredErrorHandler = true;
			set_error_handler([$this,'errorHandle']);
			register_shutdown_function([$this,'fatalErrorHandle']);
			set_exception_handler([$this,'catchException']);
			if($this->loadFunctions)
				include_once __DIR__.'/functions.inc.php';
		}
	}
	function catchException($e){
		$html = isset($_SERVER['HTTP_ACCEPT'])&&strpos($_SERVER['HTTP_ACCEPT'],'text/html')!==false;
		if(!headers_sent()&&$this->html_errors&&$html){
			header("Content-Type: text/html; charset=utf-8");
		}
		$msgStr = 'Exception: '.$e->getMessage().' in '.$e->getFile().' at line '.$e->getLine();
		$msgStr .= $this->getExceptionTraceAsString($e);
		if($html){
			$msg = 'Exception: '.htmlentities($e->getMessage()).' in '.$e->getFile().' at line '.$e->getLine();
			echo $this->debugStyle;
			echo '<pre class="error" style="'.$this->debugWrapInlineCSS.'"><span>'.$msg."</span>\nStackTrace:\n";
			echo '#'.get_class($e);
			if(method_exists($e,'getData')){
				echo ':';
				var_dump($e->getData());
			}
			//echo htmlentities($e->getTraceAsString());
			echo htmlentities($this->getExceptionTraceAsString($e));
			echo '</pre>';
		}
		else{
			echo $msgStr."\n";
		}
		$this->errorLog($msgStr);
		return false;
	}
	function errorLog($msg){
		$errorDir = $this->cwd.'/.tmp/';
		$errorFile = $errorDir.'php-error.log';
		if(!is_dir($errorDir)) mkdir($errorDir,0777,true);
		file_put_contents($errorFile,$msg.PHP_EOL,FILE_APPEND);
	}
	function getExceptionTraceAsString($exception) {
		$rtn = "";
		$count = 0;
		foreach ($exception->getTrace() as $frame) {
			$args = "";
			if (isset($frame['args'])) {
				$args = array();
				foreach ($frame['args'] as $arg) {
					if (is_string($arg)) {
						$args[] = "'" . $arg . "'";
					} elseif (is_array($arg)) {
						$args[] = "Array";
					} elseif (is_null($arg)) {
						$args[] = 'NULL';
					} elseif (is_bool($arg)) {
						$args[] = ($arg) ? "true" : "false";
					} elseif (is_object($arg)) {
						$args[] = get_class($arg);
					} elseif (is_resource($arg)) {
						$args[] = get_resource_type($arg);
					} else {
						$args[] = $arg;
					}   
				}   
				$args = join(", ", $args);
			}
			$rtn .= sprintf( "#%s %s(%s): %s(%s)\n",
									 $count,
									 isset($frame['file']) ? $frame['file'] : 'unknown file',
									 isset($frame['line']) ? $frame['line'] : 'unknown line',
									 (isset($frame['class']))  ? $frame['class'].$frame['type'].$frame['function'] : $frame['function'],
									 $args );
			$count++;
		}
		return $rtn;
	}
	function errorHandle($code, $message, $file, $line){
		if(!$this->handle||error_reporting()==0)
			return;
		$html = false;
		if(!headers_sent()&&$this->html_errors){
			header("Content-Type: text/html; charset=utf-8");
			$html = true;
		}
		$msg = self::$errorType[$code]."\t$message\nFile\t$file\nLine\t$line";
		$this->errorLog(self::$errorType[$code]."\t$message in $file at line $line");
		if(is_file($file)){
			if($html){
				echo $this->debugStyle;
				echo "<pre class=\"error\" style=\"".$this->debugWrapInlineCSS."\"><span>".$msg."</span>\nContext:\n";
				$f = explode("\n",str_replace(["\r\n","\r"],"\n",file_get_contents($file)));
				foreach($f as &$x)
					$x .= "\n";
				$c = count($f);			
				$start = $line-$this->debugLines;
				$end = $line+$this->debugLines;
				if($start<0)
					$start = 0;
				if($end>($c-1))
					$end = $c-1;
				$e = '';
				for($i=$start;$i<=$end;$i++){
					$e .= $f[$i];
				}
				$e = highlight_string('<?php '.$e,true);
				$e = str_replace('<br />',"\n",$e);
				$e = substr($e,35);
				$x = explode("\n",$e);
				$e = '<code><span style="color: #000000">';
				$count = count($x);
				for($i=0;$i<$count;$i++){
					$y = $start+$i;
					$e .= '<span style="color:#'.($y==$line?'d00':'070').';">'.$y."\t</span>";
					$e .= $x[$i]."\n";
				}
				$p = strpos($e,'&lt;?php');
				$e = substr($e,0,$p).substr($e,$p+8);
				echo $e;
				echo '</pre>';
			}
			else{
				echo strip_tags($msg);
			}
		}
		//else{
			//echo "$message in $file on line $line";
		//}
		return true;
	}
	function fatalErrorHandle(){
		if(!$this->handle)
			return;
		$error = error_get_last();
		if($error&&$error['type']&(E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR)){
			self::errorHandle(E_ERROR,$error['message'],$error['file'],$error['line']);
		}
	}
	static function initialize(){
		self::$errorType = [
			E_ERROR           => 'error',
			E_WARNING         => 'warning',
			E_PARSE           => 'parsing error',
			E_NOTICE          => 'notice',
			E_CORE_ERROR      => 'core error',
			E_CORE_WARNING    => 'core warning',
			E_COMPILE_ERROR   => 'compile error',
			E_COMPILE_WARNING => 'compile warning',
			E_USER_ERROR      => 'user error',
			E_USER_WARNING    => 'user warning',
			E_USER_NOTICE     => 'user notice',
			E_STRICT          => 'strict standard error',
			E_RECOVERABLE_ERROR => 'recoverable error',
			E_DEPRECATED      => 'deprecated error',
			E_USER_DEPRECATED => 'user deprecated error',
		];
		if(defined('E_STRICT'))
		  self::$errorType[E_STRICT] = 'runtime notice';
	}
	static function errorType($code){
		return isset(self::$errorType[$code])?self::$errorType[$code]:null;
	}
}
ErrorHandler::initialize();