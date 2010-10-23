<?php
/***************************************************************************\
| Sypex Dumper               version 2.0.6 Beta                             |
| (c) 2003-2010 zapimir      zapimir@zapimir.net       http://sypex.net/    |
| (c) 2005-2010 BINOVATOR    info@sypex.net                                 |
|---------------------------------------------------------------------------|
|     created: 2003.09.02 19:07              modified: 2010.02.23 19:22     |
|---------------------------------------------------------------------------|
| Sypex Dumper is released under the terms of the BSD license               |
|   http://sypex.net/bsd_license.txt                                        |
\***************************************************************************/
header("Expires: Wed, 19 Nov 2008 19:19:19 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Content-Type: text/html; charset=utf-8");
if(!ini_get('zlib.output_compression') && function_exists('ob_gzhandler')) ob_start('ob_gzhandler');
//error_reporting(E_ALL);
error_reporting(0);
set_error_handler('sxd_error_handler');
register_shutdown_function('sxd_shutdown');
set_magic_quotes_runtime(0);
$SXD = new Sypex_Dumper();
chdir(dirname(__FILE__));
$SXD->init(!empty($argc) && $argc > 1 ? $argv : false);
 
class Sypex_Dumper {
	function Sypex_Dumper() {
		define('C_DEFAULT', 1);
		define('C_RESULT', 2);
		define('C_ERROR', 3);
		define('C_WARNING', 4);
		define('SXD_DEBUG', false);
		define('TIMER', array_sum(explode(' ', microtime()))); 
		define('V_SXD', 20006);
		define('V_PHP', sxd_ver2int(phpversion()));
		$this->name = 'Sypex Dumper 2.0.6 Beta';
	}
	function loadLang($lng_name = 'auto'){
		if($lng_name == 'auto'){
			include('lang/list.php');
			$this->langs = &$langs;
			$lng = 'en';
			if(preg_match_all('/[a-z]{2}(-[a-z]{2})?/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $m)) {
				foreach($m[0] AS $l){
					if(isset($langs[$l])){
						$lng_name = $l;
						break;
					}
				}
			}
		}
		if(file_exists("lang/lng_{$lng_name}.php")) include("lang/lng_{$lng_name}.php");
		else include("lang/lng_en.php");
		$this->LNG = &$LNG;
		$this->LNG['name'] = $lng_name;
		return true;
	}
	function init($args = false){
		if (get_magic_quotes_gpc()) {
			$_POST = sxd_antimagic($_POST);
		}
		include('cfg.php');
		$this->loadLang($CFG['lang']);
		if (!ini_get('safe_mode') && function_exists('set_time_limit') && strpos(ini_get('disable_functions'), 'set_time_limit') === false) @set_time_limit($CFG['time_web']);
		elseif (ini_get('max_execution_time') < $CFG['time_web']) $CFG['time_web'] = ini_get('max_execution_time');
		$this->CFG = &$CFG;
		$this->try = false;
		$this->virtualize = false;
		$this->cron_mode = false;
		// Проверяем авторизацию и делаем коннект к базе
		if(empty($this->CFG['my_user'])){
			$this->CFG['my_host'] = 'localhost';
			$this->CFG['my_port'] = 3306;
			$this->CFG['my_user'] = 'root';
			$this->CFG['my_pass'] = '';
			$this->CFG['my_comp'] = 0;
			$this->CFG['my_db'] = '';
		}
		if ($args) { // консольный режим
		    foreach($args AS $key => $arg){
		        if (preg_match("/^-([hupoj])=(.*?)$/", $arg, $m)){
		            switch ($m[1]) {
		                case 'h': $this->CFG['my_host'] = $m[2]; break; // хост
		                case 'o': $this->CFG['my_port'] = $m[2]; break; // порт
		                case 'u': $this->CFG['my_user'] = $m[2]; break; // логин
		                case 'p': $this->CFG['my_pass'] = $m[2]; break; // пароль
		                case 'j': $this->CFG['sjob'] = $m[2]; break; // job-файл
		            }
		        }
		    }
		    set_time_limit($CFG['time_cron']);
		    // Загружаем конфиг файл, если нужно
		    $auth = $this->connect();
		    if($auth && !empty($this->CFG['sjob'])){
				$this->ajax($this->loadJob($this->CFG['sjob']));
				echo file_get_contents($this->JOB['file_log']);
				if(file_exists($this->JOB['file_log'])) unlink($this->JOB['file_log']);
				if(file_exists($this->JOB['file_rtl'])) unlink($this->JOB['file_rtl']);
		    }
		    else echo 'Auth error';
		    exit;
		}
		elseif(!empty($this->CFG['auth'])){ // Авторизация
			$auth = false;
			$sfile = 'ses.php';
			
			if(!empty($_COOKIE['sxd']) && preg_match('/^[\da-f]{32}$/', $_COOKIE['sxd'])){
				include($sfile);
				if(isset($SES[$_COOKIE['sxd']])) {
					$auth = true;
					$this->CFG = $SES[$_COOKIE['sxd']]['cfg'];
					$this->SES = &$SES;
					$this->loadLang($this->CFG['lang']);
				}
			}
			if(!$auth) {
				$user = !empty($_POST['user']) ? $_POST['user'] : '';
				$pass = !empty($_POST['pass']) ? $_POST['pass'] : '';
				$host = !empty($_POST['host']) ? $_POST['host'] : 'localhost';
				$port = !empty($_POST['port']) && is_numeric($_POST['port']) ? $_POST['port'] : 3306;
				$temp = preg_split('/\s+/', $this->CFG['auth']);
				if(!empty($_REQUEST['lang']) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_REQUEST['lang'])) {$this->loadLang($_REQUEST['lang']);}
				foreach($temp AS $a){
					switch($a) {
						case 'cfg': 	if(empty($user)) {continue;}
										$auth = !empty($CFG['user']) && isset($CFG['pass']) && $CFG['user']== $user && $CFG['pass'] == $pass;
										break;
						case 'mysql':	if(empty($user)) {continue;}
										$auth = $this->connect($host, $port, $user, $pass);
										break;
						default:		$file = 'auth_' . $a . '.php';
										if(!file_exists($file)) continue;
										include	$file;
					}
					if($auth) break;
				}
				if($auth){
					$key = md5(rand(1,100000) . $user . microtime());
					$CFG['lang'] = $this->LNG['name'];
					$_COOKIE['sxd'] = $key;
					$this->saveCFG();
					setcookie('sxd', $key, !empty($_POST['save']) ? time() + 31536000 : 0);
					header("Location: ./");
					exit;
				}
				foreach(array('user', 'pass', 'host', 'port') AS $key){
					$_POST[$key] = !empty($_POST[$key]) ? htmlspecialchars($_POST[$key], ENT_NOQUOTES) : '';
				}
				$_POST['save'] = !empty($_POST['save']) ? ' CHECKED' : '';
			}
			if (!$auth) {
				if(!empty($_POST['ajax'])){
					echo "sxd.hideLoading();alert('Session not found');";
					exit;
				}
				$this->lng_list = '<option value="auto">- auto -</opinion>';
				if(!isset($this->langs)) {include('lang/list.php');$this->langs = &$langs;}
				foreach($this->langs AS $k => $v){
					$this->lng_list .= "<option value=\"{$k}\"" . ($k == (!empty($_REQUEST['lang']) ? $this->LNG['name'] : $this->CFG['lang']) ? ' SELECTED' : '') . ">{$v}</opinion>";
				}
				echo sxd_tpl_auth();
				exit;
			}
		}
		/*if(!empty($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] == 'exit') {
			setcookie('sxd', '', 0);
			header("Location: {$this->CFG['exitURL']}");
			exit;
		}*/
		if(empty($_POST['ajax']['act']) || $_POST['ajax']['act'] != 'save_connect') $this->connect();
		if(isset($_POST['ajax'])) $this->ajax($_POST['ajax']);
		else $this->main();
	}
	function saveToFile($name, $content){
		$fp = fopen($name, "w");
		fwrite($fp, $content);
		fclose($fp);
	}
	function connect($host = null, $port = null, $user = null, $pass = null){
		$this->error = '';
		$this->try = true;
		if(!empty($user) && isset($pass)) {
			$this->CFG['my_host'] = $host;
			$this->CFG['my_port'] = $port;
			$this->CFG['my_user'] = $user;
			$this->CFG['my_pass'] = $pass;
		}
		if(mysql_connect($this->CFG['my_host'] . ($this->CFG['my_host']{0} != ':' ? ":{$this->CFG['my_port']}" : ''),  $this->CFG['my_user'], $this->CFG['my_pass'])) {
			mysql_query('SET NAMES utf8') or sxd_my_error();
			define('V_MYSQL', sxd_ver2int(mysql_get_server_info()));
		}
		else {
			define('V_MYSQL', 0); 
			$this->error = "sxd.actions.tab_connects();alert('{" . mysql_escape_string(mysql_error()) . "');";
		}	
		$this->try = false;
		return V_MYSQL ? true: false;
	}
	function main(){
   		// Тулбар
		$this->VAR['toolbar'] = sxd_php2json(
			array(
				array('backup', $this->LNG['tbar_backup'], 1, 3),
				array('restore', $this->LNG['tbar_restore'], 2, 3),
				array('|'),
				array('files', $this->LNG['tbar_files'], 3, 1), 
				array('services', $this->LNG['tbar_services'], 5, 1),
				array('|'),
				array('createdb', $this->LNG['tbar_createdb'], 7, 0),
				array('connects', $this->LNG['tbar_connects'], 6, 0),
				array('|'),
				array('options', $this->LNG['tbar_options'], 4, 1),
				array('|'),
				array('exit', $this->LNG['tbar_exit'], 8, 1),
			)
		);
  		$this->db = 'temp';
		$zip = array($this->LNG['zip_none']);
		if (function_exists("gzopen")) {
			for($i = 1; $i <10; $i++){
				$zip[] = "GZip: {$i}";
			}
			$zip[1] .= " ({$this->LNG['zip_min']})";
			$zip[7] .= " ({$this->LNG['default']})";
		}
		if (function_exists("bzopen")) {
		    $zip[10] = "BZip";
		}
		end($zip);
		$zip[key($zip)] .=  " ({$this->LNG['zip_max']})";
		$this->VAR['combos'] =
			$this->addCombo('backup_db', $this->db, 11, 'db', $this->getDBList()) . 
			$this->addCombo('backup_charset', 0, 9, 'charset', $this->getCharsetList()) .
			$this->addCombo('backup_zip', 7, 10, 'zip', $zip) .
			$this->addCombo('restore_db', $this->db, 11, 'db') . 
			$this->addCombo('restore_charset', 0, 9, 'charset') . 
			$this->addCombo('restore_file', 0, 12, 'files', $this->getFileList()) . 
			$this->addCombo('restore_type', 0, 13, 'types', array("CREATE + INSERT ({$this->LNG['default']})", 'TRUNCATE + INSERT', 'REPLACE', 'INSERT IGNORE')) .
			$this->addCombo('services_db', $this->db, 11, 'db') .
			$this->addCombo('services_check', 0, 5, 'check', array("- {$this->LNG['default']} -", 'QUICK', 'FAST', 'CHANGED', 'MEDIUM', 'EXTENDED')) .
			$this->addCombo('services_repair', 0, 5, 'repair', array("- {$this->LNG['default']} -", 'QUICK', 'EXTENDED')) .
			$this->addCombo('services_charset', 0, 9, 'collation', $this->getCollationList()) .
			$this->addCombo('services_charset_col', 0, 15, 'collation:services_charset').
			$this->addCombo('db_charset', 0, 9, 'collation') .
			$this->addCombo('db_charset_col', 0, 15, 'collation:db_charset')
		;
		if (!V_MYSQL) $this->VAR['combos'] .= $this->error;
		$this->VAR['combos']   .= $this->getSavedJobs() . "sxd.confirms = {$this->CFG['confirm']};";
		$this->LNG['del_date']  = sprintf($this->LNG['del_date'], '<input type="text" id="del_time" class=txt style="width:24px;" maxlength="3">');
		$this->LNG['del_count'] = sprintf($this->LNG['del_count'], '<input id="del_count" type="text" class=txt style="width:18px;" maxlength="2">');
		
		echo sxd_tpl_page();
	}
	function addCombo($name, $sel, $ico, $opt_name, $opts = ''){
		$opts = !empty($opts) ? "{{$opt_name}:" . sxd_php2json($opts) . '}' : "'{$opt_name}'";
		return "sxd.addCombo('{$name}', '{$sel}', {$ico}, {$opts});\n";
	}
	function ajax($req){
		$res = '';
		$act = $req['act'];
		if($req['act'] == 'run_savedjob'){
			$req = $this->loadJob($req);
		}
		switch($req['act']){
			case 'load_db': 
				$res = $this->getObjects(str_replace('_db', '', $req['name']), $req['value']);
				break;
			case 'load_files': 
				$res = $this->getFileObjects('restore', $req['value']);
				break;
			case 'filelist': 
				$res = "sxd.clearOpt('files');sxd.addOpt(" . sxd_php2json(array('files' => $this->getFileList())) . ");";
				break;
			case 'load_connect':
				$CFG = $this->cfg2js($this->CFG); 
				$res = "z('con_host').value = '{$CFG['my_host']}', z('con_port').value = '{$CFG['my_port']}', z('con_user').value = '{$CFG['my_user']}',
			z('con_pass').value = '', z('con_comp').checked = {$CFG['my_comp']}, z('con_db').value = '{$CFG['my_db']}'" ;
				break;
			case 'save_connect': 
				$res = $this->saveConnect($req);
				break;
			case 'save_job': 
				unset($req['act']);
				$this->saveJob('sj_' . $req['job'] , $req);
				$res = $this->getSavedJobs();
				break;
			case 'add_db': 
				$res = $this->addDb($req);
				break;
			case 'load_options':
				$CFG = $this->cfg2js($this->CFG);
				$res = "z('time_web').value = '{$CFG['time_web']}', z('time_cron').value = '{$CFG['time_cron']}', z('backup_path').value = '{$CFG['backup_path']}',
			z('backup_url').value = '{$CFG['backup_url']}', z('globstat').checked = {$CFG['globstat']}, z('charsets').value = '{$CFG['charsets']}', z('only_create').value = '{$CFG['only_create']}', z('auth').value = '{$CFG['auth']}', z('conf_import').checked = {$CFG['confirm']} & 1, z('conf_file').checked = {$CFG['confirm']} & 2, z('conf_db').checked = {$CFG['confirm']} & 4;sxd.confirms = {$this->CFG['confirm']};";
				break;
			case 'save_options': 
				$res = $this->saveOptions($req);
				break;
			case 'delete_file':
				if(preg_match('/^[^\/]+?\.sql(\.(gz|bz2))?$/', $req['name'])) {
					$file = $this->CFG['backup_path'] . $req['name'];
					if(file_exists($file)) unlink($file);
				}
				$res = $this->getFileListExtended();
				break;
			case 'delete_db':
				$res = $this->deleteDB($req['name']);
				break;
			case 'load_files_ext': 
				$res .= $this->getFileListExtended();
				break;
			case 'services': 
				$this->runServices($req);
				break;	
			case 'backup': 
				$this->addBackupJob($req);
				break;
			case 'restore': 
				$this->addRestoreJob($req);
				break;
			case 'resume': 
				$this->resumeJob($req);
				break;
			case 'exit': 
				setcookie('sxd', '', 0);
				$res = "top.location.href = '" . mysql_escape_string($this->CFG['exitURL']) . "'"; 
				break;
		}	
		echo $res;
	}
	function loadJob($job){
		$file = $this->CFG['backup_path'] . 'sj_' . (is_array($job) ? $job['job'] : $job) . '.job.php';
		if(!file_exists($file)) return;
		include($file);
		$JOB['act'] = $JOB['type'];
		$JOB['type'] = 'run';
		return $JOB;
	}
	function deleteDB($name){
		$r = mysql_query('DROP DATABASE `' . mysql_escape_string($name) . '`') or sxd_my_error();
		if($r){
        	echo "sxd.clearOpt('db');sxd.addOpt(" . sxd_php2json(array('db' => $this->getDBList())) . ");sxd.combos.services_db.select(0,'-');";
		}
        else
        	echo "alert('" . mysql_escape_string(mysql_error()) . "')";
	}
	function cfg2js($cfg){
		foreach($cfg AS $k => $v){
			$cfg[$k] = mysql_escape_string($v);
		}
		return $cfg;
	}
	function addDb($req){
        $r = mysql_query('CREATE DATABASE `' . mysql_escape_string($req['name']) . '`' . (V_MYSQL > 40100 ? "CHARACTER SET {$req['charset']} COLLATE {$req['collate']}" : ''));
        if($r)
        	echo "sxd.addOpt(" . sxd_php2json(array('db' => array($req['name'] => "{$req['name']} (0)"))) . ");";
        else
        	 sxd_my_error();
	}
	function saveConnect($req){
		$this->CFG['my_host'] = $req['host'];
		$this->CFG['my_port'] = (int)$req['port'];
		$this->CFG['my_user'] = $req['user'];
		$this->CFG['my_pass'] = $req['pass'];
		$this->CFG['my_comp'] = $req['comp'] ? 1 : 0;
		$this->CFG['my_db']   = $req['db'];
		$this->saveCFG();
        $this->connect();
        if (V_MYSQL) {
	        $tmp = array(
        		'db' => $this->getDBList(),
        		'charset' => $this->getCharsetList(),
        		'collation' => $this->getCollationList()
			);
	        echo "sxd.clearOpt('db');sxd.clearOpt('charset');sxd.clearOpt('collation');sxd.addOpt(" . sxd_php2json($tmp) . ");sxd.combos.backup_db.select(0,'-');sxd.combos.restore_db.select(0,'-');sxd.combos.services_db.select(0,'-');sxd.combos.backup_charset.select(0,'-');sxd.combos.services_db.select(0,'-');sxd.combos.db_charset.select(0,'-');";
		}
		else {
			 echo $this->error;
		}
	}
	function saveOptions($req){
		$this->CFG['time_web']    = $req['time_web'];
		$this->CFG['time_cron']   = $req['time_cron'];
		$this->CFG['backup_path'] = $req['backup_path'];
		$this->CFG['backup_url']  = $req['backup_url'];
		$this->CFG['globstat']    = $req['globstat'] ? 1 : 0;
		$this->CFG['charsets']    = $req['charsets'];
		$this->CFG['only_create'] = $req['only_create'];
		$this->CFG['auth']        = $req['auth'];
		$this->CFG['confirm']     = $req['confirm'];
		$this->saveCFG();
	}
	function saveCFG(){
		if (isset($_COOKIE['sxd'])) {
			$this->SES[$_COOKIE['sxd']] = array('cfg' => $this->CFG, 'time' => time(), 'lng' => $this->LNG['name']);
			$this->saveToFile('ses.php', "<?php\n\$SES = " . var_export($this->SES, true) . ";\n" . "?>");
		}
        if (!$this->virtualize){
        	$this->saveToFile('cfg.php', "<?php\n\$CFG = " . var_export($this->CFG, true) . ";\n" . "?>");
        }
	}
	function runServices($job) {
		$serv = array('optimize' => 'OPTIMIZE', 'analyze' => 'ANALYZE', 'check' => 'CHECK', 'repair' => 'REPAIR');
		$add = array('check'  => array('', 'QUICK', 'FAST', 'CHANGED', 'MEDIUM', 'EXTENDED'), 'repair' => array('', 'QUICK', 'EXTENDED'));
		if(isset($serv[$job['type']])) {
			mysql_select_db($job['db']);
			$filter = $object = array();
			$this->createFilters($job['obj'], $filter, $object);
			$r = mysql_query('SHOW TABLE STATUS') or sxd_my_error();
			if (!$r) return;
			$tables = array();
			while($item = mysql_fetch_assoc($r)){
				if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) continue;
				if(sxd_check($item['Name'], $object['TA'], $filter['TA'])) $tables[] = "`{$item['Name']}`";
			}
			$sql = $serv[$job['type']] . ' TABLE ' . implode(',', $tables);
			
			if ($job['type'] == 'check' || $job['type'] == 'repair') {
				$sql .= isset($add[$job['type']][$job[$job['type']]]) ? ' ' . $add[$job['type']][$job[$job['type']]] : '';
			}
			
			$r = mysql_query($sql) or sxd_my_error();
			if (!$r) return;
			$res = array();
			while($item = mysql_fetch_row($r)){
				$res[] = $item;
			}
			echo 'sxd.result.add(' . sxd_php2json($res). ');';
		}
	}
	function createFilters(&$obj, &$filter, &$object){
		$types = array('TA', 'TC', 'VI', 'PR', 'FU', 'TR', 'EV');
		foreach($types AS $type){
			$filter[$type] = array();
			$object[$type] = array();
			if(!empty($obj[$type])){
				foreach($obj[$type] AS $v){
					if(strpos($v, '*') !== false) {
						$filter[$type][] = str_replace('*', '.*?', $v); 
					}
					else {
						$object[$type][$v] = true;
					}
				}
				$filter[$type] = count($filter[$type]) > 0 ? '/^(' . implode('|', $filter[$type]) . ')$/i' : '';
			}
		}
	}
	function closeConnect(){
		//return;
		ignore_user_abort(1); 
		header("SXD: {$this->name}");
		header("Connection: close"); 
		header("Content-Length: 0"); 
		ob_end_flush(); 
		flush();
	}
	function resumeJob($job){
		$this->closeConnect();
		include($this->CFG['backup_path'] . $job['job'] . '.job.php');
		$this->JOB = &$JOB;
		if(file_exists($this->JOB['file_stp'])) unlink($this->JOB['file_stp']);
		$this->fh_rtl = fopen($this->JOB['file_rtl'], 'r+b');
		$this->fh_log = fopen($this->JOB['file_log'], 'ab');
		$t = fgets($this->fh_rtl);
		if(!empty($t)){
			$this->rtl = explode("\t", $t);	
		}
		else {
			$this->addLog($this->LNG['not_found_rtl']);
			exit;
		}
		// TODO: проверить удаление кодировки
		//$this->rtl[6] = '';
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		$this->rtl[9] = 0;
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		if ($this->JOB['act'] == 'backup') $this->runBackupJob(true);
		elseif ($this->JOB['act'] == 'restore') $this->runRestoreJob(true);
	}
	function addRestoreJob($job) {
		$this->closeConnect();
		$this->JOB = $job;
		// Создаем список объектов и фильтр
		$filter = $object = array();
   		$this->createFilters($this->JOB['obj'], $filter, $object);
		
		$objects = $this->getFileObjects('restore', $this->JOB['file'], false);
		$todo = array();
		$rows = 0;
		$this->tab_rows = array();
		foreach($objects AS $t => $list){
			if($t == 'TA' && (!empty($object['TC']) || !empty($filter['TC']))) {}
			elseif(empty($object[$t]) && empty($filter[$t])) continue;
			if (empty($list)) continue;
			$todo[$t] = array();
			foreach($list AS $item){
				switch($t){
					case 'TA':
						$type = '';
						if(sxd_check($item[0], $object['TA'], $filter['TA'])){
							$type = empty($item[1]) ? 'TC' : 'TA';
						}
						elseif(sxd_check($item[0], $object['TC'], $filter['TC'])) {
							$type = 'TC';
						}
						else continue;
						$todo['TA'][]   = array($type, $item[0], $item[1], $item[2]);
						$rows += $type == 'TA' ? $item[1] : 0;
					break;
					default:
						if(sxd_check($item, $object[$t], $filter[$t])) {
							$todo[$t][] = array($t, $item);
						}
				}
			}
		}
		$this->JOB['file_tmp'] = $this->JOB['file_name'] = $this->CFG['backup_path'] . $this->JOB['file'];
		$this->JOB['file_rtl'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.rtl';
		$this->JOB['file_log'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.log';
		$this->JOB['file_stp'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.stp';
		if(file_exists($this->JOB['file_stp'])) unlink($this->JOB['file_stp']);
		$this->JOB['todo'] = $todo;
		$this->saveJob($this->JOB['job'], $this->JOB);
		$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'r');
		$this->fh_rtl = fopen($this->JOB['file_rtl'], 'wb');
		$this->fh_log = fopen($this->JOB['file_log'], 'wb');
		$this->rtl = array(time(), time(), $rows, 0, '', '', '', 0, 0, 0, 0, TIMER, "\n");
		$this->addLog(sprintf($this->LNG['restore_begin'], $this->JOB['db']));
		$this->addLog("{$this->LNG['combo_file']} {$this->JOB['file']}");
		$this->runRestoreJob();
	}
	function runRestoreJob($continue = false){
		$ei = false;
		if($continue){
			$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'r');
			fseek($this->fh_tmp, $this->rtl[3]);
			if(!empty($this->rtl[6])) $this->setNames($this->JOB['correct'] == 1 && !empty($this->JOB['charset']) ? $this->JOB['charset'] : $this->rtl[6]);
			if($this->rtl[7] < $this->rtl[10]) $ei = true; 
		}
		mysql_select_db($this->JOB['db']);
		
		//mysql_query("SET NAMES 'UTF8'");
		$types = array('VI' => 'View', 'PR' => 'Procedure', 'FU' => 'Function', 'TR' => 'Trigger', 'EV' => 'Event');
		$fcache = '';
		$writes = 0;
		$old_charset = '';
		$tab = '';
		$seek = 0;
		$this->rtl[3] = ftell($this->fh_tmp);
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$c = 0;
		switch($this->JOB['strategy']){
			case 1: $tc = 'TRUNCATE'; $td = 'INSERT'; break;
			case 2: $tc = ''; $td = 'REPLACE'; break;
			case 3: $tc = ''; $td = 'INSERT IGNORE'; break;
			default: $tc = 'DROP TABLE IF EXISTS'; $td = 'INSERT';
		}
		$tab_exists = array();
		if($this->JOB['strategy'] > 0){
			$r = mysql_query("SHOW TABLES") or sxd_my_error();
			while($item = mysql_fetch_row($r)){
				$tab_exists[$item[0]] = true;
			}
		}
		$insert = $continue && $this->rtl[7] < $this->rtl[10] ? "{$td} INTO `{$this->rtl[5]}` VALUES " : '';
		//$enable_index = array();
		if(V_MYSQL > 40014) {
			mysql_query("SET UNIQUE_CHECKS=0");
			mysql_query("SET FOREIGN_KEY_CHECKS=0");
			if(V_MYSQL > 40101) mysql_query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
			if(V_MYSQL > 40111) mysql_query("SET SQL_NOTES=0");
		}
		$log_sql = false;
		$fields = '';
		$time_old = time();
		$exit_time = $time_old + $this->CFG['time_web'] - 1;
		while($q = sxd_read_sql($this->fh_tmp, $seek, $ei)){	
			if($time_old < time()) {
				if(file_exists($this->JOB['file_stp'])){
					$type = file_get_contents($this->JOB['file_stp']);
					$this->rtl[9] = !empty($type) ? $type : 2;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					/*if($type == 1) {
						
					}*/
					unset($this->rtl);
					exit;
				}
				$time_old = time();
				if($time_old >= $exit_time){
					$this->rtl[9] = 3;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					unset($this->rtl);
					exit;
				}
				clearstatcache(); 
			}
			switch($q{0}){
				case '(':
					if($continue) {
						$this->addLog(sprintf("{$this->LNG['restore_TC']} {$this->LNG['continue_from']}", $this->rtl[5], $this->rtl[3]));
						$continue = false;
					}
					$q = $insert . $q;
					$ex = 1;
					$c = 1;
					break;	
				case 'I':
					if (preg_match('/^INSERT( INTO `(.+?)`) VALUES/', $q, $m)) {
						$insert = $td . $m[1] . $fields . ' VALUES ';
						$tab = $m[2];
						$this->rtl[7] = 0;
						$this->rtl[8] = 0;
						foreach($this->JOB['todo']['TA'] AS $t){
							if($t[1] == $tab) {
								$this->rtl[8] = $t[2];
							}	
						}
						if($this->JOB['strategy']) {
							$q = substr_replace($q, $insert, 0, strlen($m[0]));
						}
						//mysql_query("LOCK TABLES `{$tab}` WRITE") or die (mysql_error());
						mysql_query("ALTER TABLE `{$tab}` DISABLE KEYS") or sxd_my_error();
						if(!empty($this->JOB['autoinc'])) mysql_query("ALTER TABLE `{$tab}` AUTO_INCREMENT = 1") or sxd_my_error();
						$ex = 1;
					}
					break;
				case 'C':
					$ex = 1;
					if (preg_match('/^CREATE TABLE `/', $q)) {
						if($this->JOB['strategy'] != 0 && isset($tab_exists[$this->rtl[5]])) $ex = 0;
						else {
							$ex = 1;
							if((!empty($this->JOB['correct']) && !empty($this->JOB['charset']))){
								$q = preg_replace('/(DEFAULT)?\s*(CHARSET|CHARACTER SET|COLLATE)[=\s]+\w+/i', '', $q) . (V_MYSQL < 40100 ? '' : 'DEFAULT CHARSET=' . $this->JOB['charset']);
							}
						}
						// Достаем имена полей таблицы
						$fields = $this->JOB['strategy'] > 0 && preg_match_all('/^\s+(`.+?`) /m', $q, $f, PREG_PATTERN_ORDER) ? '(' . implode(',', $f[1]) . ')' : '';
					}
					break;
				case '#': // Команды для дампера
					if (preg_match("/\#\t(TC|TD|VI|PR|FU|TR|EV)`(.+?)`(([^_]+?)_.+?)?$/", $q, $m)) {
						//if(!empty($tab)) $enable_index[] = $tab;
						$this->setNames($this->JOB['correct'] == 1 && !empty($this->JOB['charset']) ? $this->JOB['charset'] : empty($m[3]) ? '' : $m[3]);
						
						if($m[1] == 'TC') {
							$this->addLog(sprintf($this->LNG['restore_TC'], $m[2]));
							$insert = '';
							$tab = '';
							$this->rtl[4] = 'TD';
							$this->rtl[5] = $m[2];
							$ei = 0;
							if($tc && ($this->JOB['strategy'] == 0 || isset($tab_exists[$m[2]]))) {
								mysql_query("{$tc} `{$m[2]}`") or sxd_my_error();
							}
						}
						elseif($m[1] == 'TD'){
							$ei = 1;
						}
						else {
							$this->rtl[4] = $m[1];
							$this->rtl[5] = $m[2];
							$this->rtl[7] = 0;
							$this->rtl[8] = 0;
							mysql_query("DROP {$types[$m[1]]} IF EXISTS `{$m[2]}`") or sxd_my_error();
							$this->addLog(sprintf($this->LNG["restore_{$m[1]}"], $m[2]));
							$ei = 0;
						}
					}
					$ex = 0;
					break;
				default: 
					$insert = '';
					$ex = 1;
			}
			if($ex) {
				//error_log("[index.php]\n{$q}\n", 3, "error.log");
				$this->rtl[3] = ftell($this->fh_tmp) - $seek;
				fseek($this->fh_rtl, 0);
				$this->rtl[1] = time();
				fwrite($this->fh_rtl, implode("\t", $this->rtl));
				if(mysql_query($q)) {
					if($insert) {
						$c = 1;
					}
				}
				else {
					error_log("[index.php]\n{$q}\n", 3, "error.log");
					sxd_my_error();
				}
				
				if($c){
					$i = mysql_affected_rows();
					$this->rtl[3] = ftell($this->fh_tmp) - $seek;
					$this->rtl[7] += $i;
					$this->rtl[10] += $i;
					fseek($this->fh_rtl, 0);
					$this->rtl[1] = time();
					fwrite($this->fh_rtl, implode("\t", $this->rtl));
					$c = 1;
				}
			}
			
		}
		// Включаем ключи
		$this->addLog($this->LNG['restore_keys']);
		$this->rtl[4] = 'EK';
		$this->rtl[5] = '';
		$this->rtl[6] = '';
		$this->rtl[7] = 0;
		$this->rtl[8] = 0;
		foreach($this->JOB['todo']['TA'] AS $tab){
			if ($tab[0] == 'TC') continue;
			mysql_query("ALTER TABLE `{$tab[1]}` ENABLE KEYS") or sxd_my_error();
			$this->rtl[1] = time();
			$this->rtl[5] = $tab[1];
			fseek($this->fh_rtl, 0);
			fwrite($this->fh_rtl, implode("\t", $this->rtl));
		}
		$this->rtl[4] = 'EOJ';
		$this->rtl[5] = round(array_sum(explode(' ', microtime())) - $this->rtl[11], 4);
		
		fseek($this->fh_rtl, 0);
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$this->addLog(sprintf($this->LNG['restore_end'], $this->JOB['db']));
		fclose($this->fh_log);
		fclose($this->fh_rtl);
	}
	function addBackupJob($job) {
		$this->closeConnect();
		// Создаем новое задание
		$this->JOB = $job;
		mysql_select_db($this->JOB['db']);
		// Создаем список объектов и фильтр
		$filter = $object = array();
		$this->createFilters($this->JOB['obj'], $filter, $object);
		$queries = array(
			array('TABLE STATUS', 'Name', 'TA')
		);
		if (V_MYSQL > 50014) {
			$queries[] = array("PROCEDURE STATUS WHERE db='{$this->JOB['db']}'", 'Name', 'PR');
			$queries[] = array("FUNCTION STATUS WHERE db='{$this->JOB['db']}'", 'Name', 'FU');
			$queries[] = array('TRIGGERS', 'Trigger', 'TR');
			if(V_MYSQL > 50100) $queries[] = array('EVENTS', 'Name', 'EV');
		}
		$todo = $header = array();
		$tabs = $rows = 0;
		$only_create = explode(' ', $this->CFG['only_create']);
		foreach($queries AS $query){
			$t = $query[2];
			if($t == 'TA' && (!empty($object['TC']) || !empty($filter['TC']))) {}
			elseif(empty($object[$t]) && empty($filter[$t])) continue;
			$r = mysql_query('SHOW ' . $query[0]) or sxd_my_error();
			if (!$r) continue;
			$todo[$t] = array();
			$header[$t] = array();
			
			while($item = mysql_fetch_assoc($r)){
				$n = $item[$query[1]];
				switch($t){
					case 'TA':
					case 'TC':
						if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) {
							if(sxd_check($n, $object['VI'], $filter['VI'])){
								$todo['VI'] = array();
								$header['VI']= array();
							}
							continue;
						}
						elseif(sxd_check($n, $object['TA'], $filter['TA'])){
							$engine = V_MYSQL > 40101 ? $item['Engine'] : $item['Type'];
							$t = in_array($engine, $only_create) ? 'TC' : 'TA';
						}
						elseif(sxd_check($n, $object['TC'], $filter['TC'])) {
							$t = 'TC';
							$item['Rows'] = $item['Data_length'] = '';
						}
						else continue;
						$todo['TA'][]   = array($t, $n, !empty($item['Collation']) ? $item['Collation'] : '', $item['Auto_increment'], $item['Rows'], $item['Data_length']);
						$header['TA'][] = "{$n}`{$item['Rows']}`{$item['Data_length']}";
						$tabs++;
						$rows += $item['Rows'];
					break;
					default:
						if(sxd_check($n, $object[$t], $filter[$t])) {
							$todo[$t][] = array($t, $n, !empty($item['collation_connection']) ? $item['collation_connection'] : '');
							$header[$t][] = $n;
						}
				}
			}
			
		}
		if (V_MYSQL > 50014 && (!empty($object['VI']) || !empty($filter['VI']))) {
			// Бэкап обзоров, нужно отсортировать зависимые
			$r = mysql_query("SELECT table_name, view_definition /*!50121 , collation_connection */ FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = '{$this->JOB['db']}'") or sxd_my_error();
			$views = $dumped = $views_collation = array();
			$re = "/`{$this->JOB['db']}`.`(.+?)`/";
			while($item = mysql_fetch_assoc($r)){
				preg_match_all($re, preg_replace("/^select.+? from/i", '', $item['view_definition']), $m);
				$used = $m[1];	
				$views_collation[$item['table_name']] = !empty($item['collation_connection']) ? $item['collation_connection'] : '';
				$views[$item['table_name']] = $used;
			}
			
			while (count($views) > 0) {
				foreach($views AS $n => $view) {
					$can_dumped = true;
					foreach($view AS $k) {
						if (isset($views[$k]) && !isset($dumped[$k])) $can_dumped = false;	
					}
					if ($can_dumped) {
						if(sxd_check($n, $object['VI'], $filter['VI'])){
							$todo['VI'][] = array('VI', $n, $views_collation[$n]);
							$header['VI'][] = $n;
						}
						$dumped[$n] = 1;
						unset($views[$n]);
					}
				}
			}
			unset($dumped);
			unset($views);
			unset($views_collation);
		}
		$this->JOB['file_tmp'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.tmp';
		$this->JOB['file_rtl'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.rtl';
		$this->JOB['file_log'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.log';
		$this->JOB['file_stp'] = $this->CFG['backup_path'] . $this->JOB['job'] . '.stp';
		if(file_exists($this->JOB['file_stp'])) unlink($this->JOB['file_stp']);
		$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'w');
		$this->JOB['file'] = sprintf('%s_%s.%s', (isset($this->JOB['title']) ? $this->JOB['job'] : $this->JOB['db']), date('Y-m-d_H-i-s'), $this->JOB['file_ext']);
		$this->JOB['file_name'] = $this->CFG['backup_path'] . $this->JOB['file'];
		$this->JOB['todo'] = $todo;
		$this->saveJob($this->JOB['job'], $this->JOB);
		$fcache = implode('|', array('#SXD20', V_SXD, V_MYSQL, V_PHP, date('Y.m.d H:i:s'), $this->JOB['db'], $this->JOB['charset'], $tabs, $rows, mysql_escape_string($this->JOB['comment']))) . "\n";
		foreach($header AS $t => $o){
			if (!empty($o)) $fcache .= "#{$t} " . implode('|', $o) . "\n";	
		}
		$this->fh_rtl = fopen($this->JOB['file_rtl'], 'wb');
		$this->fh_log = fopen($this->JOB['file_log'], 'wb');
		$this->rtl = array(time(), time(), $rows, 0, '', '', '', 0, 0, 0, 0, TIMER, "\n");
		$fcache .= "#EOH\n\n";
		$this->write($fcache);
		$this->addLog(sprintf($this->LNG['backup_begin'], $this->JOB['db']));
		$this->runBackupJob();
	}
	function runBackupJob($continue = false){
		if($continue){
			$this->fh_tmp = $this->openFile($this->JOB['file_tmp'], 'a');
			mysql_select_db($this->JOB['db']);
		}
		mysql_query("SET SQL_QUOTE_SHOW_CREATE = 1");
		$types = array('VI' => 'View', 'PR' => 'Procedure', 'FU' => 'Function', 'TR' => 'Trigger', 'EV' => 'Event');
		$fcache = '';
		$writes = 0;
		
		mysql_query("SET SESSION character_set_results = '" . ($this->JOB['charset'] ? $this->JOB['charset'] : 'binary') ."'") or sxd_my_error();
		$time_old = time();
		$exit_time = $time_old + $this->CFG['time_web'] - 1;
		$no_cache = V_MYSQL < 40101 ? 'SQL_NO_CACHE ' : '';
		foreach($this->JOB['todo'] AS $t => $o){
			if (empty($this->rtl[4])) $this->rtl[4] = $t;
			elseif ($this->rtl[4] != $t) continue;
			foreach($o AS $n){ 
				if (empty($this->rtl[5])) {
					$this->rtl[5] = $n[1];
					$this->rtl[7] = 0;
					$this->rtl[8] = !empty($n[4]) ? $n[4] : 0;
				}
				elseif ($this->rtl[5] != $n[1]) continue;
				// Делаем бэкап
				switch($n[0]){
					case 'TC':
					case 'TD': 				
					case 'TA':
						$from = '';
						if ($n[0] == 'TC' || $this->rtl[7] == 0){
							// Бэкап структуры таблицы
							$r = mysql_query("SHOW CREATE TABLE `{$n[1]}`") or sxd_my_error();
							$item = mysql_fetch_assoc($r);
						    $fcache .= "#\tTC`{$n[1]}`{$n[2]}\t;\n{$item['Create Table']}\t;\n";
						    $this->addLog(sprintf($this->LNG['backup_TC'], $n[1]));
			            	$this->rtl[7] = 0; 
						    if($n[0] == 'TC' || !$n[4]) break;
						    // Бэкапим данные таблицы
							$fcache .= "#\tTD`{$n[1]}`{$n[2]}\t;\nINSERT INTO `{$n[1]}` VALUES \n";
						}
						else {
							$from = " LIMIT {$this->rtl[7]}, {$this->rtl[8]}";
							$this->addLog(sprintf("{$this->LNG['backup_TC']} {$this->LNG['continue_from']}", $n[1], $this->rtl[7]));
						}
						// Определяем типы полей
						$notNum = array();
						$r = mysql_query("SHOW COLUMNS FROM `{$n[1]}`") or sxd_my_error();
			            $fields = 0;
			            while($col = mysql_fetch_array($r)) {
			            	// TODO: проверить типы SET, ENUM и BIT
            				$notNum[$fields] = preg_match("/^(tinyint|smallint|mediumint|bigint|int|float|double|real|decimal|numeric|year)/", $col['Type']) ? 0 : 1; 
            				$fields++;
			            }
			            $time_old = time();
			            $z = 0;
			            // Достаем данные
			            $r = mysql_unbuffered_query("SELECT {$no_cache}* FROM `{$n[1]}`{$from}");
			            while($row = mysql_fetch_row($r)) {
			            	 if (strlen($fcache) >= 61440) {
			            	 	$z = 0;
								if($time_old < time()) {
									if(file_exists($this->JOB['file_stp'])){
										$type = file_get_contents($this->JOB['file_stp']);
										$this->rtl[9] = !empty($type) ? $type : 2;
										$this->write($fcache);
										if($type == 1) {
											
										}
										unset($this->rtl);
										exit;
									}
									$time_old = time();
									if($time_old >= $exit_time){
										$this->rtl[9] = 3;
										$this->write($fcache);
										unset($this->rtl);
										exit;
									}
									clearstatcache(); 
								}
								$this->write($fcache); 
							}
							for($k = 0; $k < $fields; $k++){
								if(!isset($row[$k])) {$row[$k] = '\N';}
								elseif($notNum[$k]) {$row[$k] =  '\'' . mysql_escape_string($row[$k]) . '\'';}
							}
							$fcache .= '(' . implode(',', $row) . "),\n";
							$this->rtl[7]++;  
							$this->rtl[10]++;
						}
						unset($row);
						mysql_free_result($r);
						$fcache = substr_replace($fcache, "\t;\n",  -2, 2);
					break;

					/*case 'VI':
					case 'PR': 
					case 'FU':
					case 'EV':
					case 'TR':*/
					default:
						if(V_MYSQL < 50121 && $n[0] == 'TR'){
							// SHOW CREATE TRIGGER отсутствует до MySQL 5.1.21
							$r = mysql_query("SELECT * FROM `INFORMATION_SCHEMA`.`TRIGGERS` WHERE `TRIGGER_SCHEMA` = '{$this->JOB['db']}' AND `TRIGGER_NAME` = '{$n[1]}'") or sxd_my_error();
							$item = mysql_fetch_assoc($r);
							$fcache .= "#\tTR`{$n[1]}`{$n[2]}\t;\nCREATE TRIGGER `{$item['TRIGGER_NAME']}` {$item['ACTION_TIMING']} {$item['EVENT_MANIPULATION']} ON `{$item['EVENT_OBJECT_TABLE']}` FOR EACH ROW {$item['ACTION_STATEMENT']}\t;\n";
						}
						else {
							$this->addLog(sprintf($this->LNG['backup_' . $n[0]], $n[1]));
							$r = mysql_query("SHOW CREATE {$types[$n[0]]} `{$n[1]}`") or sxd_my_error();
							$item = mysql_fetch_assoc($r);
							$fcache .= "#\t{$n[0]}`{$n[1]}`{$n[2]}\t;\n" . preg_replace("/DEFINER=`.+?`@`.+?` /", '', ($n[0] == 'TR' ? $item['SQL Original Statement'] : $item['Create ' . $types[$n[0]]])) . "\t;\n";
						}
				}
				
				$this->rtl[5] = '';
			}
			$this->rtl[4] = '';
		}
		$this->rtl[4] = 'EOJ';
		$this->rtl[5] = round(array_sum(explode(' ', microtime())) - $this->rtl[11], 4);
		$this->rtl[6] = '';
		$this->rtl[7] = 0;
		$this->rtl[8] = 0;
		$this->write($fcache);
		fclose($this->fh_tmp);
		rename($this->JOB['file_tmp'], $this->JOB['file_name']);
		$this->addLog(sprintf($this->LNG['backup_end'], $this->JOB['db']));
		
		if ($this->JOB['del_time'] || $this->JOB['del_count']) {
            $this->addLog($this->LNG['autodelete']);
            $deldate = '';
            if (!empty($this->JOB['del_time'])){ // Удаление по дням
                $deldate = date("Y-m-d_H-i-s", time() - intval($this->JOB['del_time']) * 86400);
            }
            $deleted = false;
            if ($dh = opendir($this->CFG['backup_path'])) {
                $files = array();
                $name = isset($this->JOB['title']) ? $this->JOB['job'] : $this->JOB['db'];
                while (false !== ($file = readdir($dh))) { 
                    if (preg_match("/^{$name}_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql/", $file, $m)) { 
                        if ($deldate && $m[1] < $deldate) {
                            if(unlink($this->CFG['backup_path'] . $file)) $this->addLog(sprintf($this->LNG['del_by_date'], $file));
                            else  $this->addLog(sprintf($this->LNG['del_fail'], $file));
                            $deleted = true;
                        }
                        else {$files[$m[1]] = $file;}
                    }
                }
                closedir($dh);
                // Сортируем файлы по дате и удаляем самые старые
                if (!empty($this->JOB['del_count'])){
                    ksort($files);
                    $file_to_delete = count($files) - $this->JOB['del_count'];
                    foreach ($files AS $file){
                        if ($file_to_delete-- > 0){ 
                        	if(unlink($this->CFG['backup_path'] . $file)) $this->addLog(sprintf($this->LNG['del_by_count'], $file));
                            else  $this->addLog(sprintf($this->LNG['del_fail'], $file));
                            $deleted = true;
                        }
                    }
                }
            }
            if(!$deleted) $this->addLog($this->LNG['del_nothing']);
		}
		fclose($this->fh_log);
		fclose($this->fh_rtl);
	}
	function setNames($collation){
		if(empty($collation)) return;
		if($this->rtl[6] != $collation) {
			mysql_query('SET NAMES \'' . preg_replace('/^(\w+?)_/', '\\1\' COLLATE \'\\1_', $collation) . '\'') or sxd_my_error();
			/*if(!$this->rtl[7])*/ $this->addLog(sprintf($this->LNG['set_names'], $collation));
			$this->rtl[6] = $collation;	
		}
	}
	function write(&$str){
		fseek($this->fh_rtl, 0);
		$this->rtl[1] = time();
		$this->rtl[3] += fwrite($this->fh_tmp, $str);
		fwrite($this->fh_rtl, implode("\t", $this->rtl));
		$str = '';
	}
	function addLog($str, $type = 1){
		fwrite($this->fh_log, date('Y.m.d H:i:s') . "\t{$type}\t{$str}\n");
	}
	function getDBList(){
		$dbs = $items = array();
        if (!V_MYSQL) return $dbs; 
        $qq = (V_MYSQL < 50000) ? '' : '\'';
		if ($this->CFG['my_db']) {
			$tmp = explode(',', $this->CFG['my_db']);
			foreach($tmp AS $d){
				$d = trim($d);
				$items[] = $qq . mysql_escape_string($d) . $qq;
				$dbs[$d] = "{$d} (0)";
			}
		}
		else{
			$result = mysql_query("SHOW DATABASES") or sxd_my_error();
    		while($item = mysql_fetch_row($result)){
    			if($item[0] == 'information_schema' || $item[0] == 'mysql') continue;
    			$items[] = $qq . mysql_escape_string($item[0]) . $qq;
    			$dbs[$item[0]] = "{$item[0]} (0)";
    		}	
		}
		if(V_MYSQL < 50000){
			foreach($items AS $item){
    			$tables = mysql_query("SHOW TABLES FROM `{$item}`") or sxd_my_error();
    			if ($tables) {
    	  			$tabs = mysql_num_rows($tables);
    	  			$dbs[$item] = "{$item} ({$tabs})";
    	  		}
			}
		}
		else {
			$where = (count($items) > 0) ? 'WHERE `table_schema` IN (' . implode(',', $items) . ')' : '';
			$result = mysql_query("SELECT `table_schema`, COUNT(*) FROM `information_schema`.`tables` {$where} GROUP BY `table_schema`") or sxd_my_error();
			while($item = mysql_fetch_row($result)){
    			if($item[0] == 'information_schema' || $item[0] == 'mysql') continue; 
    			$dbs[$item[0]] = "{$item[0]} ({$item[1]})";
    		}
		}
	    return $dbs;
	}
	function getCharsetList(){
		$tmp = array(0 => '- auto -');
		if (!V_MYSQL) return $tmp; 
		if(V_MYSQL > 40101) {
			$def_charsets = '';
			if(!empty($this->CFG['charsets'])){
				$def_charsets = preg_match_all("/([\w*?]+)\s*/", $this->CFG['charsets'], $m, PREG_PATTERN_ORDER) ? '/^(' . str_replace(array('?','*'), array('.','\w+?'), implode('|', $m[1])) . ')$/i' : '';
			}
    		$r = mysql_query("SHOW CHARACTER SET") or sxd_my_error(); 
    		if ($r) {
    			while($item = mysql_fetch_assoc($r)){
    	  			if (empty($def_charsets) || preg_match($def_charsets, $item['Charset'])) $tmp[$item['Charset']] = "{$item['Charset']}"; // ({$item['Description']})
    			}
			}
		}
	    return $tmp;
	}
	function getCollationList(){
		$tmp = array(); 
		if (!V_MYSQL) return $tmp; 
		if(V_MYSQL > 40101) {
			$def_charsets = '';
			if(!empty($this->CFG['charsets'])){
				$def_charsets = preg_match_all("/([\w*?]+)\s*/", $this->CFG['charsets'], $m, PREG_PATTERN_ORDER) ? '/^(' . str_replace(array('?','*'), array('.','\w+?'), implode('|', $m[1])) . ')$/i' : '';
			}
    		$r = mysql_query("SHOW COLLATION") or sxd_my_error(); 
    		if ($r) {
    			while($item = mysql_fetch_assoc($r)){
    	  			if (empty($def_charsets) || preg_match($def_charsets, $item['Charset'])) $tmp[$item['Charset']][$item['Collation']] = $item['Default'] == 'Yes' ? 1 : 0; 
    			}
			}
		}
	    return $tmp;
	}
	function getObjects($tree, $db_name){
		mysql_select_db($db_name);
		// Достаем таблицы
		$r = mysql_query('SHOW TABLE STATUS') or sxd_my_error();
		$tab_prefix_last = $tab_prefix = '*';
		$objects = array('TA' => array(), 'VI' => array(), 'PR' => array(), 'FU' => array(), 'TR' => array(), 'EV' => array());
		if($r){
			while($item = mysql_fetch_assoc($r)){
				if(V_MYSQL > 40101 && is_null($item['Engine']) && preg_match('/^VIEW/i', $item['Comment'])) {
					$objects['VI'][]= $item['Name'];
				}
				else{
					$objects['TA'][] = array($item['Name'], $item['Rows'], $item['Data_length']);
				} 
			}
			
			if (V_MYSQL > 50014 && $tree != 'services') {
				$shows = array(
					"PROCEDURE STATUS WHERE db='{$db_name}'", 
					"FUNCTION STATUS WHERE db='{$db_name}'",
					'TRIGGERS'
				);
				if(V_MYSQL > 50100) $shows[] = "EVENTS WHERE db='{$db_name}'";
				// TODO: Поправить проверку событий и триггеров
				for($i = 0, $l = count($shows); $i < $l; $i++){
					$r = mysql_query('SHOW ' . $shows[$i]);
					if($r && mysql_num_rows($r) > 0) {
						$col_name = $shows[$i] == 'TRIGGERS' ? 'Trigger' : 'Name';
						$type = substr($shows[$i], 0, 2);
						while($item = mysql_fetch_assoc($r)){
							$objects[$type][] = $item[$col_name];	
						}
					}
				}
			}
			else {
				$objects['VI'] = array();
			}
		}
		return $this->formatTree($tree, $objects);
	}
	function getFileObjects($tree, $name, $formatTree = true){
		// Достаем таблицы
		$objects = array('TA' => array(), 'VI' => array(), 'PR' => array(), 'FU' => array(), 'TR' => array(), 'EV' => array());
		if(!preg_match('/\.sql(\.(gz|bz2))?$/i', $name, $m)) return '';
		$name = $this->CFG['backup_path'] . $name;
		if(!is_readable($name)) {return "sxd.tree.{$tree}.error(sxd.lng('err_fopen'))";}
		$fp   = $this->openFile($name, 'r');
		$temp = fread($fp, 60000);
		// Формат файла Sypex Dumper 2 - SXD20
		if(!preg_match('/^(#SXD20\|.+?)\n#EOH\n/s', $temp, $m)) return "sxd.tree.{$tree}.error(sxd.lng('err_sxd2'));z('restore_savejob').disabled = z('restore_runjob').disabled = true;";
		$head = explode("\n", $m[1]);
		$h = explode('|', $head[0]);
		for($i = 1, $c = count($head); $i < $c; $i++){
			$objects[substr($head[$i], 1,2)] = explode('|', substr($head[$i], 4));
		}
		for($i = 0, $l = count($objects['TA']); $i < $l; $i++){
			$objects['TA'][$i] = explode('`', $objects['TA'][$i]);
		}
		return $formatTree ? $this->formatTree($tree, $objects) .  "sxd.comment.restore.value = '{$h[9]}';z('restore_savejob').disabled = z('restore_runjob').disabled = false;" : $objects;
	}
	function formatTree($tree, &$objects){
		$obj = '';
		$pid = $row = 1;
		$info = array(
			'TA' => array($this->LNG['obj_tables'], 1),
			'VI' => array($this->LNG['obj_views'], 3), 
			'PR' => array($this->LNG['obj_procs'], 5), 
			'FU' => array($this->LNG['obj_funcs'], 7), 
			'TR' => array($this->LNG['obj_trigs'], 9), 
			'EV' => array($this->LNG['obj_events'], 11)
		);
		// Находим таблицы с префиксами
		$tab_prefix_last = $tab_prefix = '*';
		for($i = 0, $l = count($objects['TA']); $i < $l; $i++){
			$t = $objects['TA'][$i];
			$tab_prefix = preg_match("/^([a-z0-9]+_)/", $t[0], $m) ? $m[1] : '*';
			if ($tab_prefix != $tab_prefix_last) {
				if ($tab_prefix != '*') $objects['TA']['*'][] = $tab_prefix;
				$tab_prefix_last = $tab_prefix;
			}
			$objects['TA'][$tab_prefix][] = $t;
			unset($objects['TA'][$i]);
		}
		foreach($objects AS $type => $o){
			if(!count($o)) continue;
			if($type == 'TA') {
				$open_childs = count($o['*']) > 1 ? 0 : 1;
				$obj .= "[{$row},0,'" . mysql_escape_string($info[$type][0]) . "',1,1,1],";
				$row++;
				foreach($o['*'] AS $value){
					if(is_string($value)){
						if(count($o[$value]) > 1)	{
							$obj .= "[{$row},1,'{$value}*',1,1,{$open_childs}],";	
							$pid = $row++; 
							for($i = 0, $l = count($o[$value]); $i < $l; $i++){
								$checked = ($o[$value][$i][1] == '' && $o[$value][$i][2] == '') ? 2 : 1;
								$obj .= "[{$row},{$pid},'" . mysql_escape_string($o[$value][$i][0]) . "',2,{$checked},{$o[$value][$i][2]}],";
								$row++;
							}
						}
						else {
							$value = $o[$value][0];	
						}
					}
					//$pid = 1;
					if (is_array($value)){
						$checked = ($value[1] == '' && $value[2] == '') ? 2 : 1;
						$obj .= "[{$row},1,'{$value[0]}',2,{$checked},{$value[2]}],";
						$row++;	
					}
				}
			}
			else {
				$obj .= "[{$row},0,'" . mysql_escape_string($info[$type][0]) . "',{$info[$type][1]},1,1],";
				$pid = $row++;
				$info[$type][1]++;
				for($i = 0, $l = count($o); $i < $l; $i++){
					$o[$i] = mysql_escape_string($o[$i]);
					$obj .= "[{$row},{$pid},'{$o[$i]}',{$info[$type][1]},1,0],";
					$row++;	
				}
			} 
		}
		return $obj ? 'sxd.tree.' . $tree . '.drawTree([' . substr_replace($obj, ']',  -1) . ');' : "sxd.tree.{$tree}.error(sxd.lng('err_empty_db'))";	
	}
	function getFileList(){
		$files = array();
		if (is_dir($this->CFG['backup_path']) && false !== ($handle = opendir($this->CFG['backup_path']))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^.+?\.sql(\.(gz|bz2))?$/", $file)) {
                    $files[$file] = $file;
                }
            }
            closedir($handle);
        }
        ksort($files);
		return $files;
	}
	function getSavedJobs(){
		$sj = array('sj_backup' => array(), 'sj_restore' => array(),);
		if (is_dir($this->CFG['backup_path']) && false !== ($handle = opendir($this->CFG['backup_path']))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^sj_(.+?)\.job.php$/", $file)) {
                	include($this->CFG['backup_path'] . $file);
                    $sj['sj_' . $JOB['type']][$JOB['job']] = "<b>{$JOB['job']}</b><br><i>{$JOB['title']}&nbsp;</i>";
                }
            }
            closedir($handle);
        }
        if(count($sj['sj_backup']) > 0){
        	ksort($sj['sj_backup']);	
		}
        else {
        	$sj['sj_backup'] = array(0 => '<b>No Saved Jobs</b><br>' . $this->LNG['no_saved']);	
		}
		if(count($sj['sj_restore']) > 0){
        	ksort($sj['sj_restore']);	
		}
        else {
        	$sj['sj_restore'] = array(0 => '<b>No Saved Jobs</b><br>' . $this->LNG['no_saved']);	
		}
		return "sxd.clearOpt('sj_backup');sxd.clearOpt('sj_restore');sxd.addOpt(" . sxd_php2json($sj) . ");";
	}
	function getFileListExtended(){
		$files = array();
		if (is_dir($this->CFG['backup_path']) && false !== ($handle = opendir($this->CFG['backup_path']))) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match("/^.+?\.sql(\.(gz|bz2))?$/", $file, $m)) {
                    $fp   = $this->openFile($this->CFG['backup_path'] . $file, 'r');
                    $ext  = !empty($m[2]) ? $m[2] : 'sql';
                    $temp = fgets($fp);
                    if(preg_match('/^(#SXD20\|.+?)\n/s', $temp, $m)){
                    	$h = explode('|', $m[1]);
                    	$files[] = array($h[5], substr($h[4], 0, -3), $ext, $h[7], number_format($h[8], 0, '', ' '), filesize($this->CFG['backup_path'] . $file), $h[9], $file);	
					}
                }
            }
            closedir($handle);
        }
        function s($a, $b){
		    return strcmp($b[1], $a[1]);
		}
        usort($files, 's');
		return 'sxd.files.clear();sxd.files.add(' . sxd_php2json($files) . ');';
	}
	function saveJob($job, $config){
		$this->saveToFile($this->CFG['backup_path'] . $job . '.job.php', "<?php\n\$JOB = " . var_export($config, true) . ";\n" . "?>");
	}
	function openFile($name, $mode){
		if($mode == 'r') {
			if(preg_match('/\.(sql|sql\.bz2|sql\.gz)$/i', $name, $m)) $this->JOB['file_ext'] = strtolower($m[1]);
		}
		else{
			switch($this->JOB['zip']) {
				case 0 : $this->JOB['file_ext'] = 'sql'; break;
				case 10: $this->JOB['file_ext'] = 'sql.bz2'; break;
				default: $this->JOB['file_ext'] = 'sql.gz'; break; 
			}
		}
		switch ($this->JOB['file_ext']){
			case 'sql':
				return fopen($name, "{$mode}b");
				break;
			case 'sql.bz2':
				return bzopen($name, $mode);
				break;
			case 'sql.gz':
				return gzopen($name, $mode . ($mode == 'w' ? $this->JOB['zip'] : ''));
				break;
			default: return false;
		}
	}
}
function sxd_read_sql($f, &$seek, $ei){
	static $l = '';
	static $r = 0;
	$fs = ftell($f);
	while($r || $s = fread($f, 61440)){
		if(!$r) $l .= $s;
		$pos = strpos($l, "\t;\n");
		if ($pos !== false) {
			// Есть окончание запроса
			$q = substr($l, 0, $pos);
			$l = substr($l, $pos+3);
			$r = 1;
			$seek = strlen($l);
			return $q;
		}
		if($ei) {
			$pos = strrpos($l, "\n");
			if($pos !== false && $l{$pos-1} === ',') {
				// Окончание не найдено
				$q = substr($l, 0, $pos-1);
				$l = substr($l, $pos+1);
				$seek = strlen($l);
				$r = 0;
				return $q;
			}
		}
		$r = 0;	
	}
	if (!empty($l)) {
		return $l;
	}
	return false;
}
function sxd_check($n, $obj, $filt){
	return isset($obj[$n]) || ($filt && preg_match($filt, $n));
}
function sxd_php2json($obj){
	if(count($obj) == 0) return '[]';
	$is_obj = isset($obj[count($obj) - 1]) ? false : true;
	$str = $is_obj ? '{' : '[';
    foreach ($obj AS $key  => $value) {
    	$str .= $is_obj ? "'" . addcslashes($key, "\n\r\t'\\/") . "'" . ':' : ''; 
        if     (is_array($value))   $str .= sxd_php2json($value);
        elseif (is_null($value))    $str .= 'null';
        elseif (is_bool($value))    $str .= $value ? 'true' : 'false';
		elseif (is_numeric($value)) $str .= $value;
		else                        $str .= "'" . addcslashes($value, "\n\r\t'\\/") . "'";
		$str .= ',';
    }
	return  substr_replace($str, $is_obj ? '}' : ']', -1);
}
function sxd_ver2int($ver){
	return preg_match("/^(\d+)\.(\d+)\.(\d+)/", $ver, $m) ? sprintf("%d%02d%02d", $m[1], $m[2], $m[3]) : 0;
}
function sxd_error_handler($errno, $errmsg, $filename, $linenum, $vars){
    global $SXD;
    if($SXD->try) return;
    if(strpos($errmsg, 'timezone settings')) return;
    $errortype = array(1 => 'Error', 2 => 'Warning', 4 => 'Parsing Error', 8 => 'Notice', 16 => 'Core Error', 32 => 'Core Warning', 64 => 'Compile Error', 
					   128 => 'Compile Warning', 256 => 'MySQL Error', 512 => 'Warning', 1024 => 'Notice');
	$str = "{$errortype[$errno]}: {$errmsg} ({$filename}:{$linenum})";
	if(SXD_DEBUG) error_log("[index.php]\n{$str}\n", 3, "error.log");
	
    if($errno == 8 || $errno == 1024) {
    	if (!$SXD->fh_log && !$SXD->fh_rtl) echo isset($_POST['ajax']) ? "alert('" . mysql_escape_string($str) . "');" : $str;
    	else {
    		fwrite($SXD->fh_log, date('Y.m.d H:i:s') . "\t3\t{$str}\n");
		}
	}
    elseif($errno < 1024) {
    	$SXD->error = true;
    	if (!$SXD->fh_log && !$SXD->fh_rtl) echo isset($_POST['ajax']) ? "alert('" . mysql_escape_string($str) . "');" : $str;
    	else {
    		$SXD->rtl[1] = time();
    		$SXD->rtl[9] = 5;
    		fseek($SXD->fh_rtl, 0);
			fwrite($SXD->fh_rtl, implode("\t", $SXD->rtl));
    		fwrite($SXD->fh_log, date('Y.m.d H:i:s') . "\t4\t{$str}\n");
    		unset($SXD->rtl);
		}
		
    	die;
	}
}
function sxd_my_error(){
	trigger_error(mysql_error(), E_USER_ERROR);	
}
function sxd_shutdown(){
	global $SXD;
	if(isset($SXD->fh_rtl) && is_resource($SXD->fh_rtl) && !empty($SXD->rtl) && empty($SXD->error)) {
		$SXD->rtl[1] = time();
		if(!empty($SXD->JOB['file_stp']) && file_exists(dirname(__FILE__) . '/' . $SXD->JOB['file_stp'])){
			$type = file_get_contents(dirname(__FILE__) . '/' . $SXD->JOB['file_stp']);
			$SXD->rtl[9] = !empty($type) ? $type : 2;
		}
		else $SXD->rtl[9] = 5;
		fseek($SXD->fh_rtl, 0);
		fwrite($SXD->fh_rtl, implode("\t", $SXD->rtl));
	}
}
function sxd_antimagic($arr){
	return is_array($arr) ? array_map('sxd_antimagic', $arr) : stripslashes($arr);
}
// Templates
function sxd_tpl_page(){
global $SXD;
return <<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$SXD->name}</title>
<link rel="stylesheet" type="text/css" href="load.php?sxd.v206.css">
<script type="text/javascript" src="load.php?sxd.v206.js"></script>
<script type="text/javascript" src="load.php?{$SXD->LNG['name']}.lng.js"></script>
<link rel="shortcut icon" href="load.php?favicon.v206.ico">
</head>

<body>
<div id="main_div">
	<div id="header">{$SXD->name}</div>
	<div id="sxdToolbar"></div>
	<div id="name"><div id="loading"></div><b></b></div>
	<div id="content" class="content">
		<table cellspacing="0" id="tab_backup">
			<tr>
				<td width="242" valign="top">
					<div class="caption">{$SXD->LNG['combo_db']}</div><div id="backup_db"></div>
					<div class="caption">{$SXD->LNG['combo_charset']}</div><div id="backup_charset"></div>
					<div class="caption">{$SXD->LNG['combo_zip']}</div><div id="backup_zip"></div>
					<div class="caption">{$SXD->LNG['combo_comments']}</div>
					<div class="border"><textarea cols="10" rows="3" id="backup_comment"></textarea></div>
					<div class="caption" style="margin-top:12px;">
						<fieldset><legend>{$SXD->LNG['del_legend']}</legend>
						<div class="caption">&nbsp;– {$SXD->LNG['del_date']}</div>
						<div class="caption">&nbsp;– {$SXD->LNG['del_count']}</div>
						</fieldset>
					</div>
				</td>
				<td width="308" valign="top">
					<div class="caption">{$SXD->LNG['tree']}</div><div id=backup_tree class="zTree"></div>
				</td>
			</tr>
			<tr><td></td><td align="right"><input type="button" value="{$SXD->LNG['btn_save']}" onclick="sxd.showDialog('savejob');"> <input type="button" value="{$SXD->LNG['btn_exec']}" onclick="sxd.runBackup();"></td></tr>
		</table>
		
		<table cellspacing="0" id="tab_restore" style="display:none;">
			<tr>
				<td width="242" valign="top">
					<div class="caption">{$SXD->LNG['combo_db']}</div><div id="restore_db"></div>
					<div class="caption">{$SXD->LNG['combo_charset']}</div><div id="restore_charset"></div>
					<div class="caption">{$SXD->LNG['combo_file']}</div><div id="restore_file"></div>
					<div class="caption">{$SXD->LNG['combo_comments']}</div>
					<div class="border"><textarea cols="10" rows="3" id="restore_comment" readonly></textarea></div>
					<div class="caption">{$SXD->LNG['combo_strategy']}</div><div id="restore_type"></div>
					<div class="caption" style="margin-top:17px;">
						<fieldset><legend>{$SXD->LNG['ext_legend']}</legend>
						<div class="caption"><label><input type="checkbox" id="correct"> {$SXD->LNG['correct']}</label></div>
						<div class="caption"><label><input type="checkbox" id="autoinc"> {$SXD->LNG['autoinc']}</label></div>
						</fieldset>
					</div>
				</td>
				<td width="308" valign="top">
					<div class="caption">{$SXD->LNG['tree']}</div><div id=restore_tree class="zTree"></div>
				</td>
			</tr>
			<tr><td></td><td align="right"><input type="button" value="{$SXD->LNG['btn_save']}" onclick="sxd.showDialog('savejob');" id=restore_savejob> <input type="button" value="{$SXD->LNG['btn_exec']}" onclick="sxd.runRestore();" id=restore_runjob></td></tr>
		</table>
		
		<table cellspacing="0" id="tab_log" style="display:none;">
			<tr>
				<td valign="top" colspan=2>
					<div id=sxdGrid1></div> 
				</td>
			</tr>
			<tr><td colspan=2>
			<table class=progress>
				<tr><td>{$SXD->LNG['status_current']}</td><td><div id="sxdProc1"></div></td><td width=60>{$SXD->LNG['time_elapsed']}</td><td width=40 align=right id="sxdTime1">00:00</td></tr>
				<tr><td>{$SXD->LNG['status_total']}</td><td><div id="sxdProc2"></div></td><td>{$SXD->LNG['time_left']}</td><td align=right id="sxdTime2">00:00</td></tr>
			</table>
			</td></tr>
			<tr><td width="152"><input type="button" value="{$SXD->LNG['btn_clear']}" onclick="sxd.log.clear();"></td><td width="380" align="right">
			<input type="button" value="{$SXD->LNG['btn_download']}" id="btn_down" onclick="sxd.runFiles('download', this.file);" style="display:none;">
			<input type="button" value="{$SXD->LNG['btn_again']}" id="btn_again" onclick="sxd.runAgain();" disabled>
			<input type="button" value="{$SXD->LNG['btn_stop']}" id="btn_stop" onclick="sxd.stopJob();" disabled>
			<input type="button" value="{$SXD->LNG['btn_pause']}" id="btn_pause" onclick="sxd.pauseJob();" disabled>
			<input type="button" value="{$SXD->LNG['btn_resume']}" id="btn_resume" onclick="sxd.resumeJob();" style="display:none;">
			</td></tr>
		</table>
		<table cellspacing="0" id="tab_result" style="display:none;">
			<tr>
				<td valign="top">
					<div id=sxdGrid3></div> 
				</td>
			</tr>
			<tr><td>
			<input type="button" value="{$SXD->LNG['btn_clear']}" onclick="sxd.result.clear();">
			</td></tr>
		</table>
		
		<table cellspacing="0" id="tab_files" style="display:none;">
			<tr>
				<td valign="top" colspan=2>
					<div id=sxdGrid2></div> 
				</td>
			</tr>
			<tr><td width="242"><form id="save_file" method="GET" style="visibility:hidden;display:inline;" target=save></form><input type="button" value="{$SXD->LNG['btn_delete']}" onclick="sxd.runFiles('delete')"></td><td width="290" align="right">
			<input type="button" value="{$SXD->LNG['btn_download']}" onclick="sxd.runFiles('download')">
			<input type="button" value="{$SXD->LNG['btn_open']}" onclick="sxd.runFiles('open')">
			</td></tr>
		</table>
		
		
		<table cellspacing="0" id="tab_services" style="display:none;">
			<tr>
				<td width="242" valign="top">
					<div class="caption">{$SXD->LNG['combo_db']}</div><div id="services_db"></div>
					<br>
					<div class="caption">{$SXD->LNG['opt_check']}</div><div id="services_check"></div>
					<div class="caption">{$SXD->LNG['opt_repair']}</div><div id="services_repair"></div>
				</td>
				<td width="308" valign="top">
					<div class="caption">{$SXD->LNG['tree']}</div><div id=services_tree class="zTree"></div>
				</td>
			</tr>
			<tr><td align="right" colspan=2><input type="button" value="{$SXD->LNG['btn_delete_db']}" onclick="/*sxd.showMenu({el:this}, sxd.options.services,{btn:1,width:160});*/sxd.runServices('delete')" style="float:left;"> <input type="button" value="{$SXD->LNG['btn_check']}" onclick="sxd.runServices('check')"> <input type="button" value="{$SXD->LNG['btn_repair']}" onclick="sxd.runServices('repair')"> <input type="button" value="{$SXD->LNG['btn_analyze']}" onclick="sxd.runServices('analyze')">  <input type="button" value="{$SXD->LNG['btn_optimize']}" onclick="sxd.runServices('optimize')"></td></tr>
		</table>
		<table cellspacing="0" id="tab_options" style="display:none;">
			<tr>
				<td valign="top" colspan=2>
					<div style="height: 341px;">
					<fieldset>
					<legend>{$SXD->LNG['cfg_legend']}</legend>
						<table cellspacing="0">
							<tr>
								<td width=190>{$SXD->LNG['cfg_time_web']}</td>
								<td width=45><input type="text" id="time_web" value="30" style="width:40px;"></td>
								<td align="right">{$SXD->LNG['cfg_time_cron']}</td>
								<td width=40 align="right"><input type="text" id="time_cron" value="0" style="width:40px;"></td>
							</tr>
							<tr>
								<td>{$SXD->LNG['cfg_backup_path']}</td>
								<td colspan=3><input type="text" id="backup_path" value="backup/" style="width:351px;"></td>
							</tr>
							<tr>
								<td>{$SXD->LNG['cfg_backup_url']}</td>
								<td colspan=3><input type="text" id="backup_url" value="backup/" style="width:351px;"></td>
							</tr>
							<tr>
								<td>{$SXD->LNG['cfg_globstat']}</td>
								<td colspan=3><input type="checkbox" id="globstat" value="1"></td>
							</tr>
						</table>
					</fieldset>
					<fieldset>
					<legend>{$SXD->LNG['cfg_confirm']}</legend>
						<table cellspacing="0">
							<tr>
								<td width="33%"><label><input type="checkbox" id="conf_import" value="1"> {$SXD->LNG['cfg_conf_import']}</label></td>
								<td width="34%"><label><input type="checkbox" id="conf_file" value="1"> {$SXD->LNG['cfg_conf_file']}</label></td>
								<td width="33%"><label><input type="checkbox" id="conf_db" value="1"> {$SXD->LNG['cfg_conf_db']}</label></td>
							</tr>
						</table>
					</fieldset>
					<fieldset>
					<legend>{$SXD->LNG['cfg_extended']}</legend>
						<table cellspacing="0">
							<tr>
								<td width=190>{$SXD->LNG['cfg_charsets']}</td>
								<td><input type="text" id="charsets" value="utf8 cp* latin1" style="width:351px;"></td>
							</tr>
							<tr>
								<td>{$SXD->LNG['cfg_only_create']}</td>
								<td><input type="text" id="only_create" value="MRG_MyISAM MERGE HEAP MEMORY" style="width:351px;"></td>
							</tr>
							<tr>
								<td>{$SXD->LNG['cfg_auth']}</td>
								<td><input type="text" id="auth" value="cfg mysql" style="width:351px;"></td>
							</tr>
						</table>
					</fieldset>
					</div>
				</td>
			</tr>
			<tr><td align="right" colspan=2><input type="button" value="{$SXD->LNG['btn_save']}" onclick="sxd.saveOptions();"></td></tr>
		</table>
	</div>
</div>

<div id="overlay"></div>
<div class="dialog" id ="dia_connect">
	<div class="header">{$SXD->LNG['con_header']}</div>
	<div class="content">
		<table cellspacing="5">
			<tr>
				<td valign="top">
				<fieldset>
				<legend>{$SXD->LNG['connect']}</legend>
					<table cellspacing="3">
						<tr>
							<td width="80">{$SXD->LNG['my_host']}</td>
							<td width="126"><input type="text" id="con_host" style="width:120px;"></td>
							<td width="40" align="right">{$SXD->LNG['my_port']}</td>
							<td width="36"><input type="text" id="con_port" maxlength="5" style="width:30px;"></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['my_user']}</td>
							<td colspan="3"><input type="text" id="con_user" name="user" style="width:202px;"></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['my_pass']}</td>
							<td colspan="3"><input type="password" id="con_pass" name="pass" title="{$SXD->LNG['my_pass_hidden']}" style="width:202px;"></td>
						</tr>
						<tr>
							<td></td>
							<td colspan="3"><label><input type="checkbox" id="con_comp" value="1"> {$SXD->LNG['my_comp']}</label></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['my_db']}</td>
							<td colspan="3"><input type="text" id="con_db" style="width:202px;"></td>
						</tr>
					</table></fieldset>
				</td>
			</tr>
			<tr class="buttons"><td align="right"><input type="button" value="{$SXD->LNG['btn_save']}" onclick="sxd.saveConnect();"><input type="button" value="{$SXD->LNG['btn_cancel']}" onclick="sxd.hideDialog('connect');"></td></tr>
		</table>
	</div>
</div>
<div class="dialog" id ="dia_savejob">
	<div class="header">{$SXD->LNG['sj_header']}</div>
	<div class="content">
		<table cellspacing="5">
			<tr>
				<td valign="top">
				<fieldset>
				<legend>{$SXD->LNG['sj_job']}</legend>
					<table cellspacing="3">
						<tr>
							<td width="80">{$SXD->LNG['sj_name']}</td>
							<td><input type="text" id="sj_name" style="width:202px;" maxlength="12" value="new"></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['sj_title']}</td>
							<td><input type="text" id="sj_title" maxlength="64" style="width:202px;"></td>
						</tr>
					</table></fieldset>
				</td>
			</tr>
			<tr class="buttons"><td align="right"><input type="button" value="{$SXD->LNG['btn_save']}" onclick="sxd.saveJob();"><input type="button" value="{$SXD->LNG['btn_cancel']}" onclick="sxd.hideDialog('savejob');"></td></tr>
		</table>
	</div>
</div>
<div class=dialog id="dia_createdb">
	<div class="header">{$SXD->LNG['cdb_header']}</div>
	<div class="content">
		<table cellspacing="5">
			<tr>
				<td valign="top">
				<fieldset>
				<legend>{$SXD->LNG['cdb_detail']}</legend>
					<table cellspacing="3">
						<tr>
							<td width="80">{$SXD->LNG['cdb_name']}</td>
							<td width="202"><input type="text" id="db_name" value="my_db_1" style="width:202px;"></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['combo_charset']}</td>
							<td><div id="db_charset"></div></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['combo_collate']}</td>
							<td><div id="db_charset_col"></div></td>
						</tr>
					</table>
				</fieldset>
				</td>
			</tr>
			<tr class="buttons"><td align="right"><input type="button" value="{$SXD->LNG['btn_create']}" onclick="sxd.addDb();"><input type="button" value="{$SXD->LNG['btn_cancel']}" onclick="sxd.hideDialog('createdb');"></td></tr>
		</table>
	</div>
</div>
<div class=dialog id="dia_charsets">
	<div class="header">{$SXD->LNG['cdb_header']}</div>
	<div class="content">
		<table cellspacing="5">
			<tr>
				<td valign="top">
				<fieldset>
				<legend>{$SXD->LNG['cdb_detail']}</legend>
					<table cellspacing="3">
						<tr>
							<td width="80">{$SXD->LNG['combo_charset']}</td>
							<td width="202"><div id="services_charset"></div></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['combo_collate']}</td>
							<td><div id="services_charset_col"></div></td>
						</tr>
						<tr>
							<td colspan=2 id=charset_help>Исправление кодировки нужно в случаях, когда у таблицы указана одна кодировка, а данные в другой. Например, таблица в latin1, а данные в cp1251.<br>Рекомендуется сначала сделать бэкап базы данных.Изменение кодировки нужно в случаях когда у нужно изменить кодировку таблицы и данных, например, из cp1251 в utf8.</td>
						</tr>
					</table>
				</fieldset>
				</td>
			</tr>
			<tr class="buttons"><td align="right"><input type="button" value="{$SXD->LNG['btn_create']}" onclick="sxd.addDb();"><input type="button" value="{$SXD->LNG['btn_cancel']}" onclick="sxd.hideDialog('charsets');"></td></tr>
		</table>
	</div>
</div>
<div id=sxdMenu style="display:none;z-index:9999;"></div>
<script type="text/javascript">
sxd.init();
sxd.backupUrl = '{$SXD->CFG['backup_url']}';
sxd.tbar.init('sxdToolbar', {$SXD->VAR['toolbar']}); 
{$SXD->VAR['combos']}
sxd.actions.tab_backup();
</script>
</body>
</html>
HTML;
}

function sxd_tpl_auth($error = ''){
global $SXD;
return <<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">  
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$SXD->name}</title>
<link rel="shortcut icon" href="load.php?favicon.v206.ico">
<link rel="stylesheet" type="text/css" href="load.php?sxd.v206.css">
</head>
<body>
<div class="dialog" id="dia_auth">
	<div class="header"><a href="http://sypex.net/">{$SXD->name}</a></div>
	<div class="content" id="div_1" style="line-height:50px;text-align:center;">{$SXD->LNG['js_required']}</div>
	<div class="content" id="div_2" style="display:none;">
		<form method="post">
		<table cellspacing="5">
			<tr>
				<td valign="top" colspan="3">
				<fieldset>
				<legend>{$SXD->LNG['auth']}</legend>
					<table cellspacing="3">
						<tr>
							<td width="90">{$SXD->LNG['auth_user']}</td>
							<td width="192"><input type="text" name="user" value="{$_POST['user']}" class="i202"></td>
						</tr>
						<tr>
							<td>{$SXD->LNG['my_pass']}</td>
							<td><input type="password" name="pass" value="{$_POST['pass']}" class="i202"></td>
						</tr>
						<tr>
							<td></td>
							<td><label><input type="checkbox" name="save" value="1"{$_POST['save']}> {$SXD->LNG['auth_remember']}</label></td>
						</tr>
						<tr>
							<td>Language:</td>
							<td><select type="text" name="lang" style="width:198px;" onChange="this.form.submit();">{$SXD->lng_list}</select></td>
						</tr>
					</table>
					<table cellspacing="3" id="hst" style="display:none;">
						<tr>
							<td width="90">{$SXD->LNG['my_host']}</td>
							<td width="116"><input type="text" name="host" style="width:110px;" value="{$_POST['host']}"></td>
							<td width="40" align="right">{$SXD->LNG['my_port']}</td>
							<td width="36"><input type="text" name="port" maxlength="5" style="width:30px;" value="{$_POST['port']}"></td>
						</tr>
					</table>
				</fieldset>
				</td>
			</tr>
			<tr class="buttons"><td align="left"><input type="button" value="{$SXD->LNG['btn_details']}" onclick="var s = document.getElementById('hst').style; s.display = (s.display == 'block') ? 'none' : 'block';"></td><td align="right"><input type="submit" value="{$SXD->LNG['btn_enter']}"></td></tr>
		</table>
		</form>
	</div>
	<script type="text/javascript">document.getElementById('div_1').style.display = 'none';document.getElementById('div_2').style.display = 'block';</script>
</div>
</body>
</html>
HTML;
}

?>
