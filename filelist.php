<?php
/**
 * Script to get list of files and file comparison
 *
 * @version		2014.04.28
 * @author   Fedik <getthesite at gmail dot com>
 * @link    http://www.getsite.org.ua
 * @license	GNU/GPL http://www.gnu.org/licenses/gpl.html
 */

// Enable error reaporting, in case fault
error_reporting(E_ALL);
ini_set('display_errors', 'On');

//base options

//The path of the folder to read.
$path = '.';
//True to recursively search into sub-folders, or an integer to specify the maximum depth.
$recurse = true;
//A Regexp filter for allowed file names. '/./' - any
$filter_allow = '/./';
//Regexp of files to exclude, have biger priority than $filter_allow
$filter_exclude =  '/(\.gif$|\.jpg$|\.jpeg$|\.png$)/i';
//Regexp of path for exclude, have biger priority than $filter_exclude
$filter_exclude_path = '/(\/\.svn|\/\.git|\/\CVS|\/\__MACOSX)/';
//True to read the files, false to read the folders only
$findfiles = true;
//Scip folders from result
$scipfolders = true;
// Whether try find the removed files when map comparasion
$findremoved = true;
// Method for count HASH of each file, for compare in future
// Important for make $findremoved work, but can cause a error on big files
// Possible values: 'md5', 'sha256', 'haval160,4', 'sha1', 'crc32' ...
// see http://www.php.net/manual/en/function.hash-algos.php
// false - for disable,
$counthash = 'md5';
// base name for a map file
$map_file_name = 'files_map';
// The date formating in the table
$date_format = 'Y-m-d H:i:s';
// How much show the files on the page
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
	$recurse = true, //True to recursively search into sub-folders, or an integer to specify the maximum depth.
	$filter_allow = '/./', //A filter for file names.
	$filter_exclude = '', //Regexp of files to exclude
	$filter_exclude_path = '', //Regexp of path to exclude
	$findfiles = true, //True to read the files, false to read the folders
	$scipfolders = true, //Scip folders from result
	$counthash = false // Method used for count file hash, false - for disable
){
	//@set_time_limit(ini_get('max_execution_time'));

	// Is the path a folder?
	if (!is_dir($path)){
		echo '<b>No valid folder path</b>';
		return false;
	}

	// use RecursiveDirectoryIterator where it possible, php 5.2.x partiall suport
	if (class_exists('RecursiveDirectoryIterator')) {//version_compare(PHP_VERSION, '5.3.0', 'ge')
		return _getItemsDirectoryIterator($path, $recurse, $filter_allow,
				$filter_exclude, $filter_exclude_path, $findfiles, $scipfolders, $counthash);
	}

	$arr = array();

	// Read the source directory
	if (!($handle = @opendir($path))){
		return $arr;
	}

	while (($file = readdir($handle)) !== false){
		// Compute the fullpath
		$fullpath = $path . '/' . $file;

		// Compute the isDir flag
		$isDir = is_dir($fullpath);

		if ($file != '.' && $file != '..'
			&& (empty($filter_exclude_path) || !preg_match($filter_exclude_path, $fullpath))
			&& (empty($filter_exclude) || !preg_match($filter_exclude, $file))
		){

			if ((($isDir && !$scipfolders) || ($findfiles && !$isDir))
				&& (empty($filter_allow) || preg_match($filter_allow, $file))
			){

				$about_file = array(
					'path' => '',
					'filename' => '',
					'ext' => '',
					'size' => 0, //size in bytes
					'type' => '', //file, folder, link
					'mtime' => '', //time of last modification (Unix timestamp)
					'ctime'	=> '', //time of last inode change (Unix timestamp)
					'mode' => '', //inode protection mode, permissions , base_convert($file_info['mode'],10,8);
					'hash' => '',
					'state' => '',//1.same;2.changed;3.new;4.removed
				);

				$about_file['path'] = $fullpath;
				$about_file['filename'] = pathinfo($file, PATHINFO_FILENAME);
				$about_file['ext'] = pathinfo($file, PATHINFO_EXTENSION);
				$about_file['type'] = filetype($fullpath);

				if($about_file['type'] != 'link'){
					$stat = stat($fullpath);

					$about_file['size'] = $stat['size'];
					$about_file['mtime'] = $stat['mtime'];
					$about_file['ctime'] = $stat['ctime'];
					$about_file['mode'] = $stat['mode'];
				}

				if (is_readable($fullpath)){
					$about_file['hash'] = $counthash ? hash_file($counthash, $fullpath) : '';
				} else {
					echo 'File not readable: '.$fullpath.'<br />';
				}

				$arr[$fullpath] = $about_file;
			}

			// Search recursively
			if ($isDir && $recurse){
				if (is_int($recurse)){
					// Until depth 0 is reached
					$arr = array_merge($arr, getItems($fullpath,  $recurse - 1, $filter_allow,
							$filter_exclude, $filter_exclude_path, $findfiles, $scipfolders, $counthash));
				}else{
					$arr = array_merge($arr, getItems($fullpath, $recurse, $filter_allow,
							$filter_exclude, $filter_exclude_path, $findfiles, $scipfolders, $counthash));
				}
			}
		}
	}
	closedir($handle);
	return $arr;
}
function _getItemsDirectoryIterator($path, $recurse, $filter_allow,
		$filter_exclude, $filter_exclude_path, $findfiles, $scipfolders, $counthash) {
	$arr = array();

	$dir_it = new RecursiveDirectoryIterator($path);

	$dir_it = new RecursiveIteratorIterator($dir_it,
			RecursiveIteratorIterator::SELF_FIRST , RecursiveIteratorIterator::CATCH_GET_CHILD);

	//Apply $filter_allow
	//RegexIterator not always available .. php > 5.2
	//if($filter_allow) {
		//$dir_it = new RegexIterator($dir_it, $filter_allow, RegexIterator::MATCH);
	//}

	if(!$recurse) {
		$dir_it->setMaxDepth(0);
	}

	foreach ($dir_it as $fullpath => $fileinfo) {
		$file = pathinfo($fullpath);

		if ($file['basename'] == '.' || $file['basename'] == '..'
			|| (!$findfiles && $fileinfo->isFile())
			|| ($scipfolders && $fileinfo->isDir())
			|| (!empty($filter_allow) && !preg_match($filter_allow, $file['basename']))
			|| (!empty($filter_exclude_path) && preg_match($filter_exclude_path, $fullpath))
			|| (!empty($filter_exclude) && preg_match($filter_exclude, $file['basename']))
		) {
			continue;
		}

		$about_file = array(
			'path' => '',
			'filename' => '',
			'ext' => '',
			'size' => 0, //size in bytes
			'type' => '', //file, folder, link
			'mtime' => '', //time of last modification (Unix timestamp)
			'ctime'	=> '', //time of last inode change (Unix timestamp)
			'mode' => '', //inode protection mode, permissions , base_convert($file_info['mode'],10,8);
			'hash' => '',
			'state' => '',//1.same;2.changed;3.new;4.removed
		);

		$about_file['path'] = $fullpath;
		$about_file['filename'] = $file['filename'];
		$about_file['ext'] = isset($file['extension']) ? $file['extension'] : '';
		$about_file['type'] = $fileinfo->getType();

		if($about_file['type'] != 'link'){
			$about_file['size'] = $fileinfo->getSize();
			$about_file['mtime'] = $fileinfo->getMTime();
			$about_file['ctime'] = $fileinfo->getCTime();
			$about_file['mode'] = $fileinfo->getPerms();
		}

		if ($fileinfo->isReadable()){
			$about_file['hash'] = $counthash ? hash_file($counthash, $fullpath) : '';
		} else {
			echo 'File not readable: '.$fullpath.'<br />';
		}

		$arr[$fullpath] = $about_file;
	}

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
$sort_dir = empty($_GET['dir']) || $_GET['dir'] == 'asc' ? SORT_ASC : SORT_DESC ;
$page = empty($_GET['page']) || $_GET['page'] == 1 ? 0 : (int)  $_GET['page'];


$table = '';
$stored_maps = '';
$pager = '';
$table_head = array(
	'path' => 'Path',
	'filename' => 'File Name',
	'ext' => 'Ext',
	'size' => 'Size',
	'type' => 'Type',
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
} elseif ($scan) {
	$files = getItems($path, $recurse, $filter_allow, $filter_exclude,
			$filter_exclude_path, $findfiles, $scipfolders, $counthash);
	//keeep old file
	renameOldFile($map_file_name, $path);
	file_put_contents($map_file, serialize($files));
	$time_exec['Scan Files'] = getmicrotime();
}

//'state' => 1.same, 2.changed, 3.new, 4.removed
if (!empty($files) && $hashcompare && $stored_map = file_get_contents($hashcompare)) {
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
	if ($findremoved) {
		foreach ($files_old as $n => $f) {
			if (empty($files[$n])) {
				$files[$n] = $f;
				$files[$n]['state'] = 4;
			}
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

if($files_total) {
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
					$table .= '<td>'. ($file[$col] ? date($date_format, $file[$col]) : '') .'</td>';
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
} else {
	$table .= '<tbody>
	<tr>
		<td colspan="'.count($table_head).'">
			<p>No filemap found. Please click <a href="?'. buildUrlQuery(array('scan' => true, 'hashcompare' => ''), true).'" title="click for scan">"Scan"</a></p>
		</td>
	</tr>
</tbody>';
}
$table .= '</table>';

//render stored map files list for hashcompare
$maps = getItems('.', false,  '/('.base64_encode($path).'|'.$map_file_name.')\.map$/', null, null, true, true);
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
<a href="?<?php echo buildUrlQuery(array('scan' => true, 'hashcompare' => ''), true); ?>" title="click for scan again">Scan again</a></p>
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
