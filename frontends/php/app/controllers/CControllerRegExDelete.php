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


class CControllerRegExDelete extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'regexids' => 'required | array_db regexps.regexpid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		/** @var array $regexids */
		$regexids = $this->getinput('regexids');
		$this->db_regexes = DBfetchArray(DBselect('SELECT regexpid, name FROM regexps'.
			' WHERE '.dbConditionInt('regexpid', $regexids)
		));

		if (count($this->db_regexes) != count($regexids)) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$audit = [];
		$regexids = [];
		foreach ($this->db_regexes as $db_regex) {
			$regexids[] = $db_regex['regexpid'];
			$audit[] = 'Id ['.$db_regex['regexpid'].'] '._('Name').' ['.$db_regex['name'].']';
		}

		DBstart();
		$result = DBexecute('DELETE FROM regexps WHERE '.dbConditionInt('regexpid', $regexids));
		if ($result) {
			$result = DBend($result);
		}

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'regex.list'));
		if ($result) {
			$response->setFormData(['uncheck' => '1']);
			$response->setMessageOk(_n('Regular expression deleted', 'Regular expressions deleted',
				count($this->db_regexes)
			));

			foreach ($audit as $msg) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_REGEXP, $msg);
			}
		}
		else {
			$response->setMessageError(_n('Cannot delete regular expression', 'Cannot delete regular expressions',
				count($this->db_regexes)
			));
		}

		$this->setResponse($response);
	}
}