<?php

/**
 * @package 	Aspen_Framework
 * @subpackage 	System
 * @author 		Michael Botsko
 * @copyright 	2009 Trellis Development, LLC
 * @since 		1.0
 */

/**
 * DOMDocument extension xml functions.
 * @package Aspen_Framework
 */
class Xml extends DOMDocument {


	/**
	 * Converts a multi-dimensional array to XML
	 * @param array $arr
	 * @param string $root_element
	 * @return string
	 */
	public function arrayToXml($arr, $root_element = 'response'){

		$this->dom = new Xml('1.0', 'utf-8');
		$this->dom->formatOutput = true;

		$response = $this->dom->createElement($root_element);
		$this->dom->fromMixed($arr, $response);
		$this->dom->appendChild($response);

		return $this->dom->saveXML();
	}


	/**
	 * Constructs elements and texts from an array or string.
	 * The array can contain an element's name in the index part
	 * and an element's text in the value part.
	 *
	 * It can also creates an xml with the same element tagName on the same
	 * level.
	 *
	 * ex:
	 * <nodes>
	 *   <node>text</node>
	 *   <node>
	 *     <field>hello</field>
	 *     <field>world</field>
	 *   </node>
	 * </nodes>
	 *
	 * Array should then look like:
	 *
	 * Array (
	 *   "nodes" => Array (
	 *     "node" => Array (
	 *       0 => "text"
	 *       1 => Array (
	 *         "field" => Array (
	 *           0 => "hello"
	 *           1 => "world"
	 *         )
	 *       )
	 *     )
	 *   )
	 * )
	 *
	 * @param mixed $mixed An array or string.
	 * @param DOMElement[optional] $domElement Then element
	 * from where the array will be construct to.
	 * @author Toni Van de Voorde
	 */
	public function fromMixed($mixed, DOMElement $domElement = null) {

		$domElement = is_null($domElement) ? $this : $domElement;

		if (is_array($mixed)) {
			foreach( $mixed as $index => $mixedElement ) {

				if ( is_int($index) ) {
					if ( $index == 0 ) {
						$node = $domElement;
					} else {
						$node = $this->createElement($domElement->tagName);
						$domElement->parentNode->appendChild($node);
					}
				} else {
					$node = $this->createElement($index);
					$domElement->appendChild($node);
				}

				$this->fromMixed($mixedElement, $node);

			}
		} else {
			$domElement->appendChild($this->createTextNode($mixed));
		}
	}
}
?>
