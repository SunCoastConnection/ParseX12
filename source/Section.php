<?php

namespace SunCoastConnection\ParseX12;

use \SunCoastConnection\ParseX12\Options;
use \SunCoastConnection\ParseX12\Raw;
use \SunCoastConnection\ParseX12\Raw\Segment;

abstract class Section {

	/**
	 * Name of section
	 * @var string
	 */
	static protected $name;

	/**
	 * Section parent name
	 * @var string
	 */
	protected $parentName;

	/**
	 * Sub-sections of section
	 * @var array
	 */
	protected $subSections = [];

	/**
	 * Delimiter to separate sub-sections
	 * @var string
	 */
	protected $subSectionDelimiter = '';

	/**
	 * Parse sub-section
	 *
	 * @param  \SunCoastConnection\ParseX12\Raw  $raw  Raw X12 document object
	 *
	 * @return boolean  True if section was parsable or false otherwise
	 */
	abstract public function parse(Raw $raw);

	/**
	 * Get instance of section class with provided options
	 *
	 * @param  \SunCoastConnection\ParseX12\Options  $options     Options to create section object with
	 * @param  string                                             $parentName  Section parent name
	 *
	 * @return \SunCoastConnection\ParseX12\Raw  Raw object
	 */
	public static function getInstance(Options $options, $parentName = '/') {
		return new static($options, $parentName);
	}

	/**
	 * Return section named sequence
	 *
	 * @param  string  $sequence  Name of sequence
	 *
	 * @return array  Named sequence
	 */
	public static function getSequence($sequence) {
		if(property_exists(get_called_class(), $sequence)) {
			return static::$$sequence;
		}
	}

	/**
	 * Create a new Section
	 *
	 * @param  \SunCoastConnection\ParseX12\Options  $options     Options to create section object with
	 * @param  string                                             $parentName  Section parent name
	 */
	public function __construct(Options $options, $parentName = '/') {
		$this->options($options);

		$this->parentName = $parentName;
	}

	/**
	 * Return section name, optionally with full parent name
	 *
	 * @param  boolean  $full  Set to true to return section name with full parent name
	 *
	 * @return string   Section name
	 */
	public function getName($full = false) {
		$name = explode('\\', static::class);
		$name = array_pop($name);

		if($full) {
			$name = ($this->parentName === '/' ?
				'' :
				$this->parentName
			).'/'.$name;
		}

		return $name;
	}

	/**
	 * Return counts from sub-sections
	 *
	 * @return integer  Count from sub-sections
	 */
	public function getSubSectionCount() {
		$return = 0;

		foreach($this->subSections as $section) {
			$return += count($section);
		}

		return $return;
	}

	/**
	 * Set section options or retrieve section options
	 *
	 * @param  \SunCoastConnection\ParseX12\Options|null  $setOptions  Options to set section object with
	 *
	 * @return \SunCoastConnection\ParseX12\Options|null  Section options or null when not set
	 */
	protected function options(Options $setOptions = null) {
		static $options = null;

		if(is_null($options) && !is_null($setOptions)) {
			$options = $setOptions;
		}

		return $options;
	}

	/**
	 * Find segments and add to sub-sections
	 *
	 * @param  array                                          $sequence  Sequence array for section
	 * @param  \SunCoastConnection\ParseX12\Raw   $raw       Raw object containing segments
	 * @param  array                                          &$objects  Array of sub-sections to add to
	 *
	 * @return boolean  True if sub-section added to objects, false otherwise
	 */
	protected function parseSequence(array $sequence, Raw $raw, array &$objects) {
		$sectionDataTemplate = [
			'name' => '',
			'required' => true,
			'repeat' => 1,
		];

		$status = false;

		foreach($sequence as $sectionData) {
			$sectionData = array_merge($sectionDataTemplate, $sectionData);

			$sectionData['class'] = $this->options()->resolveAlias($sectionData['name']);

			if(get_parent_class($sectionData['class']) !== Segment::class ||
				($raw->valid() && $raw->current()->getName() === $sectionData['name'])
			) {
				$parsed = $this->parseSection($sectionData, $raw, $objects);

				if($parsed) {
					$status = true;
				}
			}
		}

		return $status;
	}

	/**
	 * Find segments and add to section
	 *
	 * @param  array                                          $sectionData  Sub-section data
	 * @param  \SunCoastConnection\ParseX12\Raw   $raw          Raw object containing segments
	 * @param  array                                          &$objects     Array of sub-sections to add to
	 *
	 * @return boolean  True if sub-section added to objects, false otherwise
	 */
	protected function parseSection(array $sectionData, Raw $raw, array &$objects) {
		$options = $this->options();

		$parentName = $this->getName(true);

		$status = false;

		do {
			$sectionData['repeat']--;

			if(get_parent_class($sectionData['class']) !== Segment::class) {
				$section = $sectionData['class']::getInstance(
					$options,
					$parentName
				);

				$parsed = $section->parse($raw);
			} elseif($raw->valid() && $raw->current()->getName() === $sectionData['name']) {
				// If current segment is matches current section name
				$section = $raw->current();

				$section->setParentName($parentName);

				$raw->next();

				$parsed = true;
			} else {
				$parsed = false;
			}

			if($parsed) {
				$status = true;
				$objects[] = $section;
			}
		} while($sectionData['repeat'] != 0 && $parsed);

		return $status;
	}

	/**
	 * Get string value of section
	 *
	 * @return string  Raw value of section, containing all sub-sections separated by configured delimiter
	 */
	public function __toString() {
		$return = '';

		foreach($this->subSections as $section) {
			$return .= implode(
				$this->subSectionDelimiter,
				$section
			);
		}

		return $return;
	}

}