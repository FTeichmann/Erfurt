<?php
/*
 * OWLProperty.php
 * Encoding: ISO-8859-1
 *
 * Copyright (c) 2006 S�ren Auer <soeren@auer.cx>
 *
 * This file is part of pOWL - web based ontology editor.
 *
 * pOWL is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * pOWL is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with pOWL; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * OWLProperty represents a property which can be either a datatype property or
 * a object property.
 * 
 * @package owlapi
 * @author S�ren Auer <soeren@auer.cx>
 * @copyright Copyright (c) 2004
 * @version $Id: OWLProperty.php 606 2006-10-31 14:02:53Z p_frischmuth $
 */
class OWLProperty  extends RDFSProperty {
	/**
	 * Checks whether the domain of this property is an union class description.
	 *
	 * @return boolean True if the domain is an union class description, false otherwise.
	 **/
	function domainIsUnionClass() {
		if($domain=$this->getDomain())
			if($domain->listUnionOf())
				return $domain;
		return false;
	}
	/**
	 * OWLProperty::addDomainToUnionClass()
	 *
	 * @param mixed $class
	 * @return
	 **/
	function addDomainToUnionClass($class) {
		if(!$domain=$this->domainIsUnionClass())
			$this->setDomainToUnionClass(array($class));
		else
			$domain->setUnionOf(array_push($domain->getUnionOf(),$class));
	}
	function setDomainToUnionClass($members) {
		if(count($members)==1)
			$this->setDomain($members);
		else {
			if(!$domain=$this->domainIsUnionClass()) {
				$domain=$this->model->addAnonymousClass();
				$this->setDomain($domain);
			}
			$domain->setUnionOf($members);
		}
	}
	function listDomainFromUnionClass() {
		if(!$domain=$this->domainIsUnionClass())
			return $this->getDomain()?array($this->getDomain()):array();
		else
			return $domain->listUnionOf();
	}
	/**
	 * Checks whether the range of this property is an union class description.
	 *
	 * @return boolean True if the range is an union class description, false otherwise.
	 **/
	function rangeIsUnionClass() {
		if($range=$this->getRange())
			if($range->listUnionOf())
				return $range;
		return false;
	}
	function rangeIsDataRange() {
		if($range=$this->getRange())
			if($range->type($GLOBALS['OWL_DataRange']))
				return $range;
		return false;
	}
	function listRangeFromDataRange() {
		if($this->rangeIsDataRange())
			return $this->model->getOneOf($this->getRange());
		else
			return false;
	}
	function addRangeToUnionClass($class) {
		if(!$range=$this->domainIsUnionClass())
			$this->setRangeToUnionClass(array($class));
		else
			$range->setUnionOf(array_push($range->getUnionOf(),$class));
	}
	function setRangeToUnionClass($members) {
		$members=array_filter($members);
#print_r($members); exit;
		if(count($members)==1 || count($members)==0)
			$this->setRange($members);
		else {
			if(!$range=$this->rangeIsUnionClass()) {
				$range=$this->model->addAnonymousClass();
				$this->setRange($range);
			}
			$range->setUnionOf($members);
			$this->setRange($range);
		}
	}
	function setRangeToDataRange($members) {
		$data=$this->listRangeFromDataRange();
		if($data && $members==array_keys($data))
			return;
		$this->removeRange();
		$range=new Blanknode($this->model->getUniqueResourceURI(BNODE_PREFIX));
		$this->model->add($range,$GLOBALS['RDF_type'],$GLOBALS['OWL_DataRange']);
		$oneOf=$this->model->addList($members);
		$this->model->add($range,$GLOBALS['OWL_oneOf'],$oneOf);
		$this->addRange($range);
	}
	function listRangeFromUnionClass() {
		if($this->rangeIsDataRange())
			return false;
		else if(!$range=$this->rangeIsUnionClass()) {
			$r=$this->getRange();
			return $r?array($r->getLocalName()=>$r):array();
		}
		else
			return $range->listUnionOf();
	}
	/**
	 * Returns an array of inverse OWLProperties indexed by their local names.
	 *
	 * @return array An array of inverse OWLProperties indexed by their local names.
	 **/
	function listInverseOf() {
		return $this->listPropertyValuesSymmetric($GLOBALS['OWL_inverseOf'],'Property');
	}
	/**
	 * Returns an array of equivalent OWLProperties indexed by their local names.
	 *
	 * @return array An array of equivalent OWLProperties indexed by their local names.
	 **/
	function listEquivalentProperties() {
		return $this->listPropertyValuesSymmetric($GLOBALS['OWL_equivalentProperty'],'Property');
	}
	/**
	 * Sets inverse properties to the properties given in values.
	 *
	 * @param array $values An array of properties, property URIs or property local names.
	 * @return boolean
	 **/
	function setInverseOf($values) {
		return $this->setPropertyValuesSymmetric($GLOBALS['OWL_inverseOf'],$values);
	}
	/**
	 * Sets equivalent properties to the properties given in values.
	 *
	 * @param array $values An array of properties, property URIs or property local names.
	 * @return boolean
	 **/
	function setEquivalentProperties($values) {
		return $this->setPropertyValuesSymmetric($GLOBALS['OWL_equivalentProperty'],$values);
	}
	/**
	 * Checks if this property is of type owl:FunctionalProperty.
	 * If the optional parameter $bool is given, owl:FunctionalProperty
	 * will be set or removed according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:FunctionalProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:FunctionalProperty, false otherwise.
	 **/
	function isFunctional($bool=NULL) {
		return $this->type($GLOBALS['OWL_FunctionalProperty'],$bool);
	}
	/**
	 * Checks if this property is of type owl:InverseFunctionalProperty.
	 * If the optional parameter $bool is given, owl:InverseFunctionalProperty
	 * will be set or removed according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:InverseFunctionalProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:InverseFunctionalProperty, false otherwise.
	 **/
	function isInverseFunctional($bool=NULL) {
		return $this->type($GLOBALS['OWL_InverseFunctionalProperty'],$bool);
	}
	/**
	 * Checks if this property is of type owl:SymmetricProperty.
	 * If the optional parameter $bool is given, owl:SymmetricProperty
	 * will be set or removed according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:SymmetricProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:SymmetricProperty, false otherwise.
	 **/
	function isSymmetric($bool=NULL) {
		return $this->type($GLOBALS['OWL_SymmetricProperty'],$bool);
	}
	/**
	 * Checks if this property is of type owl:TransitiveProperty.
	 * If the optional parameter $bool is given, owl:TransitiveProperty
	 * will be set or removed according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:TransitiveProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:TransitiveProperty, false otherwise.
	 **/
	function isTransitive($bool=NULL) {
		return $this->type($GLOBALS['OWL_TransitiveProperty'],$bool);
	}
	/**
	 * Checks if this property is of type owl:AnnotationProperty.
	 * If the optional parameter $bool is given, owl:AnnotationProperty
	 * will be set or removed according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:AnnotationProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:AnnotationProperty, false otherwise.
	 **/
	function isAnnotation($bool=NULL) {
		return $this->type($GLOBALS['OWL_AnnotationProperty'],$bool);
	}
	/**
	 * Checks if this property is of type owl:DatatypeProperty.
	 * If the optional parameter $bool is given, it will be toggled between
	 * owl:DatatypeProperty and owl:ObjectProperty according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:DatatypeProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:DatatypeProperty, false otherwise.
	 **/
	function isDatatypeProperty($bool=NULL) {
		if($bool!==NULL) {
			if($bool)
				$this->removeType($GLOBALS['OWL_ObjectProperty']);
			else
				$this->setType($GLOBALS['OWL_ObjectProperty']);
		}
		return $this->type($GLOBALS['OWL_DatatypeProperty'],$bool);
	}
	/**
	 * Checks if this property is of type owl:ObjectProperty.
	 * If the optional parameter $bool is given, it will be toggled between
	 * owl:ObjectProperty and owl:DatatypeProperty according to $bool.
	 *
	 * @param boolean $bool Shall the property be set to be of type owl:ObjectProperty - NULL - no change, TRUE - yes, FALSE - no.
	 * @return boolean True if the property is of type owl:ObjectProperty, false otherwise.
	 **/
	function isObjectProperty($bool=NULL) {
		if($bool!==NULL) {
			if($bool)
				$this->removeType($GLOBALS['OWL_DatatypeProperty']);
			else
				$this->setType($GLOBALS['OWL_DatatypeProperty']);
		}
		return $this->type($GLOBALS['OWL_ObjectProperty'],$bool);
	}
}
?>