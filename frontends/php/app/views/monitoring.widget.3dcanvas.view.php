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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$init_script = 'jQuery.publish(' . CJs::encodeJson($data['event_data']) . ')';

$css = "<style>
/* TODO: Move to screens.scss */
.container-3dcanvas {
	position: relative;
	width: 100%;
	height: 100%;
	background-color: black;
	overflow: hidden;
}
.label-3dcanvas {
	position: absolute;
	pointer-events:none;
	color: #80ff80;
	font-weight: bold;
	top: -1000px;
	left: -1000px;
	transform: translate(0, -100%);
}
</style>";

$output = [
	'id' => 'THATAHTA',
	'header' => $data['name'],
	'body' => (new CDiv)
		->addClass('container-3dcanvas')
		->setAttribute('data-3d-canvas', '')
		->toString() . $css,
	'script_inline' => $init_script
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
