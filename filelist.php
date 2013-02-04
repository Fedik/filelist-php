<?php
/**
 * Script to get list of files and file comparison
 *
 * @version		2013.02.04
 * @author  Fedik
 * @email	getthesite@gmail.com
 * @link    http://www.getsite.org.ua
 * @license	GNU/GPL http://www.gnu.org/licenses/gpl.html
 */

//base options

$path = '.'; //The path of the folder to read.
$recurse = true; //True to recursively search into sub-folders, or an integer to specify the maximum depth.
$filter = '.'; //A filter for file names. Regexp.
$exclude = array('.svn', '.git', 'CVS', '.DS_Store', '__MACOSX'); //Array with names of files which should not be shown in the result.
$excludefilter_string = '/(^\..*|.*~|\.gif$|\.jpg$|\.jpeg$|\.png$)/i'; //Regexp of files to exclude '/(^\..*|.*~|\.ini$)/'
$findfiles = true; //True to read the files, false to read the folders
$full = true; //True to return the full info about the file.
$map_file_name = 'files_map';
$date_format = 'Y-m-d H:i:s';
$pager_limit = 80;

//profiling
$time_exec = array();
$time_exec['start'] = getmicrotime();

//utilite functions

/**
 * Function to read the files/folders in a folder.
 * @return  array  Files.
 */
function getItems(
	$path, //The path of the folder to read.
	$recurse, //True to recursively search into sub-folders, or an integer to specify the maximum depth.
	$filter, //A filter for file names.
	$exclude, //Array with names of files which should not be shown in the result.
	$excludefilter_string, //Regexp of files to exclude
	$findfiles, //True to read the files, false to read the folders
	$full //True to return the full path to the file.
){
	//@set_time_limit(ini_get('max_execution_time'));

	// Is the path a folder?
	if (!is_dir($path)){
		echo '<b>No valid folder path</b>';
		return false;
	}

	$arr = array();

	// Read the source directory
	if (!($handle = @opendir($path))){
		return $arr;
	}

	while (($file = readdir($handle)) !== false){
		if ($file != '.' && $file != '..' && !in_array($file, $exclude)
			&& (empty($excludefilter_string) || !preg_match($excludefilter_string, $file))
		){
			// Compute the fullpath
			$fullpath = $path . '/' . $file;

			// Compute the isDir flag
			$isDir = is_dir($fullpath);

			if (($isDir xor $findfiles) && preg_match("/$filter/", $file)){
				// (fullpath is dir and folders are searched or fullpath is not dir and files are searched) and file matches the filter

				$stat = stat($fullpath);
				$arr[$fullpath] = array(
					'path' => $fullpath,
					'filename' => pathinfo($file, PATHINFO_FILENAME),
					'ext' => pathinfo($file, PATHINFO_EXTENSION),
					'size' => $stat['size'], //size in bytes
					'nlink' => $stat['nlink'], //number of links
					'mtime' => $stat['mtime'], //time of last modification (Unix timestamp)
					'ctime'	=> $stat['ctime'], //time of last inode change (Unix timestamp)
					'mode' => $stat['mode'], //inode protection mode, permissions , base_convert($file_info['mode'],10,8);
					'hash' => '',
					'state' => '',//1.same;2.changed;3.new;4.removed
				);

				if ($full){
					$arr[$fullpath]['hash'] = md5_file($fullpath);
					//$arr[$fullpath]['hash'] = !$isDir ? sha1_file($fullpath) : '';
				}
			}
			if ($isDir && $recurse){
				// Search recursively
				if (is_int($recurse)){
					// Until depth 0 is reached
					$arr = array_merge($arr, getItems($fullpath,  $recurse - 1, $filter,  $exclude, $excludefilter_string, $findfiles, $full));
				}else{
					$arr = array_merge($arr, getItems($fullpath, $recurse, $filter, $exclude, $excludefilter_string, $findfiles, $full));
				}
			}
		}
	}
	closedir($handle);
	return $arr;
}
/**
 * Rename file
 * @param $file
 */
function renameOldFile($file_name, $path){
	$file = $file_name . '.map';
	if (!is_file($file)) {
		return;
	}
	$new_name = $file_name .'_' . date('Y-m-d_H.i.s', filectime($file)).'-'. base64_encode($path) .'.map';
	return rename($file, $new_name);
}
/**
 * Function for sorting files/folders by on of they properties.
 * @return  array  sorted Files.
 */
function itemsSort(
	$files, //files Array
	$key, // key for sorting
	$dir = SORT_DESC // sorting direction SORT_DESC, SORT_ASC
) {
	$sort_arr = array();
	foreach ($files as $k => $f) {
		$sort_arr[$k] = $f[$key];
	}

	array_multisort($sort_arr, $dir, $files);

	return $files;
}
/**
 * format bytes
 * @return string
 */
function formatBytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');

	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);

	// Uncomment one of the following alternatives
	$bytes /= pow(1024, $pow);
	//$bytes /= (1 << (10 * $pow));

	return round($bytes, $precision) . ' ' . $units[$pow];
}
/**
 * State format
 * 1 = same, 2 = changed, 3 = new, 4 = removed
 * @return HTML string
 */
function stateFormat($state) {
	$title = 'unknown';
	switch ($state){
		case 1:
			$title = 'same';
			break;
		case 2:
			$title = 'changed';
			break;
		case 3:
			$title = 'new';
			break;
		case 4:
			$title = 'removed';
			break;
	}

	return '<span class="'.$title.'">'.$title.'</span>';
}
/**
 * build query
 * @return new query array or query string
 */
function buildUrlQuery($query, $build_query = false) {
	$old_query = !empty($_GET) ? $_GET : array();
	foreach ($query as $k => $v){
		$old_query[$k] = $v;
	}

	return $build_query ? http_build_query($old_query) : $old_query;
}
/**
 * Get the current time.
 * @return  float The current time
 */
function getmicrotime(){
	list ($usec, $sec) = explode(' ', microtime());
	return ((float) $usec + (float) $sec);
}

// init variables
$files = array();
$map_file = $map_file_name . '.map';
$map_file_current = empty($_GET['filelist']) ? $map_file : $_GET['filelist'].'.map';
$scan = isset($_GET['scan']);
$hashcompare = empty($_GET['hashcompare'])? false : $_GET['hashcompare'] .'.map';
$sort_by = empty($_GET['sort']) ? 'path' :  $_GET['sort'];
$sort_dir = empty($_GET['dir']) || $_GET['dir'] != 'asc' ? SORT_DESC : SORT_ASC;
$page = empty($_GET['page']) || $_GET['page'] == 1 ? 0 : (int)  $_GET['page'];


$table = '';
$stored_maps = '';
$pager = '';
$table_head = array(
	'path' => 'Path',
	'filename' => 'File Name',
	'ext' => 'Ext',
	'size' => 'Size',
	'nlink' => 'Links',
	'mtime' => 'Last modification',
	'ctime' => 'Last inode change',
	'mode' => 'Permissions',
	'state' => 'File state'
);

//prepare base URL query
$_GET['filelist'] = empty($_GET['filelist']) ? $map_file_name : $_GET['filelist'];
if (isset($_GET['scan'])) {
	unset($_GET['scan']);
}
if ($scan && isset($_GET['hashcompare'])) {
	unset($_GET['hashcompare']);
	$hashcompare = false;
}

//run
if(is_file($map_file_current) && !$scan && $stored_data = file_get_contents($map_file)){
	$files = unserialize($stored_data);
	$time_exec['Open Stored'] = getmicrotime();
} elseif (!is_file($map_file) || $scan) {
	$files = getItems($path, $recurse, $filter, $exclude, $excludefilter_string, $findfiles, $full);
	//keeep old file
	renameOldFile($map_file_name, $path);
	file_put_contents($map_file, serialize($files));
	$time_exec['Scan Files'] = getmicrotime();
}

//'state' => 1.same, 2.changed, 3.new, 4.removed
if ($hashcompare && $stored_map = file_get_contents($hashcompare)) {
	$files_old = unserialize($stored_map);

	//find changed and new
	foreach ($files as $n => &$f) {
		if (empty($files_old[$n])) {
			$f['state'] = 3;
			continue;
		}
		if ($f['hash'] != $files_old[$n]['hash']) {
			$f['state'] = 2;
			continue;
		}
		$f['state'] = 1;
	}
	unset($f);
	//find removed
	foreach ($files_old as $n => $f) {
		if (empty($files[$n])) {
			$files[$n] = $f;
			$files[$n]['state'] = 4;
		}
	}
	$time_exec['Comparison'] = getmicrotime();
}

$files = itemsSort($files, $sort_by, $sort_dir);
$time_exec['Sorting'] = getmicrotime();

$files_total = count($files);

//cut by pager
if ($pager_limit && $files_total > $pager_limit) {
	$pager_start = $pager_limit * $page;
	$files =  array_slice($files, $pager_start, $pager_limit);

	//build pager
	$pages_total = round($files_total/$pager_limit, 0, PHP_ROUND_HALF_DOWN);
	$pager .= '<a href="?'.buildUrlQuery(array('page' => 1), true).'"><< First</a>&nbsp;...&nbsp;';
	//5 prev and 5 next
	for($i = max(1, $page - 5); $i <= min($page + 5, $pages_total); $i++){
		$pager .= '<a href="?'.buildUrlQuery(array('page' => $i), true).'">'.$i.'</a>';
	}
	$pager .= '&nbsp;...&nbsp;<a href="?'.buildUrlQuery(array('page' => $pages_total), true).'">Last >></a>';
}

//render table
if($files_total) {
	$table = '<table>';
	//head
	$table .= '<thead><tr>';
	foreach ($table_head as $k => $h) {
		$active = ($k == $sort_by);
		$table .= '<th class="'.$k.'">'
				. $h
				. '&nbsp;&nbsp;'
				. '<a href="?'.buildUrlQuery(array('sort' => $k, 'dir' => 'desc'), true).'">'.(($active && $sort_dir == SORT_DESC) ? '&#11014;' : '&uarr;').'</a>'//desc
				. '&nbsp;'
				. '<a href="?'.buildUrlQuery(array('sort' => $k, 'dir' => 'asc'), true).'">'.(($active && $sort_dir == SORT_ASC) ? '&#11015;' : '&darr;') .'</a>'//asc
				.'</th>';
	}
	$table .= '</tr></thead>';

	//rows
	$table .= '<tbody>';
	$columns = array_keys($table_head);
	foreach ($files as $file) {
		$table .= '<tr>';
		foreach ($columns as $col) {
			switch ($col) {
				case 'size':
					$table .= '<td>'.formatBytes($file[$col]).'</td>';
					break;
				case 'mtime':
				case 'ctime':
					$table .= '<td>'.date($date_format, $file[$col]).'</td>';
					break;
				case 'mode':
					$table .= '<td>'.base_convert($file[$col],10,8).'</td>';
					break;
				case 'state':
					$table .= '<td>'.stateFormat($file[$col]).'</td>';
					break;
				default:
					$table .= '<td>'.$file[$col].'</td>';;
					break;
			}

		}
		$table .= '</tr>';
	}
	$table .= '</tbody>';
	$table .= '</table>';

}

//render stored map files list for hashcompare
$maps = getItems('.', false,  '('.base64_encode($path).'|'.$map_file_name.')\.map$', array(), null, true, false);
rsort($maps);
$tmp_arr = array();
foreach ($maps as $map) {
	$tmp_arr[] = '<li>'.$map['filename']. '&nbsp;'
		.'<a href="?'.buildUrlQuery(array('sort' => 'state', 'dir' => 'desc', 'hashcompare' => $map['filename']), true).'">Compare</a>&nbsp;'
		.'<a href="?'.buildUrlQuery(array('sort' => 'path', 'dir' => 'desc', 'page' => 1, 'hashcompare' => '', 'filelist' => $map['filename']), true).'">Open</a>'
		.'</li>';
}
$stored_maps = '<ul>'.implode('', $tmp_arr).'</ul>';

$time_exec['Render'] = getmicrotime();

?>
<!doctype html>
<html lang="en-US">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Files list</title>
  <style type="text/css">
body {
	font: 12px Helvetica, sans-serif;
	background: #fff;
}
table {
	width: 100%;
	border: 1px solid grey;
	border-spacing: 0;
	border-collapse: collapse;
}
table th,table td {
	text-align: left;
	padding: 3px;
}
table th {
	border: 1px solid grey;
	background: #F2F2F2;
}
table th a {color: #000;}
table tr:nth-child(even) {
	background: #F2F2F2;
}
table tr:hover{
	background: #EAF2D3;
}
.pager a{
	padding: 3px;
	margin-right: 3px;
}
span.new,
span.changed,
span.removed {color: #bf1122;}
span.same{ color: #009159}
</style>
</head>
<body>
<div id="files-table">
<p>Files total: <?php echo $files_total;?><br />
Base path: <?php echo realpath($path);?><br />
<a href="?<?php echo buildUrlQuery(array('scan' => true, 'hashcompare' => ''), true); ?>">Scan again</a></p>
<p>map file: <b><?php echo $map_file_current; ?></b>
<?php if ($hashcompare): ?> vs <b><?php echo $hashcompare; ?></b> <?php endif; ?>
</p>
<?php echo $table; ?>
</div>
<p class="pager"><?php echo $pager; ?></p>
<p>
Previously saved the map files:<br />
<?php echo $stored_maps; ?>
</p>
<p>
<?php
//profiling info
foreach ($time_exec as $k => $t) {
	if ($k == 'start'){
		$prev = $t;
		continue;
	}
	echo 'Time <b>'. $k .'</b>: ' . round(($t - $prev), 6) .'<br />';
	$prev = $t;
}
echo 'Memory used: '. formatBytes(memory_get_usage());
?>
</p>
</body>
</html>