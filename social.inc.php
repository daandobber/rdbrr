<?php

/*
 *	social.inc.php
 *	File-based account/profile layer for a social Hotglue fork.
 */

@require_once('config.inc.php');


function social_enabled()
{
	return defined('SOCIAL_ACCOUNTS') && SOCIAL_ACCOUNTS;
}


function social_session_start()
{
	if (!social_enabled()) {
		return;
	}
	if (session_id() == '') {
		@session_start();
	}
}


function social_users_file()
{
	return CONTENT_DIR.'/users.json';
}


function social_read_users()
{
	$ret = array('users'=>array());
	$f = social_users_file();
	if (!is_file($f)) {
		return $ret;
	}
	$s = @file_get_contents($f);
	if ($s === false || trim($s) == '') {
		return $ret;
	}
	$data = @json_decode($s, true);
	if (!is_array($data) || !isset($data['users']) || !is_array($data['users'])) {
		return $ret;
	}
	return $data;
}


function social_write_users($data)
{
	if (!is_dir(CONTENT_DIR)) {
		return false;
	}
	$options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
	$s = json_encode($data, $options);
	if ($s === false) {
		return false;
	}
	$m = umask(0111);
	$res = @file_put_contents(social_users_file(), $s."\n", LOCK_EX);
	umask($m);
	return $res !== false;
}


function social_normalize_username($username)
{
	return strtolower(trim($username));
}


function social_valid_username($username)
{
	return preg_match('/^[a-z0-9_]{3,32}$/', $username) === 1;
}


function social_reserved_username($username)
{
	$username = social_normalize_username($username);
	return in_array($username, array(
		'account',
		'admin',
		'api',
		'edit',
		'login',
		'logout',
		'me',
		'feed',
		'follow',
		'profiles',
		'register',
		'timeline'
	));
}


function social_admin_usernames()
{
	if (!defined('SOCIAL_ADMIN_USERS') || trim(SOCIAL_ADMIN_USERS) == '') {
		return array();
	}
	$ret = array();
	foreach (expl(' ', strtolower(SOCIAL_ADMIN_USERS)) as $username) {
		$username = social_normalize_username($username);
		if (social_valid_username($username)) {
			$ret[] = $username;
		}
	}
	return $ret;
}


function social_data_has_admin($data)
{
	if (!isset($data['users']) || !is_array($data['users'])) {
		return false;
	}
	foreach ($data['users'] as $username=>$user) {
		if (isset($user['role']) && $user['role'] == 'admin') {
			return true;
		}
		if (in_array($username, social_admin_usernames())) {
			return true;
		}
	}
	return false;
}


function social_bootstrap_admin_username($data)
{
	if (social_data_has_admin($data) || !isset($data['users']) || !is_array($data['users'])) {
		return false;
	}
	foreach ($data['users'] as $username=>$user) {
		return $username;
	}
	return false;
}


function social_user_role($user, $data = false)
{
	if (!is_array($user)) {
		return 'user';
	}
	if (isset($user['role']) && $user['role'] == 'admin') {
		return 'admin';
	}
	if (isset($user['username']) && in_array($user['username'], social_admin_usernames())) {
		return 'admin';
	}
	if ($data !== false && isset($user['username']) && social_bootstrap_admin_username($data) == $user['username']) {
		return 'admin';
	}
	return 'user';
}


function social_user_is_admin($user, $data = false)
{
	return social_user_role($user, $data) == 'admin';
}


function social_is_admin()
{
	return social_user_is_admin(social_current_user());
}


function social_user_status($user)
{
	if (isset($user['status']) && $user['status'] == 'disabled') {
		return 'disabled';
	}
	return 'active';
}


function social_profile_page($username)
{
	return 'u_'.$username;
}


function social_route_profile_page($route)
{
	if (!is_string($route) || $route == '') {
		return false;
	}
	if (substr($route, 0, 1) == '@') {
		$username = social_normalize_username(substr($route, 1));
		return social_valid_username($username) ? social_profile_page($username) : false;
	}
	$username = social_normalize_username($route);
	if (!social_valid_username($username) || social_reserved_username($username)) {
		return false;
	}
	$data = social_read_users();
	if (isset($data['users'][$username]) || page_exists(social_profile_page($username).'.head')) {
		return social_profile_page($username);
	}
	return false;
}


function social_route_profile_args($route)
{
	if (is_array($route)) {
		if (isset($route[0]) && $route[0] == 'u' && isset($route[1])) {
			$username = social_normalize_username($route[1]);
			$action = isset($route[2]) ? $route[2] : '';
		} else {
			$username = isset($route[0]) ? social_normalize_username($route[0]) : '';
			$action = isset($route[1]) ? $route[1] : '';
		}
		if (!social_valid_username($username) || ($action != '' && $action != 'edit')) {
			return false;
		}
		$page = social_route_profile_page($username);
		if ($page === false) {
			return false;
		}
		return array($page, $action);
	}
	$page = social_route_profile_page($route);
	return $page === false ? false : array($page, '');
}


function social_username_from_profile_page($page)
{
	$a = expl('.', $page);
	$pagename = $a[0];
	if (substr($pagename, 0, 2) != 'u_') {
		return false;
	}
	$username = substr($pagename, 2);
	return social_valid_username($username) ? $username : false;
}


function social_current_user()
{
	social_session_start();
	if (!isset($_SESSION['social_user'])) {
		return false;
	}
	$username = social_normalize_username($_SESSION['social_user']);
	$data = social_read_users();
	if (!isset($data['users'][$username])) {
		unset($_SESSION['social_user']);
		return false;
	}
	if (social_user_status($data['users'][$username]) == 'disabled') {
		unset($_SESSION['social_user']);
		return false;
	}
	$user = $data['users'][$username];
	if (social_bootstrap_admin_username($data) == $username && (!isset($user['role']) || $user['role'] != 'admin')) {
		$data['users'][$username]['role'] = 'admin';
		social_write_users($data);
		$user = $data['users'][$username];
	}
	if (social_user_is_admin($user, $data)) {
		$user['role'] = 'admin';
	}
	return $user;
}


function social_current_username()
{
	$user = social_current_user();
	return $user ? $user['username'] : false;
}


function social_user_by_username($username)
{
	$username = social_normalize_username($username);
	$data = social_read_users();
	return isset($data['users'][$username]) ? $data['users'][$username] : false;
}


function social_display_name($username)
{
	$user = social_user_by_username($username);
	if ($user && isset($user['display_name']) && trim($user['display_name']) != '') {
		return $user['display_name'];
	}
	return $username;
}


function social_avatar_color($username)
{
	$colors = array('#3f6fb5', '#8f4fa8', '#b34c5c', '#4f7f63', '#a0662f', '#6e5fa8', '#317f8f', '#8b6f2d');
	$idx = abs(crc32($username)) % count($colors);
	return $colors[$idx];
}


function social_avatar_src($url)
{
	if (substr($url, 0, 8) == 'content/') {
		return base_url().$url;
	}
	return $url;
}


function social_avatar_html($username, $class = '')
{
	$username = social_normalize_username($username);
	$user = social_user_by_username($username);
	$name = social_display_name($username);
	$label = $name != '' ? $name : $username;
	$class = trim('social-avatar '.$class);
	if ($user && isset($user['avatar_url']) && trim($user['avatar_url']) != '') {
		return '<a class="'.htmlspecialchars($class, ENT_COMPAT, 'UTF-8').'" href="'.htmlspecialchars(social_profile_url($username), ENT_COMPAT, 'UTF-8').'" title="'.htmlspecialchars($label, ENT_COMPAT, 'UTF-8').'"><img src="'.htmlspecialchars(social_avatar_src($user['avatar_url']), ENT_COMPAT, 'UTF-8').'" alt="'.htmlspecialchars($label, ENT_COMPAT, 'UTF-8').'"></a>';
	}
	$initial = $label != '' ? strtoupper(substr($label, 0, 1)) : '?';
	return '<a class="'.htmlspecialchars($class, ENT_COMPAT, 'UTF-8').'" href="'.htmlspecialchars(social_profile_url($username), ENT_COMPAT, 'UTF-8').'" title="'.htmlspecialchars($label, ENT_COMPAT, 'UTF-8').'" style="background-color: '.htmlspecialchars(social_avatar_color($username), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars($initial, ENT_NOQUOTES, 'UTF-8').'</a>';
}


function social_clean_avatar_url($url)
{
	$url = trim($url);
	if ($url == '') {
		return '';
	}
	if (500 < strlen($url)) {
		return false;
	}
	if (preg_match('/^https?:\/\//i', $url) || substr($url, 0, 1) == '/' || substr($url, 0, 8) == 'content/') {
		return $url;
	}
	return false;
}


function social_image_from_file($file, $mime)
{
	if ($mime == 'image/jpeg') {
		return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false;
	}
	if ($mime == 'image/png') {
		return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false;
	}
	if ($mime == 'image/gif') {
		return function_exists('imagecreatefromgif') ? @imagecreatefromgif($file) : false;
	}
	if ($mime == 'image/webp') {
		return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
	}
	return false;
}


function social_avatar_crop_from_post($width, $height)
{
	$size = isset($_POST['avatar_crop_size']) ? intval($_POST['avatar_crop_size']) : 0;
	$x = isset($_POST['avatar_crop_x']) ? intval($_POST['avatar_crop_x']) : 0;
	$y = isset($_POST['avatar_crop_y']) ? intval($_POST['avatar_crop_y']) : 0;
	if ($size <= 0) {
		$size = min($width, $height);
		$x = intval(($width-$size)/2);
		$y = intval(($height-$size)/2);
	}
	$size = min($size, $width, $height);
	$x = max(0, min($x, $width-$size));
	$y = max(0, min($y, $height-$size));
	return array('x'=>$x, 'y'=>$y, 'size'=>$size);
}


function social_account_dir_size($dir)
{
	$total = 0;
	if (!is_dir($dir)) {
		return 0;
	}
	$items = @scandir($dir);
	if ($items === false) {
		return 0;
	}
	foreach ($items as $item) {
		if ($item == '.' || $item == '..') {
			continue;
		}
		$path = $dir.'/'.$item;
		if (is_dir($path) && !is_link($path)) {
			$total += social_account_dir_size($path);
		} elseif (is_file($path)) {
			$total += filesize($path);
		}
	}
	return $total;
}


function social_account_avatar_total($username)
{
	$total = 0;
	$dir = CONTENT_DIR.'/profile_avatars';
	if (!is_dir($dir)) {
		return 0;
	}
	$prefix = social_normalize_username($username).'-';
	$items = @scandir($dir);
	if ($items === false) {
		return 0;
	}
	foreach ($items as $item) {
		if (substr($item, 0, strlen($prefix)) == $prefix && is_file($dir.'/'.$item)) {
			$total += filesize($dir.'/'.$item);
		}
	}
	return $total;
}


function social_account_upload_used_bytes($username)
{
	return social_account_dir_size(CONTENT_DIR.'/'.social_profile_page($username).'/shared')+social_account_avatar_total($username);
}


function social_format_bytes($bytes)
{
	if ($bytes >= 1024*1024) {
		return round($bytes/1024/1024, 2).'MB';
	}
	if ($bytes >= 1024) {
		return round($bytes/1024, 1).'KB';
	}
	return intval($bytes).'B';
}


function social_delete_owned_avatar($username, $avatar_url)
{
	$prefix = 'content/profile_avatars/'.social_normalize_username($username).'-';
	if (substr($avatar_url, 0, strlen($prefix)) != $prefix) {
		return;
	}
	$path = CONTENT_DIR.'/profile_avatars/'.basename($avatar_url);
	if (is_file($path)) {
		@unlink($path);
	}
}


function social_delete_avatar_file($username, $file, &$error)
{
	$file = basename($file);
	$prefix = social_normalize_username($username).'-';
	if (substr($file, 0, strlen($prefix)) != $prefix) {
		$error = 'Je kunt alleen je eigen avatar verwijderen.';
		return false;
	}
	$path = CONTENT_DIR.'/profile_avatars/'.$file;
	if (!is_file($path)) {
		$error = 'Avatarbestand bestaat niet.';
		return false;
	}
	$data = social_read_users();
	if (isset($data['users'][$username]['avatar_url']) && $data['users'][$username]['avatar_url'] == 'content/profile_avatars/'.$file) {
		$data['users'][$username]['avatar_url'] = '';
		if (!social_write_users($data)) {
			$error = 'Kon account niet opslaan.';
			return false;
		}
	}
	if (!@unlink($path)) {
		$error = 'Kon avatar niet verwijderen.';
		return false;
	}
	return true;
}


function social_shared_upload_references($username, $file)
{
	$ret = array();
	$page = social_profile_page($username).'.head';
	$dir = CONTENT_DIR.'/'.str_replace('.', '/', $page);
	if (!is_dir($dir)) {
		return $ret;
	}
	load_modules('glue');
	$items = @scandir($dir);
	if ($items === false) {
		return $ret;
	}
	foreach ($items as $item) {
		if ($item == '.' || $item == '..') {
			continue;
		}
		$obj = load_object(array('name'=>$page.'.'.$item));
		if ($obj['#error']) {
			continue;
		}
		$obj = $obj['#data'];
		if (!empty($obj['image-file']) && $obj['image-file'] == $file) {
			$ret[] = array('kind'=>'object', 'name'=>$obj['name'], 'label'=>'Afbeelding');
		}
		if (!empty($obj['image-resized-file']) && $obj['image-resized-file'] == $file) {
			$ret[] = array('kind'=>'object', 'name'=>$obj['name'], 'label'=>'Afbeelding');
		}
		if (!empty($obj['page-background-file']) && $obj['page-background-file'] == $file) {
			$ret[] = array('kind'=>'background', 'name'=>$obj['name'], 'label'=>'Achtergrond');
		}
	}
	return $ret;
}


function social_delete_shared_upload($username, $file, &$error)
{
	$file = basename($file);
	$pagename = social_profile_page($username);
	$path = CONTENT_DIR.'/'.$pagename.'/shared/'.$file;
	if (!is_file($path)) {
		$error = 'Uploadbestand bestaat niet.';
		return false;
	}
	load_modules('glue');
	$refs = social_shared_upload_references($username, $file);
	foreach ($refs as $ref) {
		if ($ref['kind'] == 'object') {
			delete_object(array('name'=>$ref['name']));
		} elseif ($ref['kind'] == 'background') {
			object_remove_attr(array('name'=>$ref['name'], 'attr'=>array('page-background-file', 'page-background-mime', 'page-background-size', 'page-background-repeat', 'page-background-image-position')));
		}
	}
	if (is_file($path) && !@unlink($path)) {
		$error = 'Kon uploadbestand niet verwijderen.';
		return false;
	}
	drop_cache('page', $pagename.'.head');
	return true;
}


function social_delete_upload_from_account($username, &$error)
{
	$kind = isset($_POST['upload_kind']) ? $_POST['upload_kind'] : '';
	$file = isset($_POST['upload_file']) ? $_POST['upload_file'] : '';
	if ($file == '' || basename($file) != $file) {
		$error = 'Ongeldig uploadbestand.';
		return false;
	}
	if ($kind == 'avatar') {
		return social_delete_avatar_file($username, $file, $error);
	}
	if ($kind == 'shared') {
		return social_delete_shared_upload($username, $file, $error);
	}
	$error = 'Onbekende uploadactie.';
	return false;
}


function social_account_upload_items($username)
{
	$ret = array();
	$avatar_dir = CONTENT_DIR.'/profile_avatars';
	$prefix = social_normalize_username($username).'-';
	if (is_dir($avatar_dir) && ($items = @scandir($avatar_dir)) !== false) {
		foreach ($items as $item) {
			$path = $avatar_dir.'/'.$item;
			if (substr($item, 0, strlen($prefix)) == $prefix && is_file($path)) {
				$ret[] = array('kind'=>'avatar', 'file'=>$item, 'label'=>'Avatar', 'size'=>filesize($path), 'url'=>'content/profile_avatars/'.$item);
			}
		}
	}
	$shared_dir = CONTENT_DIR.'/'.social_profile_page($username).'/shared';
	if (is_dir($shared_dir) && ($items = @scandir($shared_dir)) !== false) {
		foreach ($items as $item) {
			$path = $shared_dir.'/'.$item;
			if ($item == '.' || $item == '..' || !is_file($path)) {
				continue;
			}
			$refs = social_shared_upload_references($username, $item);
			$label = count($refs) ? $refs[0]['label'] : 'Los bestand';
			$url = count($refs) ? '?'.$refs[0]['name'] : '';
			$ret[] = array('kind'=>'shared', 'file'=>$item, 'label'=>$label, 'size'=>filesize($path), 'url'=>$url);
		}
	}
	usort($ret, function($a, $b) {
		if ($a['kind'] == $b['kind']) {
			return strcmp($a['file'], $b['file']);
		}
		return strcmp($a['kind'], $b['kind']);
	});
	return $ret;
}


function social_render_upload_manager($username)
{
	$quota = defined('SOCIAL_USER_UPLOAD_QUOTA') ? intval(SOCIAL_USER_UPLOAD_QUOTA) : 5*1024*1024;
	$used = social_account_upload_used_bytes($username);
	$ret = tab(5).'<div class="social-upload-manager">'.nl();
	$ret .= tab(6).'<h2>Uploadruimte</h2>'.nl();
	$ret .= tab(6).'<p>'.htmlspecialchars(social_format_bytes($used), ENT_NOQUOTES, 'UTF-8').' gebruikt van '.htmlspecialchars(social_format_bytes($quota), ENT_NOQUOTES, 'UTF-8').'</p>'.nl();
	$ret .= tab(6).'<div class="social-upload-meter"><span style="width: '.htmlspecialchars($quota > 0 ? min(100, round(($used/$quota)*100)) : 0, ENT_COMPAT, 'UTF-8').'%"></span></div>'.nl();
	$items = social_account_upload_items($username);
	if (!count($items)) {
		$ret .= tab(6).'<p>Je hebt nog geen uploads.</p>'.nl();
	} else {
		$ret .= tab(6).'<div class="social-upload-list">'.nl();
		foreach ($items as $item) {
			$ret .= tab(7).'<div class="social-upload-row">'.nl();
			if ($item['url'] != '') {
				$ret .= tab(8).'<img src="'.htmlspecialchars(social_avatar_src($item['url']), ENT_COMPAT, 'UTF-8').'" alt="">'.nl();
			} else {
				$ret .= tab(8).'<span class="social-upload-thumb-empty"></span>'.nl();
			}
			$ret .= tab(8).'<div><strong>'.htmlspecialchars($item['label'], ENT_NOQUOTES, 'UTF-8').'</strong><br><span>'.htmlspecialchars($item['file'], ENT_NOQUOTES, 'UTF-8').' - '.htmlspecialchars(social_format_bytes($item['size']), ENT_NOQUOTES, 'UTF-8').'</span></div>'.nl();
			$ret .= tab(8).'<form method="post" action="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?account">'.nl();
			$ret .= tab(9).'<input type="hidden" name="account_action" value="delete_upload">'.nl();
			$ret .= tab(9).'<input type="hidden" name="upload_kind" value="'.htmlspecialchars($item['kind'], ENT_COMPAT, 'UTF-8').'">'.nl();
			$ret .= tab(9).'<input type="hidden" name="upload_file" value="'.htmlspecialchars($item['file'], ENT_COMPAT, 'UTF-8').'">'.nl();
			$ret .= tab(9).'<input type="submit" value="Verwijder">'.nl();
			$ret .= tab(8).'</form>'.nl();
			$ret .= tab(7).'</div>'.nl();
		}
		$ret .= tab(6).'</div>'.nl();
	}
	$ret .= tab(5).'</div>'.nl();
	return $ret;
}


function social_upload_avatar($username, &$error)
{
	if (!isset($_FILES['avatar_file']) || !is_array($_FILES['avatar_file'])) {
		return '';
	}
	$file = $_FILES['avatar_file'];
	if (!isset($file['error']) || $file['error'] == UPLOAD_ERR_NO_FILE) {
		return '';
	}
	if ($file['error'] != UPLOAD_ERR_OK) {
		$error = 'Uploaden van profielfoto is mislukt.';
		return false;
	}
	if (!isset($file['size']) || 5*1024*1024 < $file['size']) {
		$error = 'Profielfoto mag maximaal 5MB zijn.';
		return false;
	}
	$quota = defined('SOCIAL_USER_UPLOAD_QUOTA') ? intval(SOCIAL_USER_UPLOAD_QUOTA) : 5*1024*1024;
	if (0 < $quota && social_account_upload_used_bytes($username)+intval($file['size']) > $quota) {
		$error = 'Je uploadruimte is vol. Maximaal '.round($quota/1024/1024, 1).'MB per gebruiker.';
		return false;
	}
	$info = @getimagesize($file['tmp_name']);
	if ($info === false || empty($info['mime'])) {
		$error = 'Profielfoto moet een afbeelding zijn.';
		return false;
	}
	$exts = array(
		'image/jpeg'=>'jpg',
		'image/png'=>'png',
		'image/gif'=>'gif',
		'image/webp'=>'webp'
	);
	if (!isset($exts[$info['mime']])) {
		$error = 'Gebruik jpg, png, gif of webp als profielfoto.';
		return false;
	}
	if (!function_exists('imagecreatetruecolor')) {
		$error = 'Server mist GD om avatars bij te snijden.';
		return false;
	}
	$dir = CONTENT_DIR.'/profile_avatars';
	if (!is_dir($dir)) {
		$m = umask(0000);
		$ok = @mkdir($dir, 0777, true);
		umask($m);
		if (!$ok && !is_dir($dir)) {
			$error = 'Kon avatar-map niet aanmaken.';
			return false;
		}
	}
	$src = social_image_from_file($file['tmp_name'], $info['mime']);
	if (!$src) {
		$error = 'Kon profielfoto niet openen om bij te snijden.';
		return false;
	}
	$crop = social_avatar_crop_from_post($info[0], $info[1]);
	$dst_size = 512;
	$dst = imagecreatetruecolor($dst_size, $dst_size);
	if (!$dst) {
		imagedestroy($src);
		$error = 'Kon avatar niet verwerken.';
		return false;
	}
	imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
	if (!imagecopyresampled($dst, $src, 0, 0, $crop['x'], $crop['y'], $dst_size, $dst_size, $crop['size'], $crop['size'])) {
		imagedestroy($src);
		imagedestroy($dst);
		$error = 'Kon avatar niet bijsnijden.';
		return false;
	}
	$filename = social_normalize_username($username).'-'.substr(sha1(uniqid('', true)), 0, 12).'.jpg';
	$m = umask(0111);
	$ok = @imagejpeg($dst, $dir.'/'.$filename, 90);
	umask($m);
	imagedestroy($src);
	imagedestroy($dst);
	if (!$ok) {
		$error = 'Kon profielfoto niet opslaan.';
		return false;
	}
	return 'content/profile_avatars/'.$filename;
}


function social_update_account($display_name, $avatar_url, &$error)
{
	$current = social_current_username();
	if (!$current) {
		$error = 'Log in om je account aan te passen.';
		return false;
	}
	$display_name = trim($display_name);
	if ($display_name == '') {
		$display_name = $current;
	}
	if (80 < strlen($display_name)) {
		$error = 'Naam is te lang.';
		return false;
	}
	$uploaded_avatar = social_upload_avatar($current, $error);
	if ($uploaded_avatar === false) {
		return false;
	}
	if ($uploaded_avatar != '') {
		$avatar_url = $uploaded_avatar;
	}
	$avatar_url = social_clean_avatar_url($avatar_url);
	if ($avatar_url === false) {
		$error = 'Gebruik een http(s)-URL, /pad of content/pad voor je profielfoto.';
		return false;
	}
	$data = social_read_users();
	if (!isset($data['users'][$current])) {
		$error = 'Gebruiker bestaat niet.';
		return false;
	}
	$old_avatar_url = isset($data['users'][$current]['avatar_url']) ? $data['users'][$current]['avatar_url'] : '';
	$data['users'][$current]['display_name'] = $display_name;
	$data['users'][$current]['avatar_url'] = $avatar_url;
	if (!social_write_users($data)) {
		$error = 'Kon account niet opslaan.';
		return false;
	}
	if ($uploaded_avatar != '' && $old_avatar_url != '' && $old_avatar_url != $uploaded_avatar) {
		social_delete_owned_avatar($current, $old_avatar_url);
	}
	return true;
}


function social_following($username = false)
{
	if ($username === false) {
		$username = social_current_username();
	}
	$username = social_normalize_username($username);
	$data = social_read_users();
	if (!isset($data['users'][$username]) || !isset($data['users'][$username]['following']) || !is_array($data['users'][$username]['following'])) {
		return array();
	}
	$ret = array();
	foreach ($data['users'][$username]['following'] as $followed) {
		$followed = social_normalize_username($followed);
		if ($followed != $username && isset($data['users'][$followed])) {
			$ret[] = $followed;
		}
	}
	$ret = array_values(array_unique($ret));
	sort($ret);
	return $ret;
}


function social_is_following($follower, $target)
{
	$follower = social_normalize_username($follower);
	$target = social_normalize_username($target);
	return in_array($target, social_following($follower));
}


function social_set_following($target, $follow, &$error)
{
	$current = social_current_username();
	$target = social_normalize_username($target);
	if (!$current) {
		$error = 'Log in om gebruikers te volgen.';
		return false;
	}
	if (!social_valid_username($target)) {
		$error = 'Ongeldige gebruiker.';
		return false;
	}
	if ($target == $current) {
		$error = 'Je kunt jezelf niet volgen.';
		return false;
	}
	$data = social_read_users();
	if (!isset($data['users'][$target])) {
		$error = 'Gebruiker bestaat niet.';
		return false;
	}
	if (!isset($data['users'][$current]['following']) || !is_array($data['users'][$current]['following'])) {
		$data['users'][$current]['following'] = array();
	}
	$following = array();
	foreach ($data['users'][$current]['following'] as $username) {
		$username = social_normalize_username($username);
		if ($username != $current && isset($data['users'][$username])) {
			$following[] = $username;
		}
	}
	if ($follow) {
		$following[] = $target;
	} else {
		$following = array_diff($following, array($target));
	}
	$following = array_values(array_unique($following));
	sort($following);
	$data['users'][$current]['following'] = $following;
	if (!social_write_users($data)) {
		$error = 'Kon volgen niet opslaan.';
		return false;
	}
	return true;
}


function social_login($username)
{
	social_session_start();
	$_SESSION['social_user'] = $username;
}


function social_logout()
{
	social_session_start();
	unset($_SESSION['social_user']);
}


function social_hash_password($password)
{
	if (function_exists('password_hash')) {
		return password_hash($password, PASSWORD_DEFAULT);
	}
	$salt = substr(str_replace('+', '.', base64_encode(pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand()))), 0, 22);
	return '$hotglue$'.$salt.'$'.hash('sha256', $salt.$password);
}


function social_hash_equals($a, $b)
{
	if (function_exists('hash_equals')) {
		return hash_equals($a, $b);
	}
	if (strlen($a) != strlen($b)) {
		return false;
	}
	$res = 0;
	for ($i = 0; $i < strlen($a); $i++) {
		$res |= ord($a[$i]) ^ ord($b[$i]);
	}
	return $res === 0;
}


function social_verify_password($password, $hash)
{
	if (function_exists('password_verify') && substr($hash, 0, 1) == '$' && substr($hash, 0, 9) != '$hotglue$') {
		return password_verify($password, $hash);
	}
	if (substr($hash, 0, 9) == '$hotglue$') {
		$parts = explode('$', $hash);
		if (count($parts) != 4) {
			return false;
		}
		return social_hash_equals($hash, '$hotglue$'.$parts[2].'$'.hash('sha256', $parts[2].$password));
	}
	return false;
}


function social_register_user($username, $password, &$error)
{
	$username = social_normalize_username($username);
	if (!social_valid_username($username)) {
		$error = 'Gebruik 3-32 tekens: kleine letters, cijfers en underscores.';
		return false;
	}
	if (social_reserved_username($username)) {
		$error = 'Deze naam is gereserveerd voor rdbrr.';
		return false;
	}
	if (strlen($password) < 8) {
		$error = 'Gebruik een wachtwoord van minimaal 8 tekens.';
		return false;
	}
	$data = social_read_users();
	if (isset($data['users'][$username])) {
		$error = 'Deze gebruikersnaam bestaat al.';
		return false;
	}
	$role = count($data['users']) == 0 || in_array($username, social_admin_usernames()) ? 'admin' : 'user';
	$page = social_profile_page($username);
	if (page_exists($page.'.head')) {
		$error = 'De profielpagina voor deze naam bestaat al.';
		return false;
	}
	$data['users'][$username] = array(
		'username'=>$username,
		'display_name'=>$username,
		'avatar_url'=>'',
		'password_hash'=>social_hash_password($password),
		'role'=>$role,
		'status'=>'active',
		'following'=>array(),
		'page'=>$page,
		'created_at'=>gmdate('c')
	);
	if (!social_write_users($data)) {
		$error = 'Kon gebruiker niet opslaan.';
		return false;
	}
	if (!social_create_profile_page($username)) {
		unset($data['users'][$username]);
		social_write_users($data);
		$error = 'Kon profielpagina niet aanmaken.';
		return false;
	}
	return $data['users'][$username];
}


function social_create_profile_page($username)
{
	load_modules('glue');
	$page = social_profile_page($username).'.head';
	$ret = create_page(array('page'=>$page));
	if (is_array($ret) && isset($ret['#error']) && $ret['#error']) {
		return false;
	}
	$object = $page.'.intro';
	update_object(array(
		'name'=>$object,
		'type'=>'text',
		'module'=>'text',
		'content'=>"$username\n\nSleep tekst, beeld en links op deze pagina om je profiel te maken.",
		'text-font-size'=>'24px',
		'text-font-family'=>'DejaVuSans',
		'text-background-color'=>'#ffffff',
		'text-padding-x'=>'18px',
		'text-padding-y'=>'14px',
		'object-left'=>'80px',
		'object-top'=>'80px',
		'object-width'=>'520px',
		'object-height'=>'170px'
	));
	update_object(array(
		'name'=>$page.'.page',
		'type'=>'page',
		'module'=>'page',
		'page-title'=>$username,
		'page-background-color'=>'#f7f3ed'
	));
	update_object(array(
		'name'=>$page.'.wall',
		'type'=>'social_wall',
		'module'=>'social_wall',
		'wall-title'=>'Berichten',
		'wall-background-color'=>'#ffffff',
		'wall-font-color'=>'#202020',
		'wall-font-family'=>'DejaVuSans',
		'wall-font-size'=>'13px',
		'wall-border-color'=>'#202020',
		'wall-border-radius'=>'0px',
		'object-left'=>'80px',
		'object-top'=>'300px',
		'object-width'=>'460px',
		'object-height'=>'360px'
	));
	update_object(array(
		'name'=>$page.'.updates',
		'type'=>'social_microblog',
		'module'=>'social_microblog',
		'microblog-title'=>'Updates',
		'microblog-background-color'=>'#ffffff',
		'microblog-font-color'=>'#202020',
		'microblog-font-family'=>'DejaVuSans',
		'microblog-font-size'=>'13px',
		'microblog-border-color'=>'#202020',
		'microblog-border-radius'=>'0px',
		'object-left'=>'580px',
		'object-top'=>'300px',
		'object-width'=>'420px',
		'object-height'=>'360px'
	));
	return true;
}


function social_authenticate($username, $password)
{
	$username = social_normalize_username($username);
	$data = social_read_users();
	if (!isset($data['users'][$username])) {
		return false;
	}
	if (social_user_status($data['users'][$username]) == 'disabled') {
		return false;
	}
	if (!social_verify_password($password, $data['users'][$username]['password_hash'])) {
		return false;
	}
	return $data['users'][$username];
}


function social_can_edit_page($page)
{
	$username = social_current_username();
	if (!$username) {
		return false;
	}
	if (social_is_admin()) {
		return true;
	}
	$a = expl('.', $page);
	return $a[0] == social_profile_page($username);
}


function social_extract_page_from_service_args($args)
{
	if (isset($args['page']) && is_string($args['page'])) {
		return $args['page'];
	}
	if (isset($args['pagename']) && is_string($args['pagename'])) {
		return $args['pagename'].'.head';
	}
	if (isset($args['name']) && is_string($args['name'])) {
		$a = expl('.', $args['name']);
		if (2 <= count($a)) {
			return $a[0].'.'.$a[1];
		}
	}
	if (isset($args['html']) && is_string($args['html']) && preg_match('/\sid=("|\')([^"\']+)("|\')/', $args['html'], $m)) {
		$a = expl('.', $m[2]);
		if (2 <= count($a)) {
			return $a[0].'.'.$a[1];
		}
	}
	return false;
}


function social_authorize_service($service, $args)
{
	if (!social_enabled() || AUTH_METHOD != 'social') {
		return true;
	}
	if (social_is_admin()) {
		return true;
	}
	if (in_array($service, array('social_wall.post', 'social_wall.delete', 'social_microblog.post', 'social_microblog.delete', 'social_microblog.timeline_post'))) {
		return true;
	}
	$denied = array(
		'glue.copy_page'=>true,
		'glue.delete_page'=>true,
		'glue.rename_page'=>true,
		'glue.set_startpage'=>true,
		'glue.object_make_symlink'=>true,
		'page.set_grid'=>true
	);
	if (isset($denied[$service])) {
		return response('Deze actie is niet beschikbaar voor profielaccounts.', 403);
	}
	if ($service == 'user_code.set_code' && isset($args['page']) && $args['page'] === false) {
		return response('Globale site-code kan niet door profielaccounts worden aangepast.', 403);
	}
	$page = social_extract_page_from_service_args($args);
	if ($page !== false && !social_can_edit_page($page)) {
		return response('Je kunt alleen je eigen profielpagina aanpassen.', 403);
	}
	return true;
}


function social_login_url($next = '')
{
	$url = base_url().'?login';
	if ($next != '') {
		$url .= '&next='.urlencode($next);
	}
	return $url;
}


function social_profile_url($username)
{
	return base_url().'u/'.rawurlencode($username);
}


function social_redirect_url($next)
{
	if ($next != '' && substr($next, 0, 2) == 'u/') {
		return base_url().$next;
	}
	if (in_array($next, array('feed', 'profiles', 'timeline'))) {
		return base_url().$next;
	}
	return $next != '' ? base_url().'?'.$next : '';
}


function social_top_nav($active = '', $profile_username = '')
{
	$current = social_current_username();
	$ret = '<div class="social-top-nav">'.nl();
	$ret .= tab().'<a class="social-top-logo" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'timeline">rdbrr</a>'.nl();
	if ($current) {
		$ret .= tab().'<a'.($active == 'timeline' ? ' class="active"' : '').' href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'timeline">Tijdlijn</a>'.nl();
		$ret .= tab().'<a'.($active == 'profile' ? ' class="active"' : '').' href="'.htmlspecialchars(social_profile_url($current), ENT_COMPAT, 'UTF-8').'">Mijn profiel</a>'.nl();
		$ret .= tab().'<a'.($active == 'account' ? ' class="active"' : '').' href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?account">Account</a>'.nl();
	}
	$ret .= tab().'<a'.($active == 'feed' ? ' class="active"' : '').' href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'feed">Alles</a>'.nl();
	$ret .= tab().'<a'.($active == 'profiles' ? ' class="active"' : '').' href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?profiles">Profielen</a>'.nl();
	if ($current) {
		$ret .= tab().'<a href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?logout">Uitloggen</a>'.nl();
	} else {
		$ret .= tab().'<a href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?login">Inloggen</a>'.nl();
	}
	if ($profile_username != '') {
		$ret .= social_follow_form($profile_username, 'u/'.$profile_username, true);
	}
	$ret .= '</div>'.nl();
	return $ret;
}


function social_follow_form($target, $next = '', $compact = false)
{
	$current = social_current_username();
	$target = social_normalize_username($target);
	if (!$current || $current == $target || !social_valid_username($target)) {
		return '';
	}
	$following = social_is_following($current, $target);
	$action = $following ? 'unfollow' : 'follow';
	$label = $following ? 'Ontvolgen' : 'Volgen';
	$ret = '<form class="social-follow-form'.($compact ? ' compact' : '').'" method="post" action="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?follow">';
	$ret .= '<input type="hidden" name="username" value="'.htmlspecialchars($target, ENT_COMPAT, 'UTF-8').'">';
	$ret .= '<input type="hidden" name="action" value="'.htmlspecialchars($action, ENT_COMPAT, 'UTF-8').'">';
	if ($next != '') {
		$ret .= '<input type="hidden" name="next" value="'.htmlspecialchars($next, ENT_COMPAT, 'UTF-8').'">';
	}
	$ret .= '<input type="submit" value="'.htmlspecialchars($label, ENT_COMPAT, 'UTF-8').'">';
	$ret .= '</form>';
	return $ret;
}


function social_form_page($title, $body, $error = '', $extra_js = array())
{
	html_flush();
	default_html(false);
	html_add_css(base_url().'css/hotglue_error.css');
	html_add_css(base_url().'css/social.css');
	if (count($extra_js)) {
		$jquery = JQUERY;
		html_add_js(is_url($jquery) ? $jquery : base_url().$jquery, 1);
	}
	foreach ($extra_js as $js) {
		html_add_js($js);
	}
	$bdy = &body();
	elem_attr($bdy, 'id', 'social');
	body_append(tab(1).'<div id="paper">'.nl());
	body_append(tab(2).'<div id="wrapper">'.nl());
	body_append(tab(3).'<div id="content">'.nl());
	body_append(tab(4).'<div id="left-nav">'.nl());
	body_append(tab(5).'<a class="rdbrr-logo" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'">rdbrr</a>'.nl());
	body_append(tab(4).'</div>'.nl());
	body_append(tab(4).'<div id="main">'.nl());
	body_append(tab(5).'<h1 id="error-title">'.htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8').'</h1>'.nl());
	if ($error != '') {
		body_append(tab(5).'<p class="social-error">'.htmlspecialchars($error, ENT_NOQUOTES, 'UTF-8').'</p>'.nl());
	}
	body_append($body);
	body_append(tab(4).'</div>'.nl());
	body_append(tab(3).'</div>'.nl());
	body_append(tab(2).'</div>'.nl());
	body_append(tab(1).'</div>'.nl());
	echo html_finalize();
}


function social_manage_user($username, $action, &$error)
{
	$username = social_normalize_username($username);
	$current = social_current_username();
	if (!social_valid_username($username)) {
		$error = 'Ongeldige gebruiker.';
		return false;
	}
	$data = social_read_users();
	if (!isset($data['users'][$username])) {
		$error = 'Gebruiker bestaat niet.';
		return false;
	}
	if ($username == $current && in_array($action, array('make_user', 'disable'))) {
		$error = 'Je kunt jezelf niet degraderen of blokkeren.';
		return false;
	}
	if ($action == 'make_admin') {
		$data['users'][$username]['role'] = 'admin';
	} elseif ($action == 'make_user') {
		$data['users'][$username]['role'] = 'user';
	} elseif ($action == 'disable') {
		$data['users'][$username]['status'] = 'disabled';
	} elseif ($action == 'enable') {
		$data['users'][$username]['status'] = 'active';
	} else {
		$error = 'Onbekende beheeractie.';
		return false;
	}
	if (!social_write_users($data)) {
		$error = 'Kon gebruiker niet opslaan.';
		return false;
	}
	return true;
}


function social_controller_account($args)
{
	$current = social_current_username();
	if (!$current) {
		header('Location: '.social_login_url('account'));
		die();
	}
	$error = '';
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$action = isset($_POST['account_action']) ? $_POST['account_action'] : 'save_account';
		if ($action == 'delete_upload') {
			$ok = social_delete_upload_from_account($current, $error);
		} else {
			$display_name = isset($_POST['display_name']) ? $_POST['display_name'] : '';
			$avatar_url = isset($_POST['avatar_url']) ? $_POST['avatar_url'] : '';
			$ok = social_update_account($display_name, $avatar_url, $error);
		}
		if ($ok) {
			header('Location: '.base_url().'?account');
			die();
		}
	}
	$user = social_user_by_username($current);
	$display_name = $user && isset($user['display_name']) ? $user['display_name'] : $current;
	$avatar_url = $user && isset($user['avatar_url']) ? $user['avatar_url'] : '';
	$body = tab(5).'<div class="social-account-preview">'.social_avatar_html($current).' <strong>'.htmlspecialchars($display_name, ENT_NOQUOTES, 'UTF-8').'</strong></div>'.nl();
	$body .= tab(5).'<form class="social-account-form" method="post" enctype="multipart/form-data" action="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?account">'.nl();
	$body .= tab(6).'<label>Naam<br><input name="display_name" type="text" value="'.htmlspecialchars($display_name, ENT_COMPAT, 'UTF-8').'"></label>'.nl();
	$body .= tab(6).'<div class="social-avatar-editor">'.nl();
	$body .= tab(7).'<label>Profielfoto uploaden<br><input class="social-avatar-file" name="avatar_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></label>'.nl();
	$body .= tab(7).'<div class="social-avatar-cropper" hidden>'.nl();
	$body .= tab(8).'<div class="social-avatar-crop-stage">'.nl();
	$body .= tab(9).'<img class="social-avatar-crop-image" alt="">'.nl();
	$body .= tab(9).'<div class="social-avatar-crop-box"><span></span></div>'.nl();
	$body .= tab(8).'</div>'.nl();
	$body .= tab(8).'<p class="social-avatar-crop-hint">Sleep het kader. Pak de hoek om groter of kleiner te snijden.</p>'.nl();
	$body .= tab(7).'</div>'.nl();
	$body .= tab(7).'<input name="avatar_crop_x" type="hidden" value="">'.nl();
	$body .= tab(7).'<input name="avatar_crop_y" type="hidden" value="">'.nl();
	$body .= tab(7).'<input name="avatar_crop_size" type="hidden" value="">'.nl();
	$body .= tab(6).'</div>'.nl();
	$body .= tab(6).'<label>Of profielfoto URL/pad<br><input name="avatar_url" type="text" value="'.htmlspecialchars($avatar_url, ENT_COMPAT, 'UTF-8').'" placeholder="https://... of content/..."></label>'.nl();
	$body .= tab(6).'<input type="submit" value="Opslaan">'.nl();
	$body .= tab(5).'</form>'.nl();
	$body .= social_render_upload_manager($current);
	$body .= tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'timeline">Terug naar tijdlijn</a></p>'.nl();
	social_form_page('Account', $body, $error, array(base_url().'js/social_account.js?v='.filemtime('js/social_account.js')));
}


function social_controller_login($args)
{
	$next = isset($_GET['next']) ? $_GET['next'] : '';
	$next = str_replace(array("\r", "\n"), '', $next);
	$error = '';
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$username = isset($_POST['username']) ? $_POST['username'] : '';
		$password = isset($_POST['password']) ? $_POST['password'] : '';
		$user = social_authenticate($username, $password);
		if ($user) {
			social_login($user['username']);
			$dest = $next != '' ? social_redirect_url($next) : base_url().'timeline';
			header('Location: '.$dest);
			die();
		}
		$error = 'Gebruikersnaam of wachtwoord klopt niet.';
	}
	$body = tab(5).'<form method="post" action="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?login'.($next != '' ? '&next='.htmlspecialchars(urlencode($next), ENT_COMPAT, 'UTF-8') : '').'">'.nl();
	$body .= tab(6).'<label>Gebruikersnaam<br><input name="username" type="text" autocomplete="username"></label>'.nl();
	$body .= tab(6).'<label>Wachtwoord<br><input name="password" type="password" autocomplete="current-password"></label>'.nl();
	$body .= tab(6).'<input type="submit" value="Log in">'.nl();
	$body .= tab(5).'</form>'.nl();
	$body .= tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?register">Maak een account</a></p>'.nl();
	social_form_page('Inloggen', $body, $error);
}


function social_controller_admin($args)
{
	if (!social_current_user()) {
		header('Location: '.social_login_url('admin'));
		die();
	}
	if (!social_is_admin()) {
		hotglue_error(403);
	}
	$error = '';
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$username = isset($_POST['username']) ? $_POST['username'] : '';
		$action = isset($_POST['action']) ? $_POST['action'] : '';
		social_manage_user($username, $action, $error);
	}
	$data = social_read_users();
	ksort($data['users']);
	$body = tab(5).'<table class="social-admin-users">'.nl();
	$body .= tab(6).'<tr><th>Gebruiker</th><th>Rol</th><th>Status</th><th>Acties</th></tr>'.nl();
	foreach ($data['users'] as $username=>$user) {
		$role = social_user_role($user, $data);
		$status = social_user_status($user);
		$body .= tab(6).'<tr>'.nl();
		$body .= tab(7).'<td><a href="'.htmlspecialchars(social_profile_url($username), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars($username, ENT_NOQUOTES, 'UTF-8').'</a></td>'.nl();
		$body .= tab(7).'<td>'.htmlspecialchars($role, ENT_NOQUOTES, 'UTF-8').'</td>'.nl();
		$body .= tab(7).'<td>'.htmlspecialchars($status, ENT_NOQUOTES, 'UTF-8').'</td>'.nl();
		$body .= tab(7).'<td>'.nl();
		$body .= tab(8).'<a href="'.htmlspecialchars(social_profile_url($username), ENT_COMPAT, 'UTF-8').'/edit">bewerk profiel</a>'.nl();
		if ($role == 'admin') {
			$body .= social_admin_action_form($username, 'make_user', 'maak gebruiker');
		} else {
			$body .= social_admin_action_form($username, 'make_admin', 'maak admin');
		}
		if ($status == 'disabled') {
			$body .= social_admin_action_form($username, 'enable', 'deblokkeer');
		} else {
			$body .= social_admin_action_form($username, 'disable', 'blokkeer');
		}
		$body .= tab(7).'</td>'.nl();
		$body .= tab(6).'</tr>'.nl();
	}
	$body .= tab(5).'</table>'.nl();
	$body .= tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?profiles">Terug naar profielen</a></p>'.nl();
	social_form_page('Beheer', $body, $error);
}


function social_admin_action_form($username, $action, $label)
{
	$ret = '<form class="social-admin-action" method="post" action="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?admin">';
	$ret .= '<input type="hidden" name="username" value="'.htmlspecialchars($username, ENT_COMPAT, 'UTF-8').'">';
	$ret .= '<input type="hidden" name="action" value="'.htmlspecialchars($action, ENT_COMPAT, 'UTF-8').'">';
	$ret .= '<input type="submit" value="'.htmlspecialchars($label, ENT_COMPAT, 'UTF-8').'">';
	$ret .= '</form>';
	return $ret;
}


function social_controller_register($args)
{
	$error = '';
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		$username = isset($_POST['username']) ? $_POST['username'] : '';
		$password = isset($_POST['password']) ? $_POST['password'] : '';
		$user = social_register_user($username, $password, $error);
		if ($user) {
			social_login($user['username']);
			header('Location: '.social_profile_url($user['username']).'/edit');
			die();
		}
	}
	$body = tab(5).'<form method="post" action="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?register">'.nl();
	$body .= tab(6).'<label>Gebruikersnaam<br><input name="username" type="text" pattern="[a-z0-9_]{3,32}" autocomplete="username"></label>'.nl();
	$body .= tab(6).'<label>Wachtwoord<br><input name="password" type="password" autocomplete="new-password"></label>'.nl();
	$body .= tab(6).'<input type="submit" value="Account maken">'.nl();
	$body .= tab(5).'</form>'.nl();
	$body .= tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?login">Ik heb al een account</a></p>'.nl();
	social_form_page('Account maken', $body, $error);
}


function social_controller_logout($args)
{
	social_logout();
	header('Location: '.base_url().'?login');
	die();
}


function social_controller_me($args)
{
	$username = social_current_username();
	if (!$username) {
		header('Location: '.social_login_url('me'));
		die();
	}
	header('Location: '.base_url().'timeline');
	die();
}


function social_controller_follow($args)
{
	if (!social_current_username()) {
		header('Location: '.social_login_url('timeline'));
		die();
	}
	$target = isset($_POST['username']) ? $_POST['username'] : '';
	$action = isset($_POST['action']) ? $_POST['action'] : '';
	$next = isset($_POST['next']) ? $_POST['next'] : 'timeline';
	$next = str_replace(array("\r", "\n"), '', $next);
	$error = '';
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		social_set_following($target, $action == 'follow', $error);
	}
	$dest = $next != '' ? social_redirect_url($next) : base_url().'timeline';
	header('Location: '.$dest);
	die();
}


function social_controller_profiles($args)
{
	$data = social_read_users();
	ksort($data['users']);
	$body = '';
	foreach ($data['users'] as $username=>$user) {
		$body .= tab(5).'<p class="social-profile-row"><a id="home" href="'.htmlspecialchars(social_profile_url($username), ENT_COMPAT, 'UTF-8').'">'.htmlspecialchars($username, ENT_NOQUOTES, 'UTF-8').'</a> '.social_follow_form($username, 'profiles', true).'</p>'.nl();
	}
	if ($body == '') {
		$body = tab(5).'<p>Er zijn nog geen profielen.</p>'.nl();
	}
	$body .= tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'timeline">Tijdlijn</a> - <a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'feed">Alles</a></p>'.nl();
	$current = social_current_username();
	if ($current) {
		$body .= tab(5).'<p><a href="'.htmlspecialchars(social_profile_url($current), ENT_COMPAT, 'UTF-8').'/edit">Mijn profiel bewerken</a> - <a href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?logout">Uitloggen</a></p>'.nl();
		if (social_is_admin()) {
			$body .= tab(5).'<p><a id="home" href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?admin">Beheer</a></p>'.nl();
		}
	} else {
		$body .= tab(5).'<p><a href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?login">Inloggen</a> - <a href="'.htmlspecialchars(base_url(), ENT_COMPAT, 'UTF-8').'?register">Account maken</a></p>'.nl();
	}
	social_form_page('Profielen', $body);
}


function social_controller_feed($args)
{
	load_modules('social_microblog');
	social_microblog_feed_page();
}


function social_controller_timeline($args)
{
	load_modules('social_microblog');
	social_microblog_timeline_page();
}
