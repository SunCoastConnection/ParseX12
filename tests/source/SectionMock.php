<?php

namespace SunCoastConnection\ParseX12\Tests;

use \SunCoastConnection\ParseX12\Raw;
use \SunCoastConnection\ParseX12\Section;

class SectionMock extends Section {

	static public $testSequence = [
		'A',
		1,
		'B',
		2,
		'C',
		3,
	];

	public function parse(Raw $raw) {}

	public function __toString() {}

	// public function __parseSequence(array $sequence, Raw $raw, &$objects) {
	// 	return $this->parseSequence($sequence, $raw, $objects);
	// }
}