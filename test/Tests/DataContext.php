<?php
/**
 * Entity tests
 *
 * @package Test
 * @author Andreas Götz <cpuidle@gmx.de>
 */

namespace Tests;

abstract class DataContext extends Middleware
{
	static $uuid;
	static $precision = 0.001;	// mimic View\PRECISION

	/**
	 * Initialize context
	 */
	static function setupBeforeClass() {
		parent::setupBeforeClass();
		self::$context = self::$mw . 'data';

		self::$precision = pow(10, -\Volkszaehler\View\View::PRECISION);
	}

	/**
	 * Remove channel if initialized
	 */
	static function tearDownAfterClass() {
		if (static::$uuid) {
			self::deleteChannel(static::$uuid);
			static::$uuid = null;
		}
		parent::tearDownAfterClass();
 	}

	static function createChannel($title, $type, $resolution = null) {
		$url = self::$mw . 'channel.json?operation=add&title=' . urlencode($title) . '&type=' . urlencode($type);
		if ($resolution) $url .= '&resolution=' . $resolution;
		$json = self::getJsonRaw($url);

		return ((isset($json->entity->uuid)) ? $json->entity->uuid : null);
	}

	static function deleteChannel($uuid) {
		$url = self::$mw . 'channel/' . $uuid . '.json?operation=delete';
		$json = self::getJsonRaw($url);
	}

	protected function addTuple($ts, $value, $uuid = null) {
		$url = self::$context . '/' . (($uuid) ?: static::$uuid) .
			   '.json?operation=add&ts=' . $ts . '&value=' . $value;
		return $this->getJson($url);
	}

	protected function getTuplesByUrl($url, $from = null, $to = null, $group = null, $tuples = null, $extra = null) {
		if ($from)  $url .= 'from=' . $from . '&';
		if ($to) 	$url .= 'to=' . $to . '&';
		if ($group) $url .= 'group=' . $group . '&';
		if ($tuples)$url .= 'tuples=' . $tuples . '&';
		if ($extra) $url .= $extra . '&';

		$this->getJson($url);
		$this->assertUUID();

		return $this->json;
	}

	protected function getTuples($from = null, $to = null, $group = null, $tuples = null) {
		$url = self::$context . '/' . static::$uuid . '.json?';
		return $this->getTuplesByUrl($url, $from, $to, $group, $tuples);
	}

	protected function getTuplesRaw($from = null, $to = null, $group = null, $tuples = null) {
		$url = self::$context . '/' . static::$uuid . '.json?options=exact&';
		return $this->getTuplesByUrl($url, $from, $to, $group, $tuples);
	}

	protected function debug() {
		echo('url: ' . $this->url . "<br/>\n" . print_r($this->json,1) . "<br/>\n");
	}

	/**
	 * Helper assertion to validate correct UUID
	 */
	protected function assertUUID() {
		$this->assertEquals(static::$uuid, (isset($this->json->data->uuid) ? $this->json->data->uuid : null),
			"Wrong UUID. Expected " . static::$uuid . ", got " . $this->json->data->uuid);
	}

	/**
	 * Helper assertion to validate header fields
	 */
	protected function assertHeader($consumption, $average, $rows = null) {
		$this->assertEquals($consumption, $this->json->data->consumption, "<consumption> mismatch", self::$precision);
		$this->assertEquals($average, $this->json->data->average, "<average> mismatch", self::$precision);
		if (isset($rows)) {
			$this->assertEquals($rows, $this->json->data->rows, "<rows> mismatch");
		}
	}

	/**
	 * Helper assertion to validate from/to header fields
	 */
	protected function assertFromTo($from, $to) {
		$this->assertEquals($from, $this->json->data->from, "<from> doesn't match request");
		$this->assertEquals($to, $this->json->data->to, "<to> doesn't match request");
	}

	/**
	 * Helper assertion to validate header min/max fields
	 */
	protected function assertMinMax($min, $max = null) {
		$this->assertTuple($min, $this->json->data->min, "<min> tuple mismatch");
		$this->assertTuple($max ?: $min, $this->json->data->max, "<max> tuple mismatch");
	}

	/**
	 * Helper assertion to validate correct tuple- either by value only or (sub)tuple as array
	 */
	protected function assertTuple($realTuple, $tuple, $msg = "Tuple mismatch") {
		// got index? retrieve data from tuples
		if (!is_array($realTuple)) {
			$realTuple = $this->json->data->tuples[$realTuple];
		}

		if (is_array($tuple)) {
			for ($i=0; $i<sizeof($tuple); $i++) {
				$this->assertEquals(
					$tuple[$i], $realTuple[$i],
					$msg . ". Got " . print_r(array_slice($realTuple, 0, sizeof($tuple)), 1) .
					  ", expected " . print_r($tuple,1),
					self::$precision);
			}
		}
		else {
			$this->assertEquals(
					$tuple, $realTuple[1],
					$msg . ". Got value " . $realTuple[1] .
					        ", expected " . $tuple,
					self::$precision);
		}
	}
}

?>
