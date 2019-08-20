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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Multifield table element.
 */
class CMultifieldTableElement extends CTableElement {

	const ROW_SELECTOR = 'xpath:./tbody/tr[contains(@class, "form_row") or contains(@class, "pairRow") or contains(@class, "editable_table_row")]';

	/**
	 * Field mapping.
	 *
	 * @var array
	 */
	protected $mapping;

	/**
	 * Get field mapping.
	 *
	 * @return array
	 */
	public function getFieldMapping() {
		return is_array($this->mapping) ? $this->mapping : [];
	}

	/**
	 * Set field mapping.
	 * Field mapping is used to address controls within multifield table row.
	 *
	 * For example, if there is a three control row like this:
	 * [ tag         ] [Contains|Equals] [ value           ]
	 *
	 * The following mappings can be used:
	 *     1. ['tag', 'operator', 'value']
	 *        This will set names for the fields, but controls will be detected automatically (slow).
	 *     2. [['name' => 'tag'], ['name' => 'operator'], ['name' => 'value']]
	 *        This is the same mapping as was described in #1.
	 *     3. [
	 *            ['name' => 'tag', 'class' => 'CElement'],
	 *            ['name' => 'operator', 'class' => 'CSegmentedRadioElement'],
	 *            ['name' => 'value', 'class' => 'CElement']
	 *        ]
	 *        This will set names and expected control types for the fields (CElement is generic input).
	 *     4. [
	 *            ['name' => 'tag', 'selector' => 'xpath:./input', 'class' => 'CElement'],
	 *            ['name' => 'operator', 'selector' => 'class:radio-list-control', 'class' => 'CSegmentedRadioElement'],
	 *            ['name' => 'value', 'selector' => 'xpath:./input', 'class' => 'CElement']
	 *        ]
	 *        This will set names, selectors and expected control types for the fields.
	 *
	 * Field mapping indices should match indices of columns in table row. For example, for sortable table rows, there
	 * is an additional column with sortable controls (first column in this example):
	 * [::] [ field ] [ value ]
	 *
	 * When defining a mapping, sortable column could be skipped by specifying the indices:
	 * [1 => 'field', 2 => 'value']
	 * Or it could be set to null:
	 * [null, 'field', 'value']
	 *
	 * For tables with headings, mapping keys should match headings and not indices. For example, mapping for table:
	 * Name               Value
	 * [ tag            ] [ value             ]
	 * Should be defined as follows (array keys match table headers):
	 * ['Name' => 'tag', 'Value' => 'value']
	 *
	 * Be advised that when mapping is not set, multifield operations are slower and fields are indexed by indices (for
	 * tables without headers) or by header text (for tables with headers).
	 *
	 * @param array $mapping    field mapping
	 */
	public function setFieldMapping($mapping) {
		$this->mapping = $mapping;

		return $this;
	}

	/**
	 * Get collection of table rows.
	 *
	 * @return CElementCollection
	 */
	public function getRows() {
		return $this->query(self::ROW_SELECTOR)->asTableRow(['parent' => $this])->all();
	}

	/**
	 * Get table row by index.
	 *
	 * @param $index    row index
	 *
	 * @return CTableRow
	 */
	public function getRow($index) {
		return $this->query(self::ROW_SELECTOR.'['.((int)$index + 1).']')->asTableRow(['parent' => $this])->one();
	}

	/**
	 * Get control data from row.
	 *
	 * @param CTableRowElement $row        table row
	 * @param string|integer   $column     column name or index
	 *
	 * @return array
	 */
	protected function getRowControlData($row, $column) {
		if (($mapping = CTestArrayHelper::get($this->mapping, $column, $column)) === null) {
			return null;
		}

		if (!is_array($mapping)) {
			$mapping = ['name' => $mapping];
		}
		elseif (!array_key_exists('name', $mapping)) {
			$mapping['name'] = $column;
		}

		$class = CTestArrayHelper::get($mapping, 'class', 'CElement');
		if (array_key_exists('selector', $mapping)) {
			$mapping['element'] = $row->getColumn($column)->query($mapping['selector'])
					->cast($class)
					->all()
					->find(CElementQuery::VISIBLE);
		}
		else {
			$mapping['element'] = CElementQuery::getInputElement($row->getColumn($column), '.', $class);
		}

		return $mapping;
	}

	/**
	 * Get controls from row.
	 *
	 * @param CTableRowElement $row        table row
	 * @param array            $headers    table headers
	 *
	 * @return array
	 */
	protected function getRowControls($row, $headers = null) {
		$controls = [];

		if ($headers === null) {
			$headers = $this->getHeadersText();
		}

		foreach ($row->query('xpath:./td|./th')->all() as $i => $column) {
			$column = CTestArrayHelper::get($headers, $i, $i);
			if (!array_key_exists($column, $this->mapping)) {
				$column = $i;
			}

			$data = $this->getRowControlData($row, $column);
			if ($data['element'] === null) {
				continue;
			}

			$controls[$data['name']] = $data['element'];
		}

		return $controls;
	}

	/**
	 * Get values from all the rows.
	 *
	 * @return array
	 */
	public function getValue() {
		$data = [];
		$headers = $this->getHeadersText();

		foreach ($this->getRows() as $row) {
			$values = [];

			foreach ($this->getRowControls($row, $headers) as $name => $control) {
				$values[$name] = $control->getValue();
			}

			$data[] = $values;
		}

		return $data;
	}

	/**
	 * Get values from a specific row.
	 *
	 * @param integer $index     row index
	 *
	 * @return array
	 */
	public function getRowValue($index) {
		$value = [];

		foreach ($this->getRowControls($this->getRow($index)) as $name => $control) {
			$value[$name] = $control->getValue();
		}

		return $value;
	}

	/**
	 * Add new row.
	 *
	 * @param array $values    row values
	 *
	 * return $this
	 */
	public function addRow($values) {
		$rows = $this->getRows()->count();
		$this->query('button:Add')->one()->click();

		// Wait until new table row appears.
		$this->query(self::ROW_SELECTOR.'['.($rows + 1).']')->waitUntilPresent();
		return $this->updateRow($rows, $values);
	}

	/**
	 * Update row by index.
	 *
	 * @param integer $index     row index
	 * @param array   $values    row values
	 *
	 * @throws Exception    if not all fields could be found within a row
	 *
	 * return $this
	 */
	public function updateRow($index, $values) {
		$headers = $this->getHeadersText();

		foreach ($values as $column => $value) {
			if (!array_key_exists($column, $this->getFieldMapping())) {
				$column = array_search($column, $headers);
			}

			$data = $this->getRowControlData($this->getRow($index), $column);
			if ($data['element'] === null) {
				throw new Exception('Failed to set values for field "'.$column.'" when filling multifield row'.
						' (control is not present).'
				);
			}

			$data['element']->fill($value);
		}

		return $this;
	}


	/**
	 * Remove row by index.
	 *
	 * @param array $index    row index
	 *
	 * return $this
	 */
	public function removeRow($index) {
		$row = $this->getRow($index);
		$row->query('button:Remove')->one()->click();
		$row->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Remove all rows.
	 *
	 * return $this
	 */
	public function clear() {
		foreach(array_reverse($this->getRows()->asArray()) as $row) {
			$row->query('button:Remove')->one()->click();
		}

		$this->query(self::ROW_SELECTOR)->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Find row indexes by row data.
	 *
	 * @param array $fields     row fields
	 *
	 * @return array
	 */
	protected function findRowsByFields($fields) {
		$indices = [];

		if (array_key_exists('index', $fields)) {
			return [$fields['index']];
		}

		foreach ($this->getValue() as $index => $values) {
			foreach ($fields as $name => $value) {
				if (array_key_exists($name, $values) && $values[$name] === $value) {
					$indices[] = $index;
					break;
				}
			}
		}

		return $indices;
	}

	/**
	 * Fill table with specified data.
	 * For example, if there is a two control row, with mapping set to ['tag', 'value'], the following $data values
	 * can be used:
	 *     1. [
	 *            ['tag' => 'tag1', 'value' => '1'],
	 *            ['tag' => 'tag2', 'value' => '2'],
	 *            ['tag' => 'tag3', 'value' => '3']
	 *        ]
	 *        This will add three rows with values "tag1:1", "tag2:2" and "tag2:3".
	 *     2. [
	 *            ['tag' => 'tag4', 'value' => '4'],
	 *            ['action' => USER_ACTION_UPDATE, 'index' => 1, 'tag' => 'new tag2', 'value' => 'new 2'],
	 *            ['action' => USER_ACTION_REMOVE, 'index' => 2],
	 *            ['action' => USER_ACTION_REMOVE, 'tag' => 'tag1']
	 *        ]
	 *        This will add row "tag4:4", will update row with index 1 to "new tag2:new 2", will remove rows by index 2
	 *        and rows by tag name "tag1".
	 *
	 * @param array $data    data array to be set.
	 *
	 * @throws Exception
	 *
	 * @return $this
	 */
	public function fill($data) {
		foreach ($data as $row) {
			$action = CTestArrayHelper::get($row, 'action', USER_ACTION_ADD);
			unset($row['action']);

			switch ($action) {
				case USER_ACTION_ADD:
					$this->addRow($row);
					break;

				case USER_ACTION_UPDATE:
					$indices = $this->findRowsByFields($row);
					unset($row['index']);

					foreach ($indices as $index) {
						$this->updateRow($index, $row);
					}

					break;

				case USER_ACTION_REMOVE:
					$indices = $this->findRowsByFields($row);
					sort($indices);

					foreach (array_reverse($indices) as $index) {
						$this->removeRow($index);
					}
					break;

				default:
					throw new Exception('Cannot perform action "'.$action.'".');
			}
		}
	}
}
