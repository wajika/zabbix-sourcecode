<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CWidget {

	private $title = null;
	private $controls = null;

	/**
	 * The contents of the body of the widget.
	 *
	 * @var array
	 */
	protected $body = [];

	public function setTitle($title) {
		$this->title = $title;

		return $this;
	}

	public function setControls($controls) {
		zbx_value2array($controls);
		$this->controls = $controls;

		return $this;
	}

	public function addItem($items = null) {
		if (!is_null($items)) {
			$this->body[] = $items;
		}

		return $this;
	}

	public function get() {
		$widget = [];

		$topHeader = $this->createTopHeader();
		if ($topHeader !== null) {
			$widget[] = $topHeader;
		}

		return [$widget, $this->body];
	}

	public function show() {
		echo $this->toString();

		return $this;
	}

	public function toString() {
		$tab = $this->get();

		return unpack_object($tab);
	}

	/**
	 * Create top header
	 *
	 * @return CDiv|null
	 */
	private function createTopHeader() {
		$divs = [];

		$title = $this->createTitle();
		if ($title !== null) {
			$divs[] = $title;
		}

		if ($this->controls !== null) {
			$divs[] = (new CDiv($this->controls))->addClass(ZBX_STYLE_CELL);
		}

		if (count($divs) > 0) {
			return (new CDiv($divs))
				->addClass(ZBX_STYLE_HEADER_TITLE)
				->addClass(ZBX_STYLE_TABLE);
		}
	}

	/**
	 * Create title
	 *
	 * @return CDiv
	 */
	protected function createTitle() {
		if ($this->title !== null) {
			return (new CDiv(new CTag('h1', true, $this->title)))->addClass(ZBX_STYLE_CELL);
		}
	}
}
