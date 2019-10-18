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


class DbBinder {

	private $data = [];

	public function dbConditionId($key, $field_name, array $values, $not_in = false) {
		return $this->dbConditionInt($key, $field_name, $values, $not_in, true);
	}

	public function dbConditionInt($key, $field_name, array $values, $not_in = false, $zero_to_null = false) {
		global $DB;

		$MAX_EXPRESSIONS = 950; // Maximum  number of values for using "IN (<id1>,<id2>,...,<idN>)".
		$MIN_NUM_BETWEEN = 4; // Minimum number of consecutive values for using "BETWEEN <id1> AND <idN>".

		if (is_bool(reset($values))) {
			return '1=0';
		}

		$values = array_flip($values);

		$has_zero = false;

		if ($zero_to_null && array_key_exists(0, $values)) {
			$has_zero = true;
			unset($values[0]);
		}

		$values = array_keys($values);
		natsort($values);
		$values = array_values($values);

		$intervals = [];
		$singles = [];

		if ($DB['TYPE'] == ZBX_DB_ORACLE) {
			for ($i = 0, $size = count($values); $i < $size; $i++) {
				if ($i + $MIN_NUM_BETWEEN < $size && bcsub($values[$i + $MIN_NUM_BETWEEN], $values[$i]) == $MIN_NUM_BETWEEN) {
					// push interval first value
					$intervals[] = dbQuoteInt($values[$i]);

					for ($i += $MIN_NUM_BETWEEN; $i < $size && bcsub($values[$i], $values[$i - 1]) == 1; $i++);

					// push interval last value
					$intervals[] = dbQuoteInt($values[$i]);

					$i--;
				}
				else {
					$singles[] = dbQuoteInt($values[$i]);
				}
			}
		}
		else {
			$singles = array_map(function($value) {
				return dbQuoteInt($value);
			}, $values);
		}

		// concatenate conditions
		$condition = '';
		$logic = $not_in ? ' AND ' : ' OR ';

		// process intervals

		if ($DB['TYPE'] == ZBX_DB_ORACLE) {
			$intervals = $this->bind($key, $intervals);
		}

		$interval_chunks = array_chunk($intervals, 2);
		foreach ($interval_chunks as $interval) {
			if ($condition !== '') {
				$condition .= $logic;
			}

			$condition .= ($not_in ? 'NOT ' : '').$field_name.' BETWEEN '.$interval[0].' AND '.$interval[1];
		}

		// process individual values

		if ($DB['TYPE'] == ZBX_DB_ORACLE) {
			$singles = $this->bind($key, $singles);
		}

		$single_chunks = array_chunk($singles, $MAX_EXPRESSIONS);

		foreach ($single_chunks as $chunk) {
			if ($condition !== '') {
				$condition .= $logic;
			}

			if (count($chunk) == 1) {
				$condition .= $field_name.($not_in ? '!=' : '=').$chunk[0];
			}
			else {
				$condition .= $field_name.($not_in ? ' NOT' : '').' IN ('.implode(',', $chunk).')';
			}
		}

		if ($has_zero) {
			if ($condition !== '') {
				$condition .= $logic;
			}

			$condition .= $field_name.($not_in ? ' IS NOT NULL' : ' IS NULL');
		}

		if (!$not_in) {
			if ((int) $has_zero + count($interval_chunks) + count($single_chunks) > 1) {
				$condition = '('.$condition.')';
			}
		}

		return $condition;
	}

	public function getBinds() {
		$binds = [];
		foreach ($this->data as $key => $values) {
			foreach ($values as $index => $value) {
				$binds[':'.$key.$index] = $value;
			}
		}

		return $binds;
	}

	private function bind($key, array $values) {
		if (!$values) {
			return [];
		}

		$values = array_values($values);

		foreach ($values as &$value) {
			$value = (string) $value;
		}
		unset($value);

		$values_ret = [];

		if (array_key_exists($key, $this->data)) {
			$values_new = array_diff($values, $this->data[$key]);

			if ($values_new) {
				$this->data[$key] += array_combine(
					range(count($this->data[$key]), count($this->data[$key]) + count($values_new) - 1),
					$values_new
				);
			}

			$values_ret = array_intersect($this->data[$key], $values);
		}
		else {
			$this->data[$key] = $values;

			$values_ret = $values;
		}

		return array_map(function($index) use ($key) {
			return (':'.$key.$index);
		}, array_keys($values_ret));
	}
}
