<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


// After locale has been initialized, add translations.
require_once dirname(__FILE__).'/../../include/translateDefines.inc.php';

/**
 * strpos function allow to check ETag value to fix cases when web server compression is used:
 * - For case when apache server appends "-gzip" suffix to ETag.
 *   https://bz.apache.org/bugzilla/show_bug.cgi?id=39727
 *   https://bz.apache.org/bugzilla/show_bug.cgi?id=45023
 * - For case when nginx v1.7.3+ server mark ETag as weak adding "W/" prefix
 *   http://nginx.org/en/CHANGES
 */
if (array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER) && strpos($_SERVER['HTTP_IF_NONE_MATCH'],
		$data['etag']) !== false) {
	header('HTTP/1.1 304 Not Modified');
	header('ETag: "'.$data['etag'].'"');
	exit;
}

if (in_array('prototype.js', $data['files'])) {
	// This takes care of the Array toJSON incompatibility with JSON.stringify.
	$data['js'] .=
		'var _json_stringify = JSON.stringify;'.
		'JSON.stringify = function(value) {'.
			'var _array_tojson = Array.prototype.toJSON,'.
				'ret;'.
			'delete Array.prototype.toJSON;'.
			'ret = _json_stringify(value);'.
			'Array.prototype.toJSON = _array_tojson;'.
			'return ret;'.
		'};';
}

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: public, must-revalidate');
header('ETag: "'.$data['etag'].'"');

echo $data['js'];
